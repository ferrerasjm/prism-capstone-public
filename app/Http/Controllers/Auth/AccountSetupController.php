<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountSetupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AccountSetupController extends Controller
{
    public function show(string $token, AccountSetupService $accountSetup): View|Response
    {
        $setupToken = $accountSetup->findValid($token);

        if (! $setupToken) {
            return response()->view('auth.account-setup', [
                'invalid' => true,
                'token' => null,
                'user' => null,
                'expiresAt' => null,
            ], 410);
        }

        return view('auth.account-setup', [
            'invalid' => false,
            'token' => $token,
            'user' => $setupToken->user,
            'expiresAt' => $setupToken->expires_at,
        ]);
    }

    public function complete(Request $request, AccountSetupService $accountSetup): RedirectResponse
    {
        $plainToken = (string) $request->input('token');
        $setupToken = $accountSetup->findValid($plainToken);

        if (! $setupToken) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => 'This account setup link is invalid, expired, or has already been used.',
                ]);
        }

        $user = $setupToken->user;
        $identifier = $user->role === 'student'
            ? $user->studentProfile?->student_number
            : $user->institutional_id;
        $identifierLabel = $user->role === 'student' ? 'student number' : 'University ID';

        $validated = $request->validate([
            'token' => ['required', 'string', 'size:64'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->numbers(),
                function ($attribute, $value, $fail) use ($identifier, $identifierLabel): void {
                    if ($identifier && hash_equals((string) $identifier, (string) $value)) {
                        $fail("Your password cannot be the same as your {$identifierLabel}.");
                    }
                },
            ],
        ]);

        $user = $accountSetup->consume(
            $validated['token'],
            $validated['password'],
            $request->ip(),
        );

        if ($user->role === 'us_supervisor') {
            return redirect()
                ->route('login')
                ->with('success', 'Account setup complete. You may now sign in through the PRISM mobile app.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === 'student') {
            return redirect()->route('onboarding.profile');
        }

        return redirect($this->dashboardRoute($user->role))
            ->with('success', 'Your account setup is complete.');
    }

    private function dashboardRoute(string $role): string
    {
        return match ($role) {
            'super_admin' => route('admin.dashboard'),
            'discipline_coordinator' => route('coordinator.dashboard'),
            'discipline_officer' => route('officer.dashboard'),
            'reporting_user' => route('reporter.dashboard'),
            'guidance_officer' => route('guidance.dashboard'),
            default => route('login'),
        };
    }
}
