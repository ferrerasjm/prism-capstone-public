<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_browser_security_headers_are_present_and_authenticated_pages_are_not_cached(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

        $this->actingAs($this->user('super_admin'))
            ->get(route('admin.users.search-suggestions', ['q' => 'nobody']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('Pragma', 'no-cache');
    }

    public function test_bound_search_input_does_not_treat_sql_injection_as_executable_sql(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $this->user('discipline_officer', 'private.record@nu-clark.edu.ph');

        $this->actingAs($admin)
            ->getJson(route('admin.users.search-suggestions', [
                'q' => "%') OR 1=1 --",
            ]))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_api_login_burst_is_rate_limited_after_five_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('api.login'), [
                'email' => 'missing@nu-clark.edu.ph',
                'password' => 'incorrect',
            ])->assertUnauthorized();
        }

        $this->postJson(route('api.login'), [
            'email' => 'missing@nu-clark.edu.ph',
            'password' => 'incorrect',
        ])->assertTooManyRequests();
    }

    public function test_health_endpoint_remains_stable_during_a_controlled_request_burst(): void
    {
        foreach (range(1, 100) as $request) {
            $this->get('/up')->assertOk();
        }
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::create([
            'last_name' => 'Security',
            'first_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email ?? $role.'@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
