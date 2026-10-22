<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccountSetupToken;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Issues, validates and consumes single-use account-setup tokens. Only a
 * hash of each token is stored; the plaintext exists solely in the emailed
 * link, and a token is invalidated once consumed or once a replacement is
 * issued.
 */
class AccountSetupService
{
    private const TOKEN_BYTES = 32;

    public function issue(User $user, ?int $issuedBy = null): AccountSetupToken
    {
        if ($user->status !== 'pending_setup') {
            throw ValidationException::withMessages([
                'account' => 'Setup links can only be issued for accounts awaiting setup.',
            ]);
        }

        $plainToken = bin2hex(random_bytes(self::TOKEN_BYTES));
        $expiresAt = now()->addMinutes($this->expiryMinutes());

        $setupToken = AccountSetupToken::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'token_hash' => $this->hash($plainToken),
                'issued_by' => $issuedBy,
                'expires_at' => $expiresAt,
                'consumed_at' => null,
            ],
        );

        $user->notify(new AccountSetupLinkNotification(
            $plainToken,
            $expiresAt->toImmutable(),
        ));

        return $setupToken;
    }

    public function findValid(string $plainToken): ?AccountSetupToken
    {
        if (! $this->hasValidFormat($plainToken)) {
            return null;
        }

        return AccountSetupToken::query()
            ->with('user')
            ->where('token_hash', $this->hash($plainToken))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->whereHas('user', fn ($query) => $query->where('status', 'pending_setup'))
            ->first();
    }

    public function consume(string $plainToken, string $password, ?string $ipAddress): User
    {
        if (! $this->hasValidFormat($plainToken)) {
            throw $this->invalidTokenException();
        }

        return DB::transaction(function () use ($plainToken, $password, $ipAddress): User {
            $setupToken = AccountSetupToken::query()
                ->where('token_hash', $this->hash($plainToken))
                ->lockForUpdate()
                ->first();

            if (
                ! $setupToken
                || $setupToken->consumed_at !== null
                || $setupToken->expires_at->isPast()
            ) {
                throw $this->invalidTokenException();
            }

            $user = User::query()->lockForUpdate()->find($setupToken->user_id);

            if (! $user || $user->status !== 'pending_setup') {
                throw $this->invalidTokenException();
            }

            $activatedStatus = $user->role === 'student' ? 'pending_setup' : 'active';

            $user->update([
                'password' => Hash::make($password),
                'status' => $activatedStatus,
            ]);

            $setupToken->update(['consumed_at' => now()]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update',
                'module' => 'auth',
                'record_id' => $user->id,
                'new_values' => [
                    'account_setup_completed' => true,
                    'status' => $activatedStatus,
                ],
                'ip_address' => $ipAddress,
                'created_at' => now(),
            ]);

            return $user->fresh();
        });
    }

    public function expiryMinutes(): int
    {
        return max(1, (int) config('auth.account_setup.expire', 4320));
    }

    private function hasValidFormat(string $plainToken): bool
    {
        return strlen($plainToken) === self::TOKEN_BYTES * 2
            && ctype_xdigit($plainToken);
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function invalidTokenException(): ValidationException
    {
        return ValidationException::withMessages([
            'token' => 'This account setup link is invalid, expired, or has already been used.',
        ]);
    }
}
