<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\UsAssignment;
use App\Services\AttendanceProof;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OTPHP\TOTP;
use Tymon\JWTAuth\Facades\JWTAuth;

/** Real signatures and anchors for business-rule tests; no verifier mocks. */
trait SignsMobileAttendance
{
    protected function attendanceCode(UsAssignment $assignment, Carbon $at): string
    {
        $totp = TOTP::createFromSecret(decrypt($assignment->totp_secret));
        $totp->setPeriod(120);

        return $totp->at($at->timestamp);
    }

    protected function signedAttendance(UsAssignment $assignment, string $token, array $fields): array
    {
        [$key, $public] = AttendanceSigningKey::generate();
        $userId = (string) JWTAuth::setToken($token)->getPayload()->get('sub');
        $captured = Carbon::parse($fields['occurred_at'] ?? now()->toIso8601String());
        // For a historical record, acquire its anchor at capture. A future
        // negative case uses an anchor issued now and must fail verification.
        $issuedAt = $captured->min(now());
        $anchor = Carbon::withTestNow($issuedAt, fn () => app(AttendanceProof::class)
            ->issue($public, $userId, (string) Str::uuid()));
        $stamp = (int) $captured->getTimestampMs();
        $event = [
            'version' => 1, 'anchor' => $anchor['anchor'], 'user_id' => $userId,
            'assignment_id' => (string) $assignment->id,
            'elapsed_ms' => $stamp - $anchor['server_ms'], 'uncertainty_ms' => 0,
            'captured_at_ms' => $stamp, 'wall_clock_ms' => $stamp,
            'client_log_id' => $fields['client_log_id'] ?? (string) Str::uuid(),
            'event_type' => $fields['event_type'],
            'totp' => array_key_exists('totp', $fields) ? $fields['totp'] : $this->attendanceCode($assignment, $captured),
            'tasks_performed' => $fields['tasks_performed'] ?? 'University service tasks.',
            'override_reason' => $fields['override_reason'] ?? null,
        ];
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        if (! openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Could not sign the attendance test payload.');
        }

        return ['signed_payload' => $payload, 'signature' => base64_encode($signature)];
    }

    protected function postSignedAttendance(UsAssignment $assignment, string $token, array $fields): TestResponse
    {
        return $this->withToken($token)->postJson("/api/mobile/us/assignments/{$assignment->id}/log",
            $this->signedAttendance($assignment, $token, $fields));
    }
}
