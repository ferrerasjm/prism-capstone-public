<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->get('/_session-lifetime-probe', fn () => response()->json([
            'authenticated' => true,
        ]));
    }

    public function test_the_original_120_minute_idle_timeout_logs_out_an_inactive_session(): void
    {
        config(['session.lifetime' => 120]);
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['auth.last_activity_at' => now()->subMinutes(121)->timestamp])
            ->get('/_session-lifetime-probe')
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning', 'Your session expired due to inactivity. Please sign in again.');

        $this->assertGuest();
    }

    public function test_active_navigation_refreshes_the_server_side_idle_timeout(): void
    {
        config(['session.lifetime' => 120]);
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['auth.last_activity_at' => now()->subMinutes(100)->timestamp])
            ->get('/_session-lifetime-probe')
            ->assertOk();

        $refreshedAt = (int) session('auth.last_activity_at');
        $this->assertGreaterThan(now()->subMinute()->timestamp, $refreshedAt);

        $this->travel(100)->minutes();

        $this->get('/_session-lifetime-probe')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_session_is_now_configured_for_480_minutes_with_a_five_minute_warning(): void
    {
        $this->assertSame(480, (int) config('session.lifetime'));
        $this->assertSame(300, (int) config('session.warning'));
        $user = $this->user();

        $this->actingAs($user)
            ->withSession(['auth.last_activity_at' => now()->subMinutes(479)->timestamp])
            ->get('/_session-lifetime-probe')
            ->assertOk();

        $this->withSession(['auth.last_activity_at' => now()->subMinutes(481)->timestamp])
            ->get('/_session-lifetime-probe')
            ->assertRedirect(route('login'));
    }

    public function test_keep_alive_endpoint_refreshes_only_an_authenticated_session(): void
    {
        $this->postJson(route('session.keep-alive'))->assertUnauthorized();

        $this->actingAs($this->user())
            ->postJson(route('session.keep-alive'))
            ->assertOk()
            ->assertJson([
                'expires_in' => 480 * 60,
                'warning_in' => 300,
            ]);

        $this->assertNotNull(session('auth.last_activity_at'));
    }

    public function test_authenticated_layout_includes_the_inactivity_warning(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Session expiring soon')
            ->assertSee(route('session.keep-alive'))
            ->assertSee('data-lifetime-seconds="28800"', false)
            ->assertSee('data-warning-seconds="300"', false);
    }

    private function user(): User
    {
        return User::create([
            'last_name' => 'Session',
            'first_name' => 'Tester',
            'email' => 'session.tester@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }
}
