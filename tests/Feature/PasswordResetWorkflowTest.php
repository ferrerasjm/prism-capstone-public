<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordResetWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const RESET_LINK_MESSAGE = 'If the email address exists in PRISM, a password reset link has been sent.';

    public function test_forgot_password_page_uses_the_mobile_auth_brand_and_background_elements(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('auth-brand-header', false)
            ->assertSee('auth-brand-row', false)
            ->assertSee('assets/vector1_left.png', false)
            ->assertSee('assets/vector2_right.png', false);
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        $user = $this->user('student', 'student@students.nu-clark.edu.ph');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success', self::RESET_LINK_MESSAGE);

        Notification::assertSentTo(
            $user,
            PasswordResetNotification::class,
            function (PasswordResetNotification $notification) use ($user): bool {
                $mail = $notification->toMail($user);
                $html = (string) $mail->render();

                return $mail->subject === 'Reset your NU Clark PRISM password'
                    && str_starts_with($mail->viewData['resetUrl'], config('app.public_url').'/reset-password/')
                    && $mail->viewData['expiryMinutes'] === 60
                    && str_contains($html, 'NU Clark')
                    && str_contains($html, 'Student Discipline Office')
                    && str_contains($html, 'Reset your PRISM password')
                    && str_contains($html, 'What happens next')
                    && str_contains($html, 'Security notice')
                    && str_contains($html, $user->email);
            },
        );
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'update',
        ]);
    }

    public function test_reset_link_request_does_not_reveal_unknown_emails(): void
    {
        Notification::fake();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('password.email'), ['email' => 'unknown@nu-clark.edu.ph'])
            ->assertRedirect()
            ->assertSessionHas('success', self::RESET_LINK_MESSAGE);

        Notification::assertNothingSent();
    }

    public function test_pending_setup_account_gets_its_setup_link_reissued_instead_of_a_password_reset(): void
    {
        Notification::fake();
        $user = $this->user('student', 'pending@students.nu-clark.edu.ph');
        $user->update(['status' => 'pending_setup']);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas(
                'success',
                'If the email address exists in PRISM, a password reset link has been sent.',
            );

        Notification::assertSentTo($user, AccountSetupLinkNotification::class);
        Notification::assertNotSentTo($user, PasswordResetNotification::class);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
        ]);
    }

    public function test_mail_transport_failure_shows_a_safe_error_message(): void
    {
        $user = $this->user('student', 'student@students.nu-clark.edu.ph');
        $this->withoutMiddleware(PreventRequestForgery::class);

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $user->email])
            ->andThrow(new TransportException('SMTP credentials rejected for secret@example.com.'));

        $response = $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasInput('email', $user->email)
            ->assertSessionHasErrors([
                'email' => 'We could not send the password reset email right now. Please try again later or contact the system administrator.',
            ])
            ->assertSessionMissing('success');

        $errorMessage = $response->getSession()->get('errors')->first('email');

        $this->assertStringNotContainsString('SMTP credentials rejected', $errorMessage);
        $this->assertStringNotContainsString('secret@example.com', $errorMessage);

        $this->assertDatabaseMissing('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
        ]);
    }

    public function test_user_can_reset_password_with_a_valid_token(): void
    {
        $user = $this->user('student', 'student@students.nu-clark.edu.ph');
        $token = Password::createToken($user);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been reset. You may now sign in.');

        $this->assertTrue(Hash::check('NewPassword123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'module' => 'auth',
            'action' => 'update',
        ]);
    }

    public function test_invalid_reset_token_does_not_change_password(): void
    {
        $user = $this->user('student', 'student@students.nu-clark.edu.ph');
        $oldPassword = $user->password;
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_reset_form_can_be_opened_from_link(): void
    {
        $user = $this->user('student', 'student@students.nu-clark.edu.ph');

        $this->get(route('password.reset', [
            'token' => 'sample-token',
            'email' => $user->email,
        ]))
            ->assertOk()
            ->assertSee('Create a new password')
            ->assertSee($user->email);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => 'User',
            'first_name' => 'Password',
            'email' => $email,
            'password' => Hash::make('OldPassword123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
