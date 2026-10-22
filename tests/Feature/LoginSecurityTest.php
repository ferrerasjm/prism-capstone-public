<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use App\Services\RecaptchaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_recaptcha_when_enabled(): void
    {
        $this->enableRecaptcha();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('recaptchaContainer')
            ->assertSee('auth-brand-panel', false)
            ->assertSee('auth-brand-row', false)
            ->assertSee('assets/vector1_left.png', false)
            ->assertSee('assets/vector2_right.png', false)
            ->assertSee('test-site-key')
            ->assertSee('expired-callback')
            ->assertSee('Robot verification expired. Please complete it again.')
            ->assertSee('Signing in...');
    }

    public function test_login_requires_and_server_verifies_recaptcha(): void
    {
        $this->enableRecaptcha();
        Notification::fake();
        $user = $this->user();
        Http::fakeSequence()
            ->push(['success' => false, 'hostname' => 'localhost'])
            ->push(['success' => true, 'hostname' => 'localhost']);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
            'g-recaptcha-response' => 'invalid-token',
        ])->assertSessionHasErrors('g-recaptcha-response');
        $this->assertGuest();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
            'g-recaptcha-response' => 'valid-token',
        ])->assertRedirect(route('login'));
        $this->assertGuest();

        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));
        $this->assertGuest();
        Notification::assertSentTo($user, LoginOtpNotification::class);
    }

    public function test_login_accepts_a_valid_recaptcha_token_longer_than_2048_characters(): void
    {
        $this->enableRecaptcha();
        Notification::fake();
        $user = $this->user();
        $token = str_repeat('a', 4096);
        Http::fake([
            '*' => Http::response(['success' => true, 'hostname' => 'localhost']),
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
            'g-recaptcha-response' => $token,
        ])->assertRedirect(route('login'));

        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        Http::assertSent(
            fn (HttpRequest $request): bool => $request['response'] === $token
        );
    }

    public function test_login_accepts_google_verified_local_hosts_when_hostname_check_is_unset(): void
    {
        $this->enableRecaptcha();
        Notification::fake();
        config(['services.recaptcha.hostname' => '']);
        $user = $this->user();
        Http::fake([
            '*' => Http::response(['success' => true, 'hostname' => '127.0.0.1']),
        ]);

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
            'g-recaptcha-response' => 'valid-local-token',
        ])->assertRedirect(route('login'));

        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
    }

    public function test_local_login_can_fail_open_when_google_is_unreachable_and_fallback_is_enabled(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->enableRecaptcha();
        config(['services.recaptcha.fail_open_on_connection_error' => true]);
        Http::fake(fn () => throw new ConnectionException('Google is unreachable.'));

        $this->assertTrue(
            app(RecaptchaService::class)->verify('browser-generated-token', '127.0.0.1')
        );
    }

    public function test_recaptcha_connection_failure_remains_fail_closed_outside_local_environment(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->enableRecaptcha();
        config(['services.recaptcha.fail_open_on_connection_error' => true]);
        Http::fake(fn () => throw new ConnectionException('Google is unreachable.'));

        $this->assertFalse(
            app(RecaptchaService::class)->verify('browser-generated-token', '127.0.0.1')
        );
    }

    public function test_fifth_failed_login_starts_a_full_sixty_second_cooldown(): void
    {
        config(['services.recaptcha.enabled' => false]);
        Notification::fake();
        $user = $this->user();

        foreach (range(1, 4) as $attempt) {
            $this->from(route('login'))
                ->post(route('login.post'), [
                    'email' => $user->email,
                    'password' => 'incorrect',
                ])
                ->assertSessionHasErrors('email');
            $this->get(route('login'))->assertOk();
            $this->travel(8)->seconds();
        }

        $this->from(route('login'))
            ->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'incorrect',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Too many login attempts. Please wait 60 seconds before trying again.');

        config(['services.recaptcha.enabled' => true]);
        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'))
            ->assertSessionDoesntHaveErrors('g-recaptcha-response')
            ->assertSessionHas('error', 'Too many login attempts. Please wait 60 seconds before trying again.');
        config(['services.recaptcha.enabled' => false]);
        $this->assertGuest();
        Notification::assertNothingSent();

        $this->travel(30)->seconds();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Too many login attempts. Please wait 30 seconds before trying again.');
        $this->assertGuest();

        $this->travel(31)->seconds();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));
        Notification::assertSentTo($user, LoginOtpNotification::class);
    }

    public function test_login_cooldown_notice_uses_a_toast_instead_of_a_field_error(): void
    {
        config(['services.recaptcha.enabled' => false]);
        $user = $this->user();

        foreach (range(1, 4) as $attempt) {
            $this->post(route('login.post'), [
                'email' => $user->email,
                'password' => 'incorrect',
            ])->assertSessionHasErrors('email');
            $this->get(route('login'))->assertOk();
        }

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'incorrect',
        ])->assertSessionDoesntHaveErrors('email')
            ->assertSessionHas('error');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="auth-login-page"', false)
            ->assertSee('prism-toast-danger')
            ->assertSee('Too many login attempts. Please wait 60 seconds before trying again.')
            ->assertDontSee('Too many login attempts. Please wait 60 seconds before trying again.</span>', false);
    }

    public function test_login_validation_errors_are_rendered_as_toasts(): void
    {
        config(['services.recaptcha.enabled' => false]);

        $this->get(route('login'))->assertOk();

        // validate() calls back(), which needs a referer to resolve to - what a real
        // browser submit sends, and what a bare post() here would be missing.
        $this->from(route('login'))
            ->post(route('login.post'), [])
            ->assertRedirect(route('login'));

        // Rendered through window.PrismToast (public/js/prism-toast.js), not a
        // login-specific toast implementation - see ToastConsistencyTest for that contract.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('window.PrismToast', false)
            ->assertSee('The email field is required.')
            ->assertSee('The password field is required.')
            ->assertDontSee('id="emailError"', false)
            ->assertDontSee('id="passwordError"', false);
    }

    public function test_login_shows_the_privacy_notice_before_the_otp_is_sent(): void
    {
        config([
            'services.recaptcha.enabled' => false,
            'auth.login_otp.console' => true,
        ]);
        Notification::fake();
        $user = $this->user();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        Notification::assertNothingSent();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('id="privacyModalBackdrop"', false)
            ->assertDontSee('privacy-modal-backdrop d-none', false);

        $otp = null;
        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->privacy_accepted_at);

        Notification::assertSentTo($user, LoginOtpNotification::class, function ($notification) use ($user, &$otp): bool {
            $otp = $notification->toMail($user)->viewData['otp'];

            return is_string($otp) && preg_match('/^\d{6}$/', $otp) === 1;
        });

        $this->get(route('login.otp.show'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('auth-brand-panel', false)
            ->assertSee('auth-brand-row', false)
            ->assertSee('assets/vector1_left.png', false)
            ->assertSee('assets/vector2_right.png', false)
            ->assertSee('prism-toast-info')
            ->assertDontSee('alert-success')
            ->assertSee('Development OTP:')
            ->assertSee($otp)
            ->assertSee("console.info('PRISM development login OTP:'", false);

        $this->post(route('login.otp.verify'), ['otp' => $otp])
            ->assertRedirect(route('splash'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login',
            'module' => 'auth',
        ]);
    }

    public function test_privacy_notice_is_shown_again_even_if_previously_accepted(): void
    {
        config(['services.recaptcha.enabled' => false, 'auth.login_otp.mail_enabled' => false]);
        $user = $this->user();
        $user->privacy_accepted_at = now()->subDay();
        $user->save();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->assertNull(session('auth.login_otp'));

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('privacy-modal-backdrop d-none', false);
    }

    public function test_login_otp_is_not_printed_to_the_browser_console_when_disabled(): void
    {
        config([
            'services.recaptcha.enabled' => false,
            'auth.login_otp.console' => false,
        ]);
        Notification::fake();
        $user = $this->user();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ]);
        $this->acceptPendingPrivacy();

        $this->get(route('login.otp.show'))
            ->assertOk()
            ->assertDontSee('PRISM development login OTP:');
    }

    public function test_sixth_incorrect_otp_attempt_enters_a_one_minute_cooldown(): void
    {
        config(['services.recaptcha.enabled' => false]);
        Notification::fake();
        $user = $this->user();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ])->assertRedirect(route('login'));

        $this->acceptPendingPrivacy()->assertRedirect(route('login.otp.show'));

        foreach (range(1, 5) as $attempt) {
            $this->post(route('login.otp.verify'), ['otp' => '000000'])
                ->assertSessionHas('error', 'The verification code is incorrect.');
            $this->get(route('login.otp.show'))->assertOk();
        }

        $this->post(route('login.otp.verify'), ['otp' => '000000'])
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionHas('error', 'Too many verification attempts. Please wait a minute before trying again.');

        $this->assertGuest();
    }

    public function test_incorrect_login_otp_uses_the_shared_toast_without_an_inline_error(): void
    {
        config(['services.recaptcha.enabled' => false]);
        Notification::fake();
        $user = $this->user();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ]);
        $this->acceptPendingPrivacy();

        $this->post(route('login.otp.verify'), ['otp' => '000000'])
            ->assertRedirect()
            ->assertSessionHas('error', 'The verification code is incorrect.');

        $this->get(route('login.otp.show'))
            ->assertOk()
            ->assertSee('prism-toast-danger')
            ->assertSee('The verification code is incorrect.')
            ->assertDontSee('field-error d-block mb-3');
    }

    public function test_login_otp_email_uses_the_prism_security_email_design(): void
    {
        $user = $this->user();
        $notification = new LoginOtpNotification('123456', 10);
        $mail = $notification->toMail($user);

        $this->assertSame('Your NU Clark PRISM login verification code', $mail->subject);
        $this->assertSame('emails.login-otp', $mail->view['html']);
        $this->assertSame('emails.login-otp-text', $mail->view['text']);
        $this->assertSame('123456', $mail->viewData['otp']);
        $this->assertSame(10, $mail->viewData['expiryMinutes']);
    }

    public function test_login_otp_resend_obeys_the_cooldown_and_sends_a_replacement(): void
    {
        config([
            'services.recaptcha.enabled' => false,
            'auth.login_otp.resend_cooldown' => 60,
        ]);
        Notification::fake();
        $user = $this->user();

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ]);
        $this->acceptPendingPrivacy();

        $this->post(route('login.otp.resend'))
            ->assertSessionHas('error');
        Notification::assertSentToTimes($user, LoginOtpNotification::class, 1);

        $this->travel(61)->seconds();

        $this->post(route('login.otp.resend'))
            ->assertRedirect(route('login.otp.show'))
            ->assertSessionHas('info', 'A new verification code has been sent.');
        Notification::assertSentToTimes($user, LoginOtpNotification::class, 2);
    }

    public function test_expired_login_otp_cannot_authenticate_the_user(): void
    {
        config([
            'services.recaptcha.enabled' => false,
            'auth.login_otp.expire' => 10,
        ]);
        Notification::fake();
        $user = $this->user();
        $otp = null;

        $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'Password123',
        ]);
        $this->acceptPendingPrivacy();

        Notification::assertSentTo($user, LoginOtpNotification::class, function ($notification) use ($user, &$otp): bool {
            $otp = $notification->toMail($user)->viewData['otp'];

            return true;
        });

        $this->travel(11)->minutes();

        $this->post(route('login.otp.verify'), ['otp' => $otp])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function acceptPendingPrivacy(): TestResponse
    {
        return $this->post(route('privacy.accept'), ['accepted' => '1']);
    }

    private function enableRecaptcha(): void
    {
        config([
            'services.recaptcha.enabled' => true,
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.hostname' => 'localhost',
        ]);
    }

    private function user(): User
    {
        return User::create([
            'last_name' => 'Officer',
            'first_name' => 'Login',
            'email' => 'login.officer@nu-clark.edu.ph',
            'password' => Hash::make('Password123'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }
}
