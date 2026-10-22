<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Module 1 - User Management and Authentication
 * Component: Role-Based Route Authorization (App\Http\Middleware\RoleMiddleware)
 * Test Cycle No. 1 - 5 test cases.
 */
class RoleBasedRouteAuthorizationTest extends TestCase
{
    /** Scenario 1 - Failure: an unauthenticated visitor is redirected to the sign-in page. */
    public function test_an_unauthenticated_request_is_redirected_to_the_login_route(): void
    {
        $response = $this->runMiddleware(null, ['discipline_officer']);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    /** Scenario 2 - Normal: the single permitted role is allowed through. */
    public function test_a_user_holding_the_only_permitted_role_is_allowed_through(): void
    {
        $response = $this->runMiddleware($this->user('discipline_officer'), ['discipline_officer']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 3 - Normal: any role in a multi-role list is allowed through. */
    public function test_any_role_in_a_multi_role_allow_list_is_allowed_through(): void
    {
        $allowed = ['super_admin', 'discipline_coordinator', 'discipline_officer'];

        $response = $this->runMiddleware($this->user('discipline_coordinator'), $allowed);

        $this->assertSame('next-was-called', $response->getContent());
    }

    /** Scenario 4 - Failure: a role outside the allow list is refused with HTTP 403. */
    public function test_a_role_outside_the_allow_list_is_refused_with_403(): void
    {
        try {
            $this->runMiddleware($this->user('student'), ['discipline_officer', 'super_admin']);
            $this->fail('A student should not reach a Discipline Officer route.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('You do not have permission to access this page.', $exception->getMessage());
        }
    }

    /** Scenario 5 - Boundary: role matching is exact and case-sensitive. */
    public function test_role_matching_is_case_sensitive_and_exact(): void
    {
        try {
            $this->runMiddleware($this->user('Discipline_Officer'), ['discipline_officer']);
            $this->fail('A differently-cased role must not satisfy the allow list.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        try {
            $this->runMiddleware($this->user('discipline_office'), ['discipline_officer']);
            $this->fail('A partial role name must not satisfy the allow list.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function user(string $role): User
    {
        $user = new User([
            'last_name' => 'Reyes',
            'first_name' => 'Role',
            'email' => 'role.test@nu-clark.edu.ph',
            'role' => $role,
            'status' => 'active',
        ]);
        $user->id = 1;

        return $user;
    }

    private function runMiddleware(?User $user, array $allowedRoles)
    {
        if ($user) {
            $this->be($user);
        }

        return (new RoleMiddleware)->handle(
            Request::create('/officer/cases', 'GET'),
            fn (): Response => new Response('next-was-called'),
            ...$allowedRoles,
        );
    }
}
