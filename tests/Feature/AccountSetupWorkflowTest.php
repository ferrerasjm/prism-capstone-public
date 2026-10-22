<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AccountSetupToken;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use App\Services\AccountSetupService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSetupWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_use_a_setup_link_once_to_create_their_password(): void
    {
        Notification::fake();
        $user = $this->pendingUser('discipline_officer');
        $token = $this->issueAndCaptureToken($user);

        $this->get(route('account-setup.show', $token))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('Create your password');

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->post(route('account-setup.complete'), [
            'token' => $token,
            'password' => 'SecureSetup2026',
            'password_confirmation' => 'SecureSetup2026',
        ])->assertRedirect(route('officer.dashboard'));

        $user->refresh();
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('SecureSetup2026', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->accountSetupToken?->consumed_at);

        Auth::logout();
        $this->get(route('account-setup.show', $token))->assertGone();
    }

    public function test_student_setup_link_continues_to_profile_and_keeps_account_pending_until_acknowledgment(): void
    {
        Notification::fake();
        $student = $this->pendingUser('student');
        $student->studentProfile()->create(['student_number' => '2026-00456']);
        $token = $this->issueAndCaptureToken($student);

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->post(route('account-setup.complete'), [
            'token' => $token,
            'password' => 'StudentSecure2026',
            'password_confirmation' => 'StudentSecure2026',
        ])->assertRedirect(route('onboarding.profile'));

        $student->refresh();
        $this->assertSame('pending_setup', $student->status);
        $this->assertTrue(Hash::check('StudentSecure2026', $student->password));
        $this->assertAuthenticatedAs($student);
    }

    public function test_resending_a_setup_link_invalidates_the_previous_link(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $user = $this->pendingUser('discipline_officer');
        $oldToken = $this->issueAndCaptureToken($user);
        $oldHash = $user->accountSetupToken->token_hash;

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->actingAs($admin)
            ->post(route('admin.users.account-setup-link.resend', $user))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSame($oldHash, $user->accountSetupToken()->firstOrFail()->token_hash);
        Auth::logout();
        $this->get(route('account-setup.show', $oldToken))->assertGone();
        Notification::assertSentToTimes($user, AccountSetupLinkNotification::class, 2);
    }

    public function test_reactivating_an_unfinished_setup_account_restores_pending_setup(): void
    {
        $admin = $this->admin();
        $user = $this->pendingUser('discipline_officer');

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), [
                'status' => 'inactive',
                'reason' => 'The staff account must be temporarily disabled before onboarding.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('inactive', $user->fresh()->status);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), ['status' => 'active'])
            ->assertRedirect()
            ->assertSessionHas('success', "{$user->full_name}'s account has been returned to pending setup.");

        $this->assertSame('pending_setup', $user->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'module' => 'users',
            'record_id' => $user->id,
            'new_values->status' => 'pending_setup',
        ]);
    }

    public function test_reactivating_an_active_account_restores_normal_login_access(): void
    {
        config([
            'services.recaptcha.enabled' => false,
            'auth.login_otp.mail_enabled' => false,
        ]);

        $admin = $this->admin();
        $user = User::create([
            'last_name' => 'Ready',
            'first_name' => 'Login',
            'institutional_id' => 'NU-EMP-00999',
            'email' => 'ready.login@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), [
                'status' => 'inactive',
                'reason' => 'Temporary suspension.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        Auth::logout();
        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), ['status' => 'active'])
            ->assertRedirect()
            ->assertSessionHas('success', "{$user->full_name}'s account has been activated.");

        Auth::logout();
        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertNotNull(session('auth.pending_privacy'));
    }

    public function test_reactivating_an_unfinished_legacy_account_restores_pending_setup(): void
    {
        $admin = $this->admin();
        $user = $this->pendingUser('discipline_officer');
        AccountSetupToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'issued_by' => $admin->id,
            'expires_at' => now()->addDay(),
            'consumed_at' => null,
        ]);

        $user->update(['status' => 'active']);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), [
                'status' => 'inactive',
                'reason' => 'Temporarily disabled before setup was completed.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('inactive', $user->fresh()->status);
        $this->assertSame('pending_setup', $user->fresh()->status_before_deactivation);

        $this->actingAs($admin)
            ->patch(route('admin.users.toggle-status', $user), ['status' => 'active'])
            ->assertRedirect()
            ->assertSessionHas('success', "{$user->full_name}'s account has been returned to pending setup.");

        $this->assertSame('pending_setup', $user->fresh()->status);
    }

    public function test_legacy_active_account_with_unfinished_setup_cannot_use_normal_login(): void
    {
        config(['services.recaptcha.enabled' => false]);

        $admin = $this->admin();
        $user = User::create([
            'last_name' => 'Legacy',
            'first_name' => 'Setup',
            'institutional_id' => 'NU-EMP-00888',
            'email' => 'legacy.setup@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        AccountSetupToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', str_repeat('b', 64)),
            'issued_by' => $admin->id,
            'expires_at' => now()->addDay(),
            'consumed_at' => null,
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertSessionHasErrors([
            'email' => 'This account is still awaiting setup. Use the account setup link or ask the system administrator to resend it.',
        ]);

        $this->assertGuest();
    }

    public function test_password_cannot_match_the_users_institutional_identifier(): void
    {
        Notification::fake();
        $student = $this->pendingUser('student');
        $student->studentProfile()->create(['student_number' => '2026-00456']);
        $token = $this->issueAndCaptureToken($student);

        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->post(route('account-setup.complete'), [
            'token' => $token,
            'password' => '2026-00456',
            'password_confirmation' => '2026-00456',
        ])->assertSessionHasErrors('password');
    }

    private function issueAndCaptureToken(User $user): string
    {
        app(AccountSetupService::class)->issue($user);
        $token = '';

        Notification::assertSentTo(
            $user,
            AccountSetupLinkNotification::class,
            function (AccountSetupLinkNotification $notification) use ($user, &$token): bool {
                $setupUrl = (string) $notification->toMail($user)->viewData['setupUrl'];
                $token = basename((string) parse_url($setupUrl, PHP_URL_PATH));

                return $token !== '';
            },
        );

        return $token;
    }

    private function pendingUser(string $role): User
    {
        return User::create([
            'last_name' => 'Pending',
            'first_name' => 'Account',
            'institutional_id' => $role === 'student' ? '2026-00456' : 'NU-EMP-00456',
            'email' => $role === 'student'
                ? 'pending@students.nu-clark.edu.ph'
                : 'pending@nu-clark.edu.ph',
            'password' => Hash::make('unshared-placeholder'),
            'role' => $role,
            'status' => 'pending_setup',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'last_name' => 'Admin',
            'first_name' => 'Super',
            'email' => 'admin.setup@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
