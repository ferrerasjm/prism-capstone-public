<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnforceIdleSessionTimeout;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\VerifyJwtCookieCsrf;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cloudflare Tunnel / reverse proxies terminate TLS; trust forwarded headers.
        $middleware->trustProxies(at: '*');

        $middleware->append(AddSecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'ensure.active' => EnsureAccountActive::class,
            'ensure.onboarding' => EnsureOnboardingComplete::class,
            'jwt.cookie.csrf' => VerifyJwtCookieCsrf::class,
        ]);

        $middleware->appendToGroup('web', EnsureAccountActive::class);
        $middleware->appendToGroup('web', EnforceIdleSessionTimeout::class);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Too many attempts. Please wait a minute and try again.',
                ], 429);
            }

            $isPasswordReset = $request->is('forgot-password') || $request->is('reset-password');
            $isAccountSetup = $request->is('account-setup', 'account-setup/*');
            $isLoginOtp = $request->is('login/verify', 'login/verify/*');
            $isPasswordOtp = $request->is('account/password/otp/*');

            if ($isAccountSetup) {
                return back()->withErrors([
                    'password' => 'Too many account setup attempts. Please wait a minute and try again.',
                ]);
            }

            if ($isLoginOtp) {
                return redirect()->route('login.otp.show')
                    ->with('error', 'Too many verification attempts. Please wait a minute before trying again.');
            }

            if ($isPasswordOtp) {
                return redirect()->route('account.password')
                    ->withErrors(['otp' => 'Too many attempts. Please wait a minute and try again.']);
            }

            return redirect()
                ->route($isPasswordReset ? 'password.request' : 'login')
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => $isPasswordReset
                        ? 'Too many password reset attempts. Please wait a minute and try again.'
                        : 'Too many login attempts. Please wait a minute before trying again.',
                ]);
        });
    })->create();
