<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a request from an account that is inactive or otherwise not
 * permitted to proceed. Applied to essentially every authenticated route
 * as `ensure.active`.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->status === 'inactive') {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Please contact the Discipline Office.']);
        }

        return $next($request);
    }
}
