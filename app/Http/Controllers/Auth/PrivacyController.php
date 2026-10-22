<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    private const PENDING_PRIVACY_SESSION_KEY = 'auth.pending_privacy';

    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return view('auth.privacy-notice');
        }

        // Guest who submitted credentials but hasn't accepted yet: the notice is
        // shown as a modal on the login page itself, not a separate page.
        return redirect()->route('login');
    }

    public function accept(Request $request, AuthController $authController): RedirectResponse|JsonResponse
    {
        $wantsJson = $request->wantsJson();

        $validator = Validator::make($request->all(), [
            'accepted' => ['required', 'accepted'],
        ], [
            'accepted.required' => 'You must accept the Data Privacy Policy to continue.',
            'accepted.accepted' => 'You must accept the Data Privacy Policy to continue.',
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();

            return $wantsJson
                ? response()->json(['message' => $message], 422)
                : back()->with('error', $message);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $user->privacy_accepted_at = now();
            $user->save();

            $this->logPrivacyAcceptance($request, $user);

            $destination = redirect()->intended($this->dashboardRouteForRole($user->role));

            return $wantsJson
                ? response()->json(['redirect' => $destination->getTargetUrl()])
                : $destination;
        }

        $pending = $request->session()->get(self::PENDING_PRIVACY_SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['user_id'])) {
            $message = 'Your login session is no longer available. Please sign in again.';

            return $wantsJson
                ? response()->json(['message' => $message, 'redirect' => route('login')], 401)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        $user = User::find($pending['user_id']);

        if (! $user || $user->status === 'inactive') {
            $request->session()->forget(self::PENDING_PRIVACY_SESSION_KEY);
            $message = 'This account is no longer available for web login.';

            return $wantsJson
                ? response()->json(['message' => $message, 'redirect' => route('login')], 401)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        $user->privacy_accepted_at = now();
        $user->save();

        $this->logPrivacyAcceptance($request, $user);
        $request->session()->forget(self::PENDING_PRIVACY_SESSION_KEY);

        // Now that the notice is accepted, actually send the OTP and move on to
        // the verification step (the user is still a guest at this point).
        $otpResponse = $authController->startLoginOtpChallenge($request, $user, false);

        if (! $wantsJson) {
            return $otpResponse;
        }

        if ($otpResponse->getTargetUrl() === route('login.otp.show')) {
            return response()->json(['redirect' => route('login.otp.show')]);
        }

        return response()->json([
            'message' => 'We could not send a verification code right now. Please try again.',
        ], 500);
    }

    private function logPrivacyAcceptance(Request $request, User $user): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'auth',
            'new_values' => ['privacy_accepted_at' => $user->privacy_accepted_at->toDateTimeString()],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
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
}
