<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiJwtCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'jwt.cookie.enabled' => true,
            'jwt.cookie.name' => 'token',
            'jwt.cookie.path' => '/api',
            'jwt.cookie.domain' => null,
            'jwt.cookie.secure' => true,
            'jwt.cookie.same_site' => 'strict',
            'jwt.cookie.csrf_cookie' => 'API-XSRF-TOKEN',
            'jwt.cookie.csrf_header' => 'X-API-XSRF-TOKEN',
            'jwt.cookie.csrf_path' => '/',
        ]);
    }

    public function test_api_login_sets_a_path_scoped_secure_http_only_jwt_cookie(): void
    {
        $user = $this->supervisor();

        $response = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user']);

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'token');

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', strtolower((string) $cookie->getSameSite()));
        $this->assertSame('/api', $cookie->getPath());
        $this->assertSame($response->json('access_token'), $cookie->getValue());

        $csrfCookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'API-XSRF-TOKEN');

        $this->assertNotNull($csrfCookie);
        $this->assertFalse($csrfCookie->isHttpOnly());
        $this->assertTrue($csrfCookie->isSecure());
        $this->assertSame('/', $csrfCookie->getPath());
    }

    public function test_api_guard_accepts_the_jwt_cookie_and_logout_expires_it(): void
    {
        config(['jwt.cookie.secure' => false]);
        $user = $this->supervisor();
        $login = $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertOk();
        $token = (string) $login->json('access_token');

        $this->app['auth']->forgetGuards();
        $this->withCredentials()
            ->withUnencryptedCookie('token', $token)
            ->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('id', $user->id);

        $this->app['auth']->forgetGuards();
        $this->withUnencryptedCookie('token', $token)
            ->postJson(route('api.logout'))
            ->assertStatus(419)
            ->assertJsonPath('message', 'CSRF token mismatch.');

        $csrfToken = hash_hmac('sha256', $token, (string) config('app.key'));
        $this->app['auth']->forgetGuards();
        $logout = $this->withUnencryptedCookie('token', $token)
            ->withHeader('X-API-XSRF-TOKEN', $csrfToken)
            ->postJson(route('api.logout'))
            ->assertOk();

        $expiredCookie = collect($logout->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'token');

        $this->assertNotNull($expiredCookie);
        $this->assertTrue($expiredCookie->getExpiresTime() < time());

        $expiredCsrfCookie = collect($logout->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === 'API-XSRF-TOKEN');

        $this->assertNotNull($expiredCsrfCookie);
        $this->assertTrue($expiredCsrfCookie->getExpiresTime() < time());
    }

    public function test_bearer_api_clients_remain_compatible_without_cookie_csrf(): void
    {
        $user = $this->supervisor();
        $token = (string) $this->postJson(route('api.login'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertOk()->json('access_token');

        $this->app['auth']->forgetGuards();

        $this->withToken($token)
            ->postJson(route('api.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Successfully logged out.');
    }

    private function supervisor(): User
    {
        return User::create([
            'last_name' => 'Supervisor',
            'first_name' => 'API',
            'email' => 'api.supervisor@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => 'us_supervisor',
            'status' => 'active',
        ]);
    }
}
