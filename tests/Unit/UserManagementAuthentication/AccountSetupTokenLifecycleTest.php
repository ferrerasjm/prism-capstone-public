<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Models\AccountSetupToken;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use App\Services\AccountSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Module 1 - User Management and Authentication
 * Component: Account Setup Token Lifecycle (App\Services\AccountSetupService)
 * Test Cycle No. 1 - 5 test cases.
 */
class AccountSetupTokenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: a pending_setup account receives a hashed, expiring token. */
    public function test_issuing_a_link_stores_only_the_hashed_token_with_an_expiry(): void
    {
        Notification::fake();
        $user = $this->user('pending_setup', 'discipline_officer');

        $token = (new AccountSetupService)->issue($user);
        $plain = $this->capturePlainToken($user);

        $this->assertNotNull($plain);
        $this->assertSame(hash('sha256', $plain), $token->token_hash);
        $this->assertNotSame($plain, $token->token_hash);
        $this->assertNull($token->consumed_at);
        $this->assertTrue($token->expires_at->greaterThan(now()));
    }

    /** Scenario 2 - Invalid state: an already active account cannot be issued a setup link. */
    public function test_issuing_a_link_for_an_active_account_is_rejected(): void
    {
        Notification::fake();
        $user = $this->user('active', 'discipline_officer');

        $this->expectException(ValidationException::class);

        (new AccountSetupService)->issue($user);
    }

    /** Scenario 3 - Invalid input: a malformed token is rejected before any lookup. */
    public function test_a_malformed_token_string_is_never_treated_as_valid(): void
    {
        Notification::fake();
        $service = new AccountSetupService;
        $user = $this->user('pending_setup', 'discipline_officer');
        $service->issue($user);

        $this->assertNull($service->findValid('not-a-hex-token'));
        $this->assertNull($service->findValid(str_repeat('a', 63)));
        $this->assertNull($service->findValid(str_repeat('z', 64)));
    }

    /** Scenario 4 - Normal: consuming a token sets the password and the role-correct status. */
    public function test_consuming_a_valid_token_sets_the_password_and_role_specific_status(): void
    {
        Notification::fake();
        $service = new AccountSetupService;

        $staff = $this->user('pending_setup', 'discipline_officer');
        $service->issue($staff);
        $staffToken = $this->capturePlainToken($staff);
        $activatedStaff = $service->consume($staffToken, 'StaffPass#2026', '127.0.0.1');

        $student = $this->user('pending_setup', 'student');
        $service->issue($student);
        $studentToken = $this->capturePlainToken($student);
        $activatedStudent = $service->consume($studentToken, 'StudentPass#2026', '127.0.0.1');

        $this->assertSame('active', $activatedStaff->status);
        $this->assertTrue(Hash::check('StaffPass#2026', $activatedStaff->password));
        $this->assertSame('pending_setup', $activatedStudent->status);
        $this->assertNotNull(AccountSetupToken::where('user_id', $staff->id)->first()->consumed_at);
    }

    /** Scenario 5 - Failure: a consumed token cannot be replayed. */
    public function test_a_token_cannot_be_consumed_twice(): void
    {
        Notification::fake();
        $service = new AccountSetupService;
        $user = $this->user('pending_setup', 'discipline_officer');
        $service->issue($user);
        $plain = $this->capturePlainToken($user);

        $service->consume($plain, 'FirstPass#2026', '127.0.0.1');

        $this->expectException(ValidationException::class);

        $service->consume($plain, 'SecondPass#2026', '127.0.0.1');
    }

    private function capturePlainToken(User $user): ?string
    {
        $plain = null;

        Notification::assertSentTo(
            $user,
            AccountSetupLinkNotification::class,
            function (AccountSetupLinkNotification $notification) use (&$plain): bool {
                $plain = (new ReflectionProperty($notification, 'token'))->getValue($notification);

                return true;
            }
        );

        return $plain;
    }

    private function user(string $status, string $role): User
    {
        static $sequence = 0;
        $sequence++;

        return User::create([
            'last_name' => 'Dela Cruz',
            'first_name' => 'Account'.$sequence,
            'email' => "account{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('placeholder-password'),
            'role' => $role,
            'status' => $status,
        ]);
    }
}
