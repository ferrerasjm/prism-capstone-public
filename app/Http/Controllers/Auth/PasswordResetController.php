<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AccountSetupService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    private const RESET_LINK_MESSAGE = 'If the email address exists in PRISM, a password reset link has been sent.';

    private const RESET_LINK_ERROR_MESSAGE = 'We could not send the password reset email right now. Please try again later or contact the system administrator.';

    private const PASSWORD_RESET_MESSAGE = 'Your password has been reset. You may now sign in.';

    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request, AccountSetupService $accountSetup): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower($validated['email']);
        $user = User::where('email', $email)->first();

        if (! $user) {
            return back()->with('success', self::RESET_LINK_MESSAGE);
        }

        try {
            if ($user->status === 'pending_setup') {
                $accountSetup->issue($user);
            } else {
                Password::sendResetLink(['email' => $email]);
            }
        } catch (TransportExceptionInterface $exception) {
            report($exception);

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => self::RESET_LINK_ERROR_MESSAGE,
                ]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'update',
            'module' => 'auth',
            'record_id' => $user->id,
            'new_values' => $user->status === 'pending_setup'
                ? ['account_setup_link_reissued' => true]
                : ['password_reset_requested' => true],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return back()->with('success', self::RESET_LINK_MESSAGE);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $validated['email'] = strtolower($validated['email']);
        $user = User::where('email', $validated['email'])->first();

        if ($user?->status === 'pending_setup') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'This account is awaiting activation. Use your account setup link or contact the system administrator for a replacement.',
                ]);
        }

        $status = Password::reset(
            $validated,
            function (User $user, string $password) use ($request) {
                $user->update([
                    'password' => Hash::make($password),
                ]);

                event(new PasswordReset($user));

                AuditLog::create([
                    'user_id' => $user->id,
                    'action' => 'update',
                    'module' => 'auth',
                    'record_id' => $user->id,
                    'new_values' => ['password_reset_completed' => true],
                    'ip_address' => $request->ip(),
                    'created_at' => now(),
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')
                ->with('success', self::PASSWORD_RESET_MESSAGE);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'The reset link is invalid or has expired. Please request a new one.',
            ]);
    }
}
