<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The mobile app renews its access token through /api/auth/refresh whenever a
 * request comes back 401. If renewal fails, the app signs the supervisor out,
 * so a broken refresh shows up as being logged out roughly an hour after
 * signing in.
 */
class ApiJwtRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_valid_token_can_be_refreshed_and_the_new_one_works(): void
    {
        $token = $this->login();

        $refresh = $this->withToken($token)->postJson('/api/auth/refresh');

        $refresh->assertOk()->assertJsonPath('user.role', 'us_supervisor');
        $newToken = $refresh->json('access_token');
        $this->assertNotSame($token, $newToken);

        $this->resetGuards();
        $this->withToken($newToken)->getJson('/api/auth/me')->assertOk();
    }

    public function test_an_expired_token_can_still_be_refreshed_within_the_refresh_window(): void
    {
        $token = $this->login();
        Carbon::setTestNow(now()->addMinutes(90));

        $this->resetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();

        $this->resetGuards();
        $newToken = $this->withToken($token)->postJson('/api/auth/refresh')
            ->assertOk()
            ->json('access_token');

        $this->resetGuards();
        $this->withToken($newToken)->getJson('/api/auth/me')->assertOk();
    }

    public function test_refresh_still_rejects_a_supervisor_deactivated_since_login(): void
    {
        $token = $this->login();
        User::where('email', 'refresh-sup@nu-clark.edu.ph')->update(['status' => 'inactive']);

        $this->resetGuards();
        $this->withToken($token)->postJson('/api/auth/refresh')->assertForbidden();
    }

    private function login(): string
    {
        User::create([
            'first_name' => 'Refresh', 'last_name' => 'Supervisor',
            'email' => 'refresh-sup@nu-clark.edu.ph',
            'institutional_id' => 'US-REFRESH-1',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor', 'office_name' => 'Library', 'status' => 'active',
        ]);

        return $this->postJson('/api/auth/login', [
            'email' => 'refresh-sup@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->json('access_token');
    }

    private function resetGuards(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('tymon.jwt');
        $this->app->forgetInstance('tymon.jwt.auth');
    }
}
