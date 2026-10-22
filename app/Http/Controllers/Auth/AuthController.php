<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MobileOtpChallenge;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Services\RecaptchaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class AuthController extends Controller
{
    private const MAX_RECAPTCHA_TOKEN_LENGTH = 8192;

    private const LOGIN_OTP_SESSION_KEY = 'auth.login_otp';

    private const PENDING_PRIVACY_SESSION_KEY = 'auth.pending_privacy';

    private const MAX_LOGIN_ATTEMPTS = 5;

    private const MAX_LOGIN_ATTEMPTS_PER_IP = 25;

    private const LOGIN_COOLDOWN_SECONDS = 60;

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect($this->dashboardRouteForRole(Auth::user()->role));
        }

        $pending = $request->session()->get(self::PENDING_PRIVACY_SESSION_KEY);

        return view('auth.login', [
            'openPrivacyModal' => is_array($pending) && isset($pending['user_id']),
        ]);
    }

    /**
     * Web login: credentials, reCAPTCHA, then the email OTP challenge.
     *
     * Rate limiting is applied on two independent keys - per account and
     * per IP - so neither a single targeted account nor a single source
     * address can be brute-forced, and the cooldown is checked before
     * validation so a locked-out attempt costs nothing. Success does not
     * log the user in; it starts the OTP challenge.
     */
    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $submittedEmail = (string) $request->input('email');
        $lockKey = $this->loginLockKey($request, $submittedEmail);
        $ipLockKey = $this->loginIpLockKey($request);

        if (RateLimiter::tooManyAttempts($lockKey, 1)
            || RateLimiter::tooManyAttempts($ipLockKey, 1)) {
            return $this->loginCooldownResponse($request, $lockKey, $ipLockKey);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => [
                $recaptcha->enabled() ? 'required' : 'nullable',
                'string',
                'max:'.self::MAX_RECAPTCHA_TOKEN_LENGTH,
            ],
        ], [
            'g-recaptcha-response.required' => 'Please complete the "I\'m not a robot" verification.',
        ]);

        $attemptKey = $this->loginAttemptKey($request, (string) $validated['email']);
        $ipAttemptKey = $this->loginIpAttemptKey($request);

        if ($recaptcha->enabled() && ! $recaptcha->verify(
            (string) ($validated['g-recaptcha-response'] ?? ''),
            $request->ip()
        )) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'g-recaptcha-response' => 'Robot verification failed or expired. Please try again.',
                ]);
        }

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($attemptKey, self::LOGIN_COOLDOWN_SECONDS);
            RateLimiter::hit($ipAttemptKey, self::LOGIN_COOLDOWN_SECONDS);
            $accountLimitReached = RateLimiter::attempts($attemptKey) >= self::MAX_LOGIN_ATTEMPTS;
            $ipLimitReached = RateLimiter::attempts($ipAttemptKey) >= self::MAX_LOGIN_ATTEMPTS_PER_IP;

            if ($accountLimitReached) {
                RateLimiter::clear($attemptKey);
                RateLimiter::hit($lockKey, self::LOGIN_COOLDOWN_SECONDS);
            }

            if ($ipLimitReached) {
                RateLimiter::clear($ipAttemptKey);
                RateLimiter::hit($ipLockKey, self::LOGIN_COOLDOWN_SECONDS);
            }

            if ($accountLimitReached || $ipLimitReached) {
                return $this->loginCooldownResponse($request, $lockKey, $ipLockKey);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password']);
        }

        $user = User::query()->where('email', $validated['email'])->firstOrFail();
        RateLimiter::clear($attemptKey);

        if ($user->status === 'inactive') {
            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been deactivated. Please contact the Discipline Office.']);
        }

        if ($user->role === 'us_supervisor') {
            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Web access is not available for this role. Please use the mobile app.']);
        }

        if ($user->status === 'active' && $user->hasUnfinishedSetup()) {
            return redirect()->route('login')
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account is still awaiting setup. Use the account setup link or ask the system administrator to resend it.']);
        }

        // The privacy notice is shown on every login, not just once. Onboarding
        // accounts see it after finishing onboarding instead of here.
        if ($user->status !== 'pending_setup') {
            $request->session()->regenerate();
            $request->session()->put(self::PENDING_PRIVACY_SESSION_KEY, [
                'user_id' => $user->id,
            ]);

            return redirect()->route('login');
        }

        return $this->startLoginOtpChallenge($request, $user, false);
    }

    public function showLoginOtpForm(Request $request): View|RedirectResponse
    {
        $challenge = $request->session()->get(self::LOGIN_OTP_SESSION_KEY);

        if (! is_array($challenge) || ! isset($challenge['user_id'])) {
            return redirect()->route('login');
        }

        $user = User::find($challenge['user_id']);

        if (! $user || (int) ($challenge['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::LOGIN_OTP_SESSION_KEY);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification code expired. Please sign in again.']);
        }

        return view('auth.login-otp', [
            'email' => $user->email,
            'expiresInMinutes' => (int) config('auth.login_otp.expire', 10),
            'resendAvailableAt' => (int) ($challenge['resend_available_at'] ?? 0),
            'debugOtp' => $challenge['debug_otp'] ?? null,
        ]);
    }

    public function verifyLoginOtp(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Enter the six-digit verification code.',
            'otp.digits' => 'The verification code must contain exactly six digits.',
        ]);

        if ($validator->fails()) {
            return back()->with('error', $validator->errors()->first());
        }

        $validated = $validator->validated();
        $challenge = $request->session()->get(self::LOGIN_OTP_SESSION_KEY);

        if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['code_hash'])) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your login verification session is no longer available. Please sign in again.']);
        }

        if ((int) ($challenge['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::LOGIN_OTP_SESSION_KEY);

            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification code expired. Please sign in again.']);
        }

        if (! hash_equals((string) $challenge['code_hash'], $this->hashOtp((string) $validated['otp']))) {
            return back()->with('error', 'The verification code is incorrect.');
        }

        $user = User::find($challenge['user_id']);

        if (! $user || $user->status === 'inactive' || $user->role === 'us_supervisor') {
            $request->session()->forget(self::LOGIN_OTP_SESSION_KEY);

            return redirect()->route('login')
                ->withErrors(['email' => 'This account is no longer available for web login.']);
        }

        $remember = (bool) ($challenge['remember'] ?? false);
        $request->session()->forget(self::LOGIN_OTP_SESSION_KEY);

        return $this->completeLogin($request, $user, $remember);
    }

    public function resendLoginOtp(Request $request): RedirectResponse
    {
        $challenge = $request->session()->get(self::LOGIN_OTP_SESSION_KEY);

        if (! is_array($challenge) || ! isset($challenge['user_id'])) {
            return redirect()->route('login');
        }

        $retryAfter = (int) ($challenge['resend_available_at'] ?? 0) - now()->timestamp;

        if ($retryAfter > 0) {
            return back()->with('error', "Please wait {$retryAfter} seconds before requesting another code.");
        }

        $user = User::find($challenge['user_id']);

        if (! $user) {
            $request->session()->forget(self::LOGIN_OTP_SESSION_KEY);

            return redirect()->route('login');
        }

        return $this->startLoginOtpChallenge($request, $user, (bool) ($challenge['remember'] ?? false), true);
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'logout',
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function apiLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = auth('api')->user();

        if ($user->status === 'inactive') {
            auth('api')->logout();

            return response()->json(['message' => 'Account deactivated.'], 403);
        }

        if ($user->role !== 'us_supervisor') {
            auth('api')->logout();

            return response()->json(['message' => 'Unauthorized role for mobile access.'], 403);
        }

        if ((bool) config('auth.mobile_otp.enabled', false)) {
            // Invalidate the JWT minted by auth('api')->attempt() above — the
            // OTP step, not password validation, is what should hand out a
            // usable token.
            auth('api')->logout();

            return $this->issueMobileOtp($request, $user);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $this->respondWithToken($token, $user);
    }

    /**
     * Verifies a mobile login OTP and completes the login.
     *
     * The challenge is looked up by token and email together, not
     * token alone, and checked for both prior use and expiry before
     * the code itself is compared - a used or expired challenge never
     * reaches the hash_equals comparison.
     */
    public function apiOtpVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp_token' => ['required', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        $challenge = MobileOtpChallenge::query()
            ->where('otp_token', $validated['otp_token'])
            ->whereHas('user', fn ($query) => $query->where('email', $validated['email']))
            ->first();

        if (! $challenge || $challenge->isUsed() || $challenge->isExpired()) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        if ($challenge->isLocked()) {
            return response()->json(['message' => 'Too many incorrect attempts. Request a new code.'], 429);
        }

        if (! hash_equals($challenge->code_hash, $this->hashOtp($validated['code']))) {
            $challenge->increment('attempts');

            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $user = $challenge->user;

        if (! $user || $user->status === 'inactive' || $user->role !== 'us_supervisor') {
            $challenge->forceFill(['used_at' => now()])->save();

            return response()->json(['message' => 'This account is no longer available for mobile login.'], 403);
        }

        $challenge->forceFill(['used_at' => now()])->save();

        $token = auth('api')->login($user);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return $this->respondWithToken($token, $user);
    }

    public function apiOtpResend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp_token' => ['required', 'uuid'],
        ]);

        $challenge = MobileOtpChallenge::query()
            ->where('otp_token', $validated['otp_token'])
            ->whereHas('user', fn ($query) => $query->where('email', $validated['email']))
            ->first();

        if (! $challenge || $challenge->isUsed()) {
            return response()->json(['message' => 'Invalid or expired code.'], 422);
        }

        $retryAfter = now()->diffInSeconds($challenge->resend_available_at, false);

        if ($retryAfter > 0) {
            return response()->json([
                'message' => "Please wait {$retryAfter} seconds before requesting another code.",
            ], 429);
        }

        $user = $challenge->user;

        if (! $user || $user->status === 'inactive' || $user->role !== 'us_supervisor') {
            $challenge->forceFill(['used_at' => now()])->save();

            return response()->json(['message' => 'This account is no longer available for mobile login.'], 403);
        }

        return $this->issueMobileOtp($request, $user, $challenge);
    }

    public function apiLogout(Request $request): JsonResponse
    {
        AuditLog::create([
            'user_id' => auth('api')->id(),
            'action' => 'logout',
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        auth('api')->logout();

        $response = response()->json(['message' => 'Successfully logged out.']);

        if (config('jwt.cookie.enabled')) {
            $response->withCookie(Cookie::forget(
                (string) config('jwt.cookie.name', 'token'),
                (string) config('jwt.cookie.path', '/api'),
                config('jwt.cookie.domain'),
            ));
            $response->withCookie(Cookie::forget(
                (string) config('jwt.cookie.csrf_cookie', 'API-XSRF-TOKEN'),
                (string) config('jwt.cookie.csrf_path', '/'),
                config('jwt.cookie.domain'),
            ));
        }

        return $response;
    }

    public function apiRefresh(): JsonResponse
    {
        try {
            $token = auth('api')->refresh();
        } catch (\Tymon\JWTAuth\Exceptions\TokenBlacklistedException|\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['message' => 'Session expired. Please sign in again.'], 401);
        }

        // refresh() blacklists the request's token without swapping it out of the guard, so user() would
        // re-read that dead token and come back null, rejecting every refresh as a lost account.
        $user = auth('api')->setToken($token)->user();

        if (! $user || $user->status === 'inactive' || $user->role !== 'us_supervisor') {
            auth('api')->logout();

            return response()->json(['message' => 'This account is no longer available for mobile login.'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    public function apiMe(): JsonResponse
    {
        return response()->json($this->formatUserForApi(auth('api')->user()));
    }

    public function apiUpdateMe(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $validated = $request->validate([
            'contact_number' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^[0-9+()\-\s]{7,20}$/'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'contact_number.regex' => 'Enter a valid contact number.',
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

        $updates = [];

        if (array_key_exists('contact_number', $validated)) {
            $updates['contact_number'] = $validated['contact_number'];
        }

        if ($newProfilePhoto) {
            $updates['profile_photo'] = $newProfilePhoto;
        }

        if ($updates !== []) {
            $user->update($updates);

            if ($newProfilePhoto && $oldProfilePhoto && $oldProfilePhoto !== $newProfilePhoto) {
                Storage::disk('public')->delete($oldProfilePhoto);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'update',
                'module' => 'account_profiles',
                'record_id' => $user->id,
                'new_values' => array_keys($updates),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        return response()->json($this->formatUserForApi($user->fresh()));
    }

    private function dashboardRouteForRole(string $role): string
    {
        return match ($role) {
            'super_admin' => route('admin.dashboard'),
            'discipline_coordinator' => route('coordinator.dashboard'),
            'discipline_officer' => route('officer.dashboard'),
            'reporting_user' => route('reporter.dashboard'),
            'guidance_officer' => route('guidance.dashboard'),
            'student' => route('student.dashboard'),
            'us_supervisor' => route('login'),
            default => route('login'),
        };
    }

    public function startLoginOtpChallenge(
        Request $request,
        User $user,
        bool $remember,
        bool $isResend = false,
    ): RedirectResponse {
        $otp = (string) random_int(100000, 999999);
        $expiresInMinutes = (int) config('auth.login_otp.expire', 10);
        $resendCooldown = (int) config('auth.login_otp.resend_cooldown', 60);
        $mailEnabled = (bool) config('auth.login_otp.mail_enabled', true);

        if ($mailEnabled) {
            try {
                $user->notify(new LoginOtpNotification($otp, $expiresInMinutes));
            } catch (Throwable $exception) {
                Log::error('Unable to send login OTP notification.', [
                    'user_id' => $user->id,
                    'exception' => $exception,
                ]);

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors(['email' => 'We could not send a verification code right now. Please try again.']);
            }
        } else {
            Log::info('Login OTP email suppressed (auth.login_otp.mail_enabled is off).', [
                'user_id' => $user->id,
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(self::LOGIN_OTP_SESSION_KEY, [
            'user_id' => $user->id,
            'code_hash' => $this->hashOtp($otp),
            'expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
            'resend_available_at' => now()->addSeconds($resendCooldown)->timestamp,
            'remember' => $remember,
            // Kept with the rest of the challenge state (not flashed) so it survives a
            // page refresh instead of vanishing after being read once.
            'debug_otp' => (! $mailEnabled || config('auth.login_otp.console') || $this->shouldRevealOtp($user))
                ? $otp
                : null,
        ]);

        return redirect()->route('login.otp.show')
            ->with('info', $isResend
                ? 'A new verification code has been sent.'
                : ($mailEnabled
                    ? 'We sent a verification code to your registered email address.'
                    : 'Email delivery is temporarily disabled — your verification code is shown below.'));
    }

    private function issueMobileOtp(
        Request $request,
        User $user,
        ?MobileOtpChallenge $existingChallenge = null,
    ): JsonResponse {
        $otp = (string) random_int(100000, 999999);
        $expiresInMinutes = (int) config('auth.mobile_otp.expire', 5);
        $resendCooldown = (int) config('auth.mobile_otp.resend_cooldown', 30);
        $mailEnabled = (bool) config('auth.mobile_otp.mail_enabled', true);

        $challenge = $existingChallenge ?? new MobileOtpChallenge([
            'otp_token' => (string) Str::uuid(),
            'user_id' => $user->id,
        ]);

        $challenge->fill([
            'code_hash' => $this->hashOtp($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'resend_available_at' => now()->addSeconds($resendCooldown),
            'used_at' => null,
        ])->save();

        if ($mailEnabled) {
            try {
                $user->notify(new LoginOtpNotification($otp, $expiresInMinutes));
            } catch (Throwable $exception) {
                Log::error('Unable to send mobile login OTP notification.', [
                    'user_id' => $user->id,
                    'exception' => $exception,
                ]);

                return response()->json([
                    'message' => 'We could not send a verification code right now. Please try again.',
                ], 500);
            }
        } else {
            Log::info('Mobile login OTP email suppressed (auth.mobile_otp.mail_enabled is off).', [
                'user_id' => $user->id,
            ]);
        }

        return response()->json(array_filter([
            'otp_required' => true,
            'otp_token' => $challenge->otp_token,
            // Temporary reveal for while email delivery is disabled, mirroring
            // the web login OTP page showing the code when mail is off — the
            // mobile app has no server-rendered page to flash it into, so it
            // rides in the response instead. Remove once mail_enabled is
            // back on for good.
            'debug_otp' => $mailEnabled ? null : $otp,
        ], fn ($value) => $value !== null));
    }

    private function hashOtp(string $otp): string
    {
        return hash_hmac('sha256', $otp, (string) config('app.key'));
    }

    private function shouldRevealOtp(User $user): bool
    {
        $revealEmails = array_map('strtolower', config('auth.login_otp.reveal_emails', []));

        return in_array(strtolower($user->email), $revealEmails, true);
    }

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'module' => 'auth',
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        if ($user->status === 'pending_setup') {
            return redirect()->route('onboarding.password');
        }

        $destination = redirect()->intended($this->dashboardRouteForRole($user->role))->getTargetUrl();

        return redirect()->route('splash')->with('post_login_redirect', $destination);
    }

    private function loginAttemptKey(Request $request, string $email): string
    {
        return 'web-login-attempts:'.Str::lower(trim($email)).'|'.$request->ip();
    }

    private function loginLockKey(Request $request, string $email): string
    {
        return 'web-login-lock:'.Str::lower(trim($email)).'|'.$request->ip();
    }

    private function loginCooldownResponse(Request $request, string $lockKey, string $ipLockKey): RedirectResponse
    {
        $retryAfter = max(
            RateLimiter::availableIn($lockKey),
            RateLimiter::availableIn($ipLockKey),
            1,
        );

        return redirect()->route('login')
            ->withInput($request->only('email'))
            ->with('error', "Too many login attempts. Please wait {$retryAfter} seconds before trying again.");
    }

    private function loginIpAttemptKey(Request $request): string
    {
        return 'web-login-ip-attempts:'.$request->ip();
    }

    private function loginIpLockKey(Request $request): string
    {
        return 'web-login-ip-lock:'.$request->ip();
    }

    private function respondWithToken(string $token, User $user): JsonResponse
    {
        $response = response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => $this->formatUserForApi($user),
        ]);

        if (config('jwt.cookie.enabled')) {
            $response->withCookie(cookie(
                (string) config('jwt.cookie.name', 'token'),
                $token,
                (int) config('jwt.ttl', 60),
                (string) config('jwt.cookie.path', '/api'),
                config('jwt.cookie.domain'),
                (bool) config('jwt.cookie.secure', false),
                true,
                false,
                (string) config('jwt.cookie.same_site', 'strict'),
            ));
            $response->withCookie(cookie(
                (string) config('jwt.cookie.csrf_cookie', 'API-XSRF-TOKEN'),
                hash_hmac('sha256', $token, (string) config('app.key')),
                (int) config('jwt.ttl', 60),
                (string) config('jwt.cookie.csrf_path', '/'),
                config('jwt.cookie.domain'),
                (bool) config('jwt.cookie.secure', false),
                false,
                false,
                (string) config('jwt.cookie.same_site', 'strict'),
            ));
        }

        return $response;
    }

    private function formatUserForApi(User $user): array
    {
        return [
            'id' => $user->id,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'office_name' => $user->office_name,
            'status' => $user->status,
            'contact_number' => $user->contact_number,
            'avatar_url' => $user->profile_photo
                ? Storage::disk('public')->url($user->profile_photo)
                : null,
        ];
    }
}
