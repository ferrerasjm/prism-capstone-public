<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAccountSetupEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_staff_account_receives_a_one_time_setup_link_without_a_temporary_password(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->actingAs($admin)
            ->post(route('admin.users.store'), $this->staffData())
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success', function (string $message): bool {
                return str_contains($message, 'one-time setup link was queued')
                    && ! str_contains(strtolower($message), 'password');
            });

        $staff = User::where('email', 'new.officer@nu-clark.edu.ph')->firstOrFail();

        Notification::assertSentTo(
            $staff,
            AccountSetupLinkNotification::class,
            function (AccountSetupLinkNotification $notification) use ($staff): bool {
                $mail = $notification->toMail($staff);
                $html = (string) $mail->render();
                $setupUrl = (string) $mail->viewData['setupUrl'];
                $plainToken = basename((string) parse_url($setupUrl, PHP_URL_PATH));

                return $mail->subject === 'Activate your NU Clark PRISM account'
                    && $mail->viewData['roleLabel'] === 'Discipline Officer'
                    && str_contains($html, 'Set Up My Account')
                    && str_contains($html, $staff->email)
                    && ! str_contains(strtolower($html), 'temporary password')
                    && strlen($plainToken) === 64
                    && hash_equals(
                        hash('sha256', $plainToken),
                        (string) $staff->accountSetupToken?->token_hash,
                    );
            },
        );

        $this->assertTrue(is_subclass_of(AccountSetupLinkNotification::class, ShouldQueue::class));
        $this->assertTrue(is_subclass_of(AccountSetupLinkNotification::class, ShouldBeEncrypted::class));
        $this->assertSame('pending_setup', $staff->status);
        $this->assertFalse(Hash::check($staff->institutional_id, $staff->password));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'users',
            'record_id' => $staff->id,
        ]);
        $this->assertNotNull($response->getSession());
    }

    public function test_admin_can_create_an_individual_student_with_a_setup_link(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'last_name' => 'Student',
                'first_name' => 'Manual',
                'middle_name' => null,
                'institutional_id' => '2026-00123',
                'email' => 'manual.student@students.nu-clark.edu.ph',
                'role' => 'student',
            ])
            ->assertRedirect(route('admin.users.index'));

        $student = User::where('email', 'manual.student@students.nu-clark.edu.ph')->firstOrFail();

        $this->assertSame('student', $student->role);
        $this->assertSame('pending_setup', $student->status);
        $this->assertFalse(Hash::check('2026-00123', $student->password));
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'student_number' => '2026-00123',
        ]);

        Notification::assertSentTo(
            $student,
            AccountSetupLinkNotification::class,
            function (AccountSetupLinkNotification $notification) use ($student): bool {
                $mail = $notification->toMail($student);
                $html = (string) $mail->render();

                return $mail->viewData['roleLabel'] === 'Student'
                    && $mail->viewData['isStudent'] === true
                    && str_contains($html, 'Complete your student profile');
            },
        );
    }

    public function test_manual_student_number_must_be_unique_across_student_profiles(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $existing = User::create([
            'last_name' => 'Existing',
            'first_name' => 'Student',
            'email' => 'existing.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
        $existing->studentProfile()->create(['student_number' => '2026-00123']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'last_name' => 'Duplicate',
                'first_name' => 'Student',
                'institutional_id' => '2026-00123',
                'email' => 'duplicate.student@students.nu-clark.edu.ph',
                'role' => 'student',
            ])
            ->assertSessionHasErrors('institutional_id', null, 'createUser');

        $this->assertDatabaseMissing('users', [
            'email' => 'duplicate.student@students.nu-clark.edu.ph',
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'last_name' => 'Admin',
            'first_name' => 'Super',
            'email' => 'super.demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function staffData(): array
    {
        return [
            'last_name' => 'Officer',
            'first_name' => 'New',
            'middle_name' => null,
            'institutional_id' => 'NU-EMP-00123',
            'email' => 'new.officer@nu-clark.edu.ph',
            'role' => 'discipline_officer',
            'office_name' => null,
            'academic_unit_ids' => [AcademicUnit::query()->where('is_active', true)->value('id')],
        ];
    }
}
