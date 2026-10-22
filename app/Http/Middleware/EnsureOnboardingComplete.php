<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces a user through required onboarding (including privacy-notice
 * acceptance) before they can reach the rest of the app. Applied as
 * `ensure.onboarding`.
 */
class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->status === 'pending_setup') {
            return redirect()->route('onboarding.password')
                ->with('info', 'Please complete your account setup before continuing.');
        }

        return $next($request);
    }
}
