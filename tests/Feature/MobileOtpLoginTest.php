<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MobileOtpChallenge;
use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileOtpLoginTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupervisor(): User
    {
        return User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Juan Paolo',
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-001',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);
    }

    public function test_login_without_otp_flag_returns_token_directly(): void
    {
        config(['auth.mobile_otp.enabled' => false]);
        $this->makeSupervisor();

        $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_login_with_otp_enabled_requires_verification(): void
    {
        Notification::fake();
        config(['auth.mobile_otp.enabled' => true]);
        $supervisor = $this->makeSupervisor();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ]);

        $login->assertOk()
            ->assertJsonPath('otp_required', true)
            ->assertJsonStructure(['otp_token'])
            ->assertJsonMissing(['access_token']);

        $challenge = MobileOtpChallenge::firstOrFail();
        $this->assertSame($login->json('otp_token'), $challenge->otp_token);
        $this->assertSame($supervisor->id, $challenge->user_id);

        Notification::assertSentTo($supervisor, LoginOtpNotification::class);
    }

    public function test_debug_otp_is_revealed_only_when_mail_disabled(): void
    {
        config(['auth.mobile_otp.enabled' => true, 'auth.mobile_otp.mail_enabled' => false]);
        $this->makeSupervisor();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->assertOk();

        $debugOtp = $login->json('debug_otp');
        $this->assertNotNull($debugOtp);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $debugOtp);

        $challenge = MobileOtpChallenge::where('otp_token', $login->json('otp_token'))->firstOrFail();
        $this->assertSame($challenge->code_hash, hash_hmac('sha256', $debugOtp, (string) config('app.key')));

        Notification::fake();
        config(['auth.mobile_otp.mail_enabled' => true]);
        $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])
            ->assertOk()
            ->assertJsonMissing(['debug_otp']);
    }

    public function test_full_otp_verify_flow_returns_token(): void
    {
        config(['auth.mobile_otp.enabled' => true, 'auth.mobile_otp.mail_enabled' => false]);
        $this->makeSupervisor();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->assertOk();

        $otpToken = $login->json('otp_token');
        $challenge = MobileOtpChallenge::where('otp_token', $otpToken)->firstOrFail();

        // Brute-force the 6-digit code against the stored hash since the
        // controller never exposes it in a response, matching production.
        $code = null;
        for ($i = 0; $i <= 999999; $i++) {
            $candidate = str_pad((string) $i, 6, '0', STR_PAD_LEFT);
            if (hash_equals($challenge->code_hash, hash_hmac('sha256', $candidate, (string) config('app.key')))) {
                $code = $candidate;
                break;
            }
        }
        $this->assertNotNull($code);

        $this->postJson('/api/auth/otp/verify', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'otp_token' => $otpToken,
            'code' => $code,
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'user']);

        $this->postJson('/api/auth/otp/verify', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'otp_token' => $otpToken,
            'code' => $code,
        ])->assertStatus(422);
    }

    public function test_wrong_code_locks_after_five_attempts(): void
    {
        config(['auth.mobile_otp.enabled' => true, 'auth.mobile_otp.mail_enabled' => false]);
        $this->makeSupervisor();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->assertOk();

        $otpToken = $login->json('otp_token');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/otp/verify', [
                'email' => 'demo-user-006@nu-clark.edu.ph',
                'otp_token' => $otpToken,
                'code' => '000000',
            ])->assertStatus(422);
        }

        $this->postJson('/api/auth/otp/verify', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'otp_token' => $otpToken,
            'code' => '000000',
        ])->assertStatus(429);
    }

    public function test_resend_generates_new_code_under_same_token(): void
    {
        config([
            'auth.mobile_otp.enabled' => true,
            'auth.mobile_otp.mail_enabled' => false,
            'auth.mobile_otp.resend_cooldown' => 0,
        ]);
        $this->makeSupervisor();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ])->assertOk();

        $otpToken = $login->json('otp_token');
        $originalHash = MobileOtpChallenge::where('otp_token', $otpToken)->firstOrFail()->code_hash;

        $this->postJson('/api/auth/otp/resend', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'otp_token' => $otpToken,
        ])
            ->assertOk()
            ->assertJsonPath('otp_token', $otpToken);

        $challenge = MobileOtpChallenge::where('otp_token', $otpToken)->firstOrFail();
        $this->assertNotSame($originalHash, $challenge->code_hash);
        $this->assertSame(1, MobileOtpChallenge::count());
    }
}
