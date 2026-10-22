<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MobileOtpChallenge;
use App\Models\User;
use App\Notifications\PasswordChangeOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_password_form_requires_current_password_for_every_role(): void
    {
        $roles = [
            'super_admin',
            'discipline_coordinator',
            'discipline_officer',
            'reporting_user',
            'guidance_officer',
            'us_supervisor',
            'student',
        ];

        foreach ($roles as $role) {
            $this->actingAs($this->user($role))
                ->get(route('account.password'))
                ->assertOk()
                ->assertSee('Current Password')
                ->assertSee('name="current_password"', false);
        }
    }

    public function test_incorrect_current_password_prevents_password_change(): void
    {
        $user = $this->user('discipline_officer');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('CurrentPassword123', $user->fresh()->password));
        $this->assertSame(0, AuditLog::where('module', 'auth')->count());
    }

    public function test_correct_current_password_sends_an_otp_instead_of_changing_the_password_immediately(): void
    {
        Notification::fake();
        $user = $this->user('student');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ])
            ->assertRedirect(route('account.password'));

        $this->assertTrue(Hash::check('CurrentPassword123', $user->fresh()->password));
        $this->assertSame(0, AuditLog::where('module', 'auth')->count());
        Notification::assertSentTo($user, PasswordChangeOtpNotification::class);
    }

    public function test_entering_the_correct_otp_applies_the_password_change_for_every_role(): void
    {
        $roles = [
            'super_admin',
            'discipline_coordinator',
            'discipline_officer',
            'reporting_user',
            'guidance_officer',
            'us_supervisor',
            'student',
        ];

        foreach ($roles as $role) {
            Notification::fake();
            $user = $this->user($role);
            $otp = null;

            $this->actingAs($user)
                ->post(route('account.password.update'), [
                    'current_password' => 'CurrentPassword123',
                    'password' => 'NewPassword123',
                    'password_confirmation' => 'NewPassword123',
                ]);

            Notification::assertSentTo($user, PasswordChangeOtpNotification::class, function ($notification) use ($user, &$otp): bool {
                $otp = $notification->toMail($user)->viewData['otp'];

                return is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1;
            });

            $this->post(route('account.password.otp.verify'), ['otp' => $otp])
                ->assertRedirect(route('dashboard.profile'));

            $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $user->id,
                'module' => 'auth',
                'action' => 'update',
            ]);
        }
    }

    public function test_incorrect_otp_does_not_change_the_password(): void
    {
        Notification::fake();
        $user = $this->user('discipline_officer');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $this->post(route('account.password.otp.verify'), ['otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('CurrentPassword123', $user->fresh()->password));
        $this->assertSame(0, AuditLog::where('module', 'auth')->count());
    }

    public function test_expired_otp_does_not_change_the_password(): void
    {
        Notification::fake();
        $user = $this->user('discipline_officer');
        $otp = null;

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        Notification::assertSentTo($user, PasswordChangeOtpNotification::class, function ($notification) use ($user, &$otp): bool {
            $otp = $notification->toMail($user)->viewData['otp'];

            return true;
        });

        MobileOtpChallenge::query()->where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->post(route('account.password.otp.verify'), ['otp' => $otp])
            ->assertRedirect(route('account.password'))
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('CurrentPassword123', $user->fresh()->password));
    }

    public function test_sixth_incorrect_otp_attempt_locks_the_challenge(): void
    {
        Notification::fake();
        $user = $this->user('discipline_officer');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        foreach (range(1, 5) as $attempt) {
            $this->post(route('account.password.otp.verify'), ['otp' => '000000']);
        }

        $this->post(route('account.password.otp.verify'), ['otp' => '000000'])
            ->assertRedirect(route('account.password'))
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('CurrentPassword123', $user->fresh()->password));
    }

    public function test_cancelling_the_otp_step_returns_to_the_password_form(): void
    {
        Notification::fake();
        $user = $this->user('discipline_officer');

        $this->actingAs($user)
            ->post(route('account.password.update'), [
                'current_password' => 'CurrentPassword123',
                'password' => 'NewPassword123',
                'password_confirmation' => 'NewPassword123',
            ]);

        $this->post(route('account.password.otp.cancel'))
            ->assertRedirect(route('account.password'));

        $this->get(route('account.password'))
            ->assertOk()
            ->assertSee('name="current_password"', false);
    }

    private function user(string $role): User
    {
        return User::create([
            'first_name' => 'Password',
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $role.'-'.uniqid().'-password@example.test',
            'password' => Hash::make('CurrentPassword123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
