<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSRF protection for the cookie-based JWT flow used by the mobile API:
 * requires the paired CSRF cookie and header to match, since a
 * cookie-borne token alone would otherwise be replayable cross-site.
 */
class VerifyJwtCookieCsrf
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = (string) $request->headers->get('Authorization', '');

        if ($request->isMethodSafe()
            || str_starts_with(strtolower($authorization), 'bearer ')
            || ! $request->hasCookie((string) config('jwt.cookie.name', 'token'))) {
            return $next($request);
        }

        $jwt = (string) $request->cookie((string) config('jwt.cookie.name', 'token'));
        $providedToken = (string) $request->header(
            (string) config('jwt.cookie.csrf_header', 'X-API-XSRF-TOKEN')
        );

        if ($providedToken === '' || ! hash_equals($this->csrfToken($jwt), $providedToken)) {
            return new JsonResponse([
                'message' => 'CSRF token mismatch.',
            ], 419);
        }

        return $next($request);
    }

    private function csrfToken(string $jwt): string
    {
        return hash_hmac('sha256', $jwt, (string) config('app.key'));
    }
}
