<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUniversityServiceTracking;

use App\Http\Middleware\VerifyJwtCookieCsrf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: Mobile API Cookie CSRF Protection (App\Http\Middleware\VerifyJwtCookieCsrf)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The mobile client may authenticate with a bearer token or with the JWT
 * cookie. Only the cookie path is vulnerable to cross-site submission, so
 * only that path must present the paired CSRF header.
 */
class MobileApiCsrfProtectionTest extends TestCase
{
    private const JWT = 'header.payload.signature';

    /** Scenario 1 - Normal: a safe read request is not challenged. */
    public function test_a_safe_read_request_is_not_challenged(): void
    {
        $response = $this->runMiddleware('GET', cookies: [$this->cookieName() => self::JWT]);

        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 2 - Normal: a bearer-token request bypasses the cookie CSRF check. */
    public function test_a_bearer_token_request_bypasses_the_cookie_csrf_check(): void
    {
        $response = $this->runMiddleware(
            'POST',
            cookies: [$this->cookieName() => self::JWT],
            headers: ['HTTP_AUTHORIZATION' => 'Bearer '.self::JWT],
        );

        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 3 - Boundary: a request without the JWT cookie is not challenged. */
    public function test_a_request_without_the_jwt_cookie_is_not_challenged(): void
    {
        $response = $this->runMiddleware('POST');

        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 4 - Failure: a cookie-authenticated write without the paired header is refused. */
    public function test_a_cookie_write_without_the_paired_header_is_refused(): void
    {
        $missing = $this->runMiddleware('POST', cookies: [$this->cookieName() => self::JWT]);
        $wrong = $this->runMiddleware(
            'POST',
            cookies: [$this->cookieName() => self::JWT],
            headers: [$this->headerKey() => 'not-the-paired-token'],
        );

        $this->assertSame(419, $missing->getStatusCode());
        $this->assertSame('CSRF token mismatch.', $missing->getData()->message);
        $this->assertSame(419, $wrong->getStatusCode());
    }

    /** Scenario 5 - Normal: a cookie-authenticated write with the paired header is accepted. */
    public function test_a_cookie_write_with_the_paired_header_is_accepted(): void
    {
        $response = $this->runMiddleware(
            'POST',
            cookies: [$this->cookieName() => self::JWT],
            headers: [$this->headerKey() => $this->pairedToken(self::JWT)],
        );

        $this->assertSame('next-was-called', $response->getContent());
        $this->assertNotSame(
            $this->pairedToken(self::JWT),
            $this->pairedToken('another.jwt.value'),
            'The CSRF token must be bound to the specific JWT.'
        );
    }

    private function runMiddleware(string $method, array $cookies = [], array $headers = []): BaseResponse
    {
        $request = Request::create(
            '/api/mobile/us/assignments',
            $method,
            [],
            $cookies,
            [],
            $headers,
        );

        return (new VerifyJwtCookieCsrf)->handle(
            $request,
            fn (): Response => new Response('next-was-called'),
        );
    }

    private function cookieName(): string
    {
        return (string) config('jwt.cookie.name', 'token');
    }

    private function headerKey(): string
    {
        $header = (string) config('jwt.cookie.csrf_header', 'X-API-XSRF-TOKEN');

        return 'HTTP_'.strtoupper(str_replace('-', '_', $header));
    }

    private function pairedToken(string $jwt): string
    {
        return hash_hmac('sha256', $jwt, (string) config('app.key'));
    }
}
