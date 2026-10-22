<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Http\Middleware\EnforceIdleSessionTimeout;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Tests\TestCase;

/**
 * Module 1 - User Management and Authentication
 * Component: Session and Account Access Guards
 *            (EnforceIdleSessionTimeout, EnsureAccountActive, EnsureOnboardingComplete)
 * Test Cycle No. 1 - 5 test cases.
 */
class SessionAndAccountGuardTest extends TestCase
{
    /** Scenario 1 - Normal: a guest request is never challenged by the idle timeout. */
    public function test_a_guest_request_passes_the_idle_timeout_untouched(): void
    {
        $response = (new EnforceIdleSessionTimeout)->handle($this->request(), $this->next());

        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 2 - Normal: activity inside the window refreshes the last-activity stamp. */
    public function test_activity_inside_the_window_refreshes_the_last_activity_stamp(): void
    {
        $this->be($this->user('active'));
        $request = $this->request();
        $request->session()->put('auth.last_activity_at', now()->timestamp - 60);

        $response = (new EnforceIdleSessionTimeout)->handle($request, $this->next());

        $this->assertSame('next-was-called', $response->getContent());
        $this->assertEqualsWithDelta(
            now()->timestamp,
            (int) $request->session()->get('auth.last_activity_at'),
            2
        );
    }

    /** Scenario 3 - Boundary: idling past the configured lifetime signs the user out. */
    public function test_idling_past_the_configured_lifetime_signs_the_user_out(): void
    {
        $this->be($this->user('active'));
        $lifetimeSeconds = (int) config('session.lifetime', 480) * 60;

        $request = $this->request();
        $request->session()->put('auth.last_activity_at', now()->timestamp - $lifetimeSeconds - 1);

        $response = (new EnforceIdleSessionTimeout)->handle($request, $this->next());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    /** Scenario 4 - Failure: a deactivated account is signed out on its next request. */
    public function test_a_deactivated_account_is_signed_out_on_its_next_request(): void
    {
        $this->be($this->user('inactive'));

        $response = (new EnsureAccountActive)->handle($this->request(), $this->next());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    /** Scenario 5 - Boundary: an account still awaiting setup is held at onboarding. */
    public function test_an_account_awaiting_setup_is_held_at_onboarding(): void
    {
        $this->be($this->user('pending_setup'));
        $held = (new EnsureOnboardingComplete)->handle($this->request(), $this->next());

        $this->be($this->user('active'));
        $allowed = (new EnsureOnboardingComplete)->handle($this->request(), $this->next());

        $this->assertInstanceOf(RedirectResponse::class, $held);
        $this->assertSame(route('onboarding.password'), $held->getTargetUrl());
        $this->assertSame('next-was-called', $allowed->getContent());
    }

    private function request(): Request
    {
        $request = Request::create('/dashboard', 'GET');
        $request->setLaravelSession(new Store('prism-test', new ArraySessionHandler(120)));

        return $request;
    }

    private function next(): callable
    {
        return fn (): Response => new Response('next-was-called');
    }

    private function user(string $status): User
    {
        $user = new User([
            'last_name' => 'Cruz',
            'first_name' => 'Guarded',
            'email' => 'guarded.user@nu-clark.edu.ph',
            'role' => 'discipline_officer',
            'status' => $status,
        ]);
        $user->id = 1;

        return $user;
    }
}
