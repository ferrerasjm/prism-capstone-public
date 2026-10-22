<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MobileOtpChallenge;
use App\Models\User;
use App\Notifications\PasswordChangeOtpNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AccountProfileController extends Controller
{
    private const PASSWORD_OTP_SESSION_KEY = 'account.password_otp';

    public function show(): View
    {
        auth()->user()->load(['studentProfile', 'academicUnit']);

        return view('dashboard.profile');
    }

    /**
     * Updates the signed-in user's own profile.
     *
     * The rule set is role-dependent: students supply contact and
     * guardian details but cannot change their own name, while staff
     * may edit their name and supply neither. Both may replace the
     * profile photo, with the previous file cleaned up afterwards.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isStudent = $user->role === 'student';

        $rules = [
            'middle_name' => ['nullable', 'string', 'max:100'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];

        if ($isStudent) {
            $rules += [
                'contact_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
                'guardian_name' => ['required', 'string', 'max:255'],
                'guardian_contact' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
                'guardian_relationship' => ['required', Rule::in(['Father', 'Mother', 'Guardian', 'Sibling', 'Other'])],
            ];
        } else {
            $rules += [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
            ];
        }

        $validated = $request->validate($rules, [
            'contact_number.regex' => 'Enter a valid contact number.',
            'guardian_contact.regex' => 'Enter a valid guardian contact number.',
            'profile_photo.image' => 'Choose a valid image for your profile photo.',
            'profile_photo.mimes' => 'The profile photo must be a JPG, PNG, or WebP image.',
            'profile_photo.max' => 'The profile photo must not exceed 2 MB.',
        ]);

        $oldProfilePhoto = $user->profile_photo;
        $newProfilePhoto = null;

        if ($request->hasFile('profile_photo')) {
            $storedPath = $request->file('profile_photo')->store(
                "profile-photos/{$user->id}",
                'public'
            );

            if (! is_string($storedPath) || $storedPath === '') {
                throw new RuntimeException('The profile photo could not be stored.');
            }

            $newProfilePhoto = $storedPath;
        }

        try {
            DB::transaction(function () use (
                $request,
                $user,
                $validated,
                $isStudent,
                $newProfilePhoto
            ): void {
                $userFields = $isStudent
                    ? ['middle_name']
                    : ['first_name', 'last_name', 'middle_name'];

                if ($newProfilePhoto) {
                    $userFields[] = 'profile_photo';
                }

                $userUpdates = collect($validated)->only($userFields)->all();

                if ($newProfilePhoto) {
                    $userUpdates['profile_photo'] = $newProfilePhoto;
                }

                $oldUserValues = collect($user->only($userFields))
                    ->filter(fn ($value, $key) => ($userUpdates[$key] ?? null) !== $value)
                    ->all();

                $user->update($userUpdates);

                $oldValues = ['user' => $oldUserValues];
                $newValues = ['user' => collect($userUpdates)->only(array_keys($oldUserValues))->all()];

                if ($isStudent) {
                    $profileFields = ['contact_number', 'guardian_name', 'guardian_contact', 'guardian_relationship'];
                    $profile = $user->studentProfile;
                    $profileUpdates = collect($validated)->only($profileFields)->all();
                    $existingProfileValues = $profile
                        ? $profile->only($profileFields)
                        : array_fill_keys($profileFields, null);
                    $oldProfileValues = collect($existingProfileValues)
                        ->filter(fn ($value, $key) => ($profileUpdates[$key] ?? null) !== $value)
                        ->all();

                    $user->studentProfile()->updateOrCreate(['user_id' => $user->id], $profileUpdates);
                    $oldValues['student_profile'] = $oldProfileValues;
                    $newValues['student_profile'] = collect($profileUpdates)->only(array_keys($oldProfileValues))->all();
                }

                if (collect($newValues)->flatten()->isNotEmpty()) {
                    AuditLog::create([
                        'user_id' => $user->id,
                        'action' => 'update',
                        'module' => 'account_profiles',
                        'record_id' => $user->id,
                        'old_values' => $oldValues,
                        'new_values' => $newValues,
                        'ip_address' => $request->ip(),
                        'created_at' => now(),
                    ]);
                }
            });
        } catch (Throwable $exception) {
            if ($newProfilePhoto) {
                Storage::disk('public')->delete($newProfilePhoto);
            }

            throw $exception;
        }

        if ($newProfilePhoto && $oldProfilePhoto && $oldProfilePhoto !== $newProfilePhoto) {
            Storage::disk('public')->delete($oldProfilePhoto);
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    public function showPasswordForm(Request $request): View
    {
        $pending = $request->session()->get(self::PASSWORD_OTP_SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['user_id'], $pending['otp_token']) || $pending['user_id'] !== $request->user()->id) {
            return view('account.change-password', ['otpPending' => null]);
        }

        if ((int) ($pending['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);

            return view('account.change-password', ['otpPending' => null]);
        }

        return view('account.change-password', [
            'otpPending' => [
                'expiresInMinutes' => (int) config('auth.password_otp.expire', 10),
                'resendAvailableAt' => (int) ($pending['resend_available_at'] ?? 0),
                'debugOtp' => $pending['debug_otp'] ?? null,
            ],
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) use ($user): void {
                    $identifier = $user->role === 'student'
                        ? $user->studentProfile?->student_number
                        : $user->institutional_id;

                    if ($identifier && $value === $identifier) {
                        $fail('Your password cannot be the same as your University ID.');
                    }
                },
            ],
        ]);

        return $this->issuePasswordOtp($request, $user, Hash::make($validated['password']));
    }

    /**
     * Verifies the OTP for a password change and applies it.
     *
     * The pending challenge is read from the session and checked
     * against the signed-in user's id, so a session cannot be replayed
     * against a different account, and is discarded once it expires
     * rather than left to be retried indefinitely.
     */
    public function verifyPasswordOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Enter the six-digit verification code.',
            'otp.digits' => 'The verification code must contain exactly six digits.',
        ]);

        $user = $request->user();
        $pending = $request->session()->get(self::PASSWORD_OTP_SESSION_KEY);

        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $user->id) {
            return redirect()->route('account.password')
                ->withErrors(['otp' => 'Your verification session is no longer available. Please try again.']);
        }

        if ((int) ($pending['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);

            return redirect()->route('account.password')
                ->withErrors(['otp' => 'Your verification code expired. Please try again.']);
        }

        $challenge = MobileOtpChallenge::query()
            ->where('otp_token', $pending['otp_token'])
            ->where('user_id', $user->id)
            ->first();

        if (! $challenge || $challenge->isUsed() || $challenge->isExpired()) {
            $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);

            return redirect()->route('account.password')
                ->withErrors(['otp' => 'Your verification code expired. Please try again.']);
        }

        if ($challenge->isLocked()) {
            $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);
            $challenge->forceFill(['used_at' => now()])->save();

            return redirect()->route('account.password')
                ->withErrors(['otp' => 'Too many incorrect attempts. Please request a new code.']);
        }

        if (! hash_equals($challenge->code_hash, $this->hashPasswordOtp($validated['otp']))) {
            $challenge->increment('attempts');

            return back()->withErrors(['otp' => 'The verification code is incorrect.']);
        }

        $challenge->forceFill(['used_at' => now()])->save();
        $user->update(['password' => $pending['password_hash']]);
        $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'auth',
            'record_id' => $user->id,
            'new_values' => ['password_changed' => true],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return redirect()->route('dashboard.profile')->with('success', 'Password updated successfully.');
    }

    public function resendPasswordOtp(Request $request): RedirectResponse
    {
        $user = $request->user();
        $pending = $request->session()->get(self::PASSWORD_OTP_SESSION_KEY);

        if (! is_array($pending) || ($pending['user_id'] ?? null) !== $user->id) {
            return redirect()->route('account.password');
        }

        $retryAfter = (int) ($pending['resend_available_at'] ?? 0) - now()->timestamp;

        if ($retryAfter > 0) {
            return back()->withErrors(['otp' => "Please wait {$retryAfter} seconds before requesting another code."]);
        }

        $challenge = MobileOtpChallenge::query()
            ->where('otp_token', $pending['otp_token'])
            ->where('user_id', $user->id)
            ->first();

        if (! $challenge || $challenge->isUsed()) {
            $request->session()->forget(self::PASSWORD_OTP_SESSION_KEY);

            return redirect()->route('account.password')
                ->withErrors(['otp' => 'Your verification session is no longer available. Please try again.']);
        }

        return $this->issuePasswordOtp($request, $user, $pending['password_hash'], $challenge, true);
    }

    public function cancelPasswordOtp(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull(self::PASSWORD_OTP_SESSION_KEY);

        if (is_array($pending) && isset($pending['otp_token'])) {
            MobileOtpChallenge::query()
                ->where('otp_token', $pending['otp_token'])
                ->where('user_id', $request->user()->id)
                ->update(['used_at' => now()]);
        }

        return redirect()->route('account.password');
    }

    private function issuePasswordOtp(
        Request $request,
        User $user,
        string $passwordHash,
        ?MobileOtpChallenge $existingChallenge = null,
        bool $isResend = false,
    ): RedirectResponse {
        $otp = (string) random_int(100000, 999999);
        $expiresInMinutes = (int) config('auth.password_otp.expire', 10);
        $resendCooldown = (int) config('auth.password_otp.resend_cooldown', 60);
        $mailEnabled = (bool) config('auth.password_otp.mail_enabled', true);

        $challenge = $existingChallenge ?? new MobileOtpChallenge([
            'otp_token' => (string) Str::uuid(),
            'user_id' => $user->id,
        ]);

        $challenge->fill([
            'code_hash' => $this->hashPasswordOtp($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'resend_available_at' => now()->addSeconds($resendCooldown),
            'used_at' => null,
        ])->save();

        if ($mailEnabled) {
            try {
                $user->notify(new PasswordChangeOtpNotification($otp, $expiresInMinutes));
            } catch (Throwable $exception) {
                Log::error('Unable to send password change OTP notification.', [
                    'user_id' => $user->id,
                    'exception' => $exception,
                ]);

                return back()->withErrors(['password' => 'We could not send a verification code right now. Please try again.']);
            }
        } else {
            Log::info('Password change OTP email suppressed (auth.password_otp.mail_enabled is off).', [
                'user_id' => $user->id,
            ]);
        }

        $request->session()->put(self::PASSWORD_OTP_SESSION_KEY, [
            'user_id' => $user->id,
            'password_hash' => $passwordHash,
            'otp_token' => $challenge->otp_token,
            'expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
            'resend_available_at' => now()->addSeconds($resendCooldown)->timestamp,
            // Kept with the rest of the pending-challenge state (not flashed) so it
            // survives a page refresh instead of vanishing after being read once.
            'debug_otp' => (! $mailEnabled || config('auth.password_otp.console')) ? $otp : null,
        ]);

        return redirect()->route('account.password')
            ->with('info', $isResend
                ? 'A new verification code has been sent.'
                : ($mailEnabled
                    ? 'We sent a verification code to your registered email address.'
                    : 'Email delivery is temporarily disabled — your verification code is shown below.'));
    }

    private function hashPasswordOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }
}
