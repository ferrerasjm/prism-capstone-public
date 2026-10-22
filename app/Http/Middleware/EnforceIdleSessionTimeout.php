<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a session that has been idle beyond the configured limit. Paired
 * with a client-side warning shown SESSION_WARNING_SECONDS before the
 * cutoff.
 */
class EnforceIdleSessionTimeout
{
    private const LAST_ACTIVITY_KEY = 'auth.last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivityAt = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, $now);
        $lifetimeSeconds = (int) config('session.lifetime', 480) * 60;

        if ($now - $lastActivityAt > $lifetimeSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your session expired due to inactivity. Please sign in again.',
                ], 401);
            }

            return redirect()->route('login')
                ->with('warning', 'Your session expired due to inactivity. Please sign in again.');
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }
}
