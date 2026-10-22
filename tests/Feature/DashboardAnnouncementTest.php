<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_account_change_is_shown_only_to_the_affected_student(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $student = $this->student('affected.student@example.test');
        $otherStudent = $this->student('other.student@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'users',
            'record_id' => $student->id,
            'old_values' => ['year_level' => '1st Year'],
            'new_values' => ['year_level' => '2nd Year'],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('announcements', [
            'created_by' => $admin->id,
            'target_user_id' => $student->id,
            'title' => 'Your account information was updated',
            'message' => 'Your year level changed from "1st Year" to "2nd Year".',
        ]);
        $this->assertDatabaseMissing('announcements', ['target_user_id' => $otherStudent->id]);

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Your year level changed from &quot;1st Year&quot; to &quot;2nd Year&quot;.', false)
            ->assertSee('Personal');

        $this->actingAs($otherStudent)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertDontSee('Your year level changed')
            ->assertSee('No announcements yet.');

        $this->actingAs($otherStudent)
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Your year level changed');
    }

    public function test_announcements_are_shown_on_officer_and_coordinator_dashboards(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_periods',
            'record_id' => 1,
            'old_values' => [
                'academic_year' => '2025-2026',
                'term_name' => '1st Term',
            ],
            'new_values' => [
                'academic_year' => '2026-2027',
                'term_name' => '2nd Term',
            ],
            'created_at' => now(),
        ]);

        $expectedMessage = 'For 2nd Term for Academic Year 2026-2027, the academic year changed from '
            .'&quot;2025-2026&quot; to &quot;2026-2027&quot; and the academic term changed from '
            .'&quot;1st Term&quot; to &quot;2nd Term&quot;.';

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee($expectedMessage, false);

        $this->actingAs($coordinator)
            ->get(route('coordinator.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee($expectedMessage, false);

        $this->assertSame(0, Announcement::query()->where('target_role', 'super_admin')->count());
    }

    public function test_catalog_announcement_uses_department_names_instead_of_ids(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $departments = AcademicUnit::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $departments);
        $program = AcademicProgram::query()->firstOrFail();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_programs',
            'record_id' => $program->id,
            'old_values' => [
                'academic_unit_id' => $departments[0]->id,
                'name' => $program->name,
            ],
            'new_values' => [
                'academic_unit_id' => $departments[1]->id,
                'name' => $program->name,
            ],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('announcements', [
            'target_role' => 'discipline_officer',
            'target_academic_unit_id' => $departments[0]->id,
            'title' => 'Program department changed',
            'message' => sprintf(
                'The academic department for %s changed from "%s" to "%s".',
                $program->name,
                $departments[0]->name,
                $departments[1]->name,
            ),
        ]);
        $this->assertDatabaseHas('announcements', [
            'target_role' => 'discipline_officer',
            'target_academic_unit_id' => $departments[1]->id,
            'title' => 'Program department changed',
        ]);
    }

    public function test_catalog_announcements_are_visible_only_to_assigned_departments(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $departments = AcademicUnit::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $departments);
        $assignedOfficer = $this->user('discipline_officer', 'assigned@example.test');
        $unrelatedOfficer = $this->user('discipline_officer', 'unrelated@example.test');
        $assignedOfficer->academicUnits()->attach($departments[0]->id);
        $unrelatedOfficer->academicUnits()->attach($departments[1]->id);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_units',
            'record_id' => $departments[0]->id,
            'old_values' => ['name' => 'Former Department Name'],
            'new_values' => ['name' => 'Updated Department Name'],
            'created_at' => now(),
        ]);

        $message = 'The academic department was renamed from "Former Department Name" to "Updated Department Name".';

        $this->actingAs($assignedOfficer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee($message);

        $this->actingAs($unrelatedOfficer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertDontSee($message)
            ->assertSee('<section class="dashboard-announcements"', false)
            ->assertSee('No announcements yet.');
    }

    public function test_coordinator_sees_department_scoped_announcements_for_every_department(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $departments = AcademicUnit::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $departments);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_units',
            'record_id' => $departments[0]->id,
            'old_values' => ['name' => 'Former Department Name'],
            'new_values' => ['name' => 'Updated Department Name'],
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee('The academic department was renamed from "Former Department Name" to "Updated Department Name".');
    }

    public function test_catalog_changes_are_broadcast_to_guidance_and_supervisor(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $guidance = $this->user('guidance_officer', 'guidance@example.test');
        $supervisor = $this->user('us_supervisor', 'supervisor@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_units',
            'record_id' => 1,
            'old_values' => ['name' => 'Former Department Name'],
            'new_values' => ['name' => 'Updated Department Name'],
            'created_at' => now(),
        ]);

        $message = 'The academic department was renamed from "Former Department Name" to "Updated Department Name".';

        $this->actingAs($guidance)
            ->get(route('guidance.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee($message);

        $this->actingAs($supervisor)
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee('Announcements')
            ->assertSee($message);
    }

    public function test_risk_setting_announcements_are_not_broadcast_to_guidance_and_supervisor(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $guidance = $this->user('guidance_officer', 'guidance@example.test');
        $supervisor = $this->user('us_supervisor', 'supervisor@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'risk_factor_weights',
            'old_values' => [
                'total_offense_count' => ['weight' => 0.2, 'count_cap' => 3],
            ],
            'new_values' => [
                'methodology_version' => '1.0',
                'weights' => [
                    'total_offense_count' => ['weight' => 0.25, 'count_cap' => 4],
                ],
            ],
            'created_at' => now(),
        ]);

        $this->assertSame(0, Announcement::query()->whereIn('target_role', ['guidance_officer', 'us_supervisor', 'reporting_user', 'student'])->count());
        $this->assertSame(2, Announcement::query()->whereIn('target_role', ['discipline_coordinator', 'discipline_officer'])->count());

        $this->actingAs($guidance)
            ->get(route('guidance.dashboard'))
            ->assertOk()
            ->assertSee('No announcements yet.');

        $this->actingAs($supervisor)
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee('No announcements yet.');
    }

    public function test_risk_setting_announcement_describes_nested_before_and_after_values(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'risk_factor_weights',
            'old_values' => [
                'total_offense_count' => ['weight' => 0.2, 'count_cap' => 3],
            ],
            'new_values' => [
                'methodology_version' => '1.0',
                'weights' => [
                    'total_offense_count' => ['weight' => 0.25, 'count_cap' => 4],
                ],
            ],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('announcements', [
            'target_role' => 'discipline_officer',
            'message' => 'The total offense count weight changed from "0.2" to "0.25" and the total offense count cap changed from "3" to "4".',
        ]);
    }

    public function test_announcements_disappear_from_the_dashboard_after_three_days(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $expired = Announcement::create([
            'created_by' => $admin->id,
            'target_role' => 'discipline_officer',
            'kind' => 'academic_periods',
            'title' => 'Expired academic term update',
            'message' => 'This announcement should no longer appear.',
        ]);
        $expired->timestamps = false;
        $expired->forceFill(['created_at' => now()->subDays(3)->subSecond()])->saveQuietly();

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertDontSee('Expired academic term update')
            ->assertDontSee('This announcement should no longer appear.')
            ->assertSee('<section class="dashboard-announcements"', false)
            ->assertSee('No announcements yet.');
    }

    public function test_multiple_updates_from_the_same_day_share_one_bulleted_announcement_card(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');

        foreach ([
            ['1st Term', '2nd Term'],
            ['2025-2026', '2026-2027'],
        ] as $index => [$oldValue, $newValue]) {
            $field = $index === 0 ? 'term_name' : 'academic_year';
            AuditLog::create([
                'user_id' => $admin->id,
                'action' => 'update',
                'module' => 'academic_periods',
                'record_id' => $index + 1,
                'old_values' => [$field => $oldValue],
                'new_values' => [$field => $newValue],
                'created_at' => now(),
            ]);
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_periods',
            'record_id' => 3,
            'old_values' => ['term_name' => '1st Term'],
            'new_values' => ['term_name' => '2nd Term'],
            'created_at' => now(),
        ]);

        Announcement::create([
            'created_by' => $admin->id,
            'target_role' => 'discipline_officer',
            'kind' => 'academic_periods',
            'title' => 'Legacy academic term update',
            'message' => 'The System Administrator changed the term name from "1st Term" to "2nd Term".',
        ]);

        $response = $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('dashboard-announcement-items', false)
            ->assertSee('Changes as of '.now()->format('F j, Y').':')
            ->assertSee('The academic term changed from &quot;1st Term&quot; to &quot;2nd Term&quot;.', false)
            ->assertSee('The academic year changed from &quot;2025-2026&quot; to &quot;2026-2027&quot;.', false)
            ->assertDontSee('The System Administrator changed', false)
            ->assertDontSee('Academic term updated')
            ->assertDontSee('Academic Catalog');

        $this->assertSame(1, substr_count($response->getContent(), '<article class="dashboard-announcement-card">'));
        $this->assertSame(2, substr_count($response->getContent(), '<li>'));
    }

    public function test_routine_audit_events_do_not_create_announcements(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => 1,
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'under_review'],
            'created_at' => now(),
        ]);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_no_op_configuration_updates_do_not_create_announcements(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_units',
            'record_id' => 1,
            'old_values' => ['name' => 'School of Engineering'],
            'new_values' => ['name' => '  School   of Engineering  '],
            'created_at' => now(),
        ]);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_department_rename_announcement_uses_a_clear_sentence(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_units',
            'record_id' => 1,
            'old_values' => ['name' => 'School of Computing'],
            'new_values' => ['name' => 'School of Engineering'],
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('announcements', [
            'target_role' => 'discipline_officer',
            'message' => 'The academic department was renamed from "School of Computing" to "School of Engineering".',
        ]);
    }

    public function test_academic_period_date_change_shows_readable_dates_not_raw_timestamps(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'term_name' => '1st Term',
            'starts_on' => '2026-07-31',
            'ends_on' => '2026-10-30',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-periods.update', $period), [
                'academic_year' => '2026-2027',
                'term_name' => '1st Term',
                'starts_on' => '2026-07-12',
                'ends_on' => '2026-10-13',
            ])
            ->assertRedirect();

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertDontSee('T00:00:00', false)
            ->assertDontSee('T16:00:00', false)
            ->assertSee('2026-07-31', false)
            ->assertSee('2026-07-12', false);
    }

    public function test_dashboard_shows_only_the_three_most_recent_announcements(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');

        foreach (range(1, 5) as $i) {
            Announcement::create([
                'created_by' => $admin->id,
                'target_role' => 'discipline_officer',
                'kind' => 'academic_periods',
                'title' => "Update {$i}",
                'message' => "Test message number {$i}.",
            ]);
        }

        $response = $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk();

        $this->assertSame(3, substr_count($response->getContent(), '<li>'));
    }

    public function test_view_all_button_links_to_full_announcements_page(): void
    {
        $admin = $this->user('super_admin', 'admin@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');

        foreach (range(1, 5) as $i) {
            Announcement::create([
                'created_by' => $admin->id,
                'target_role' => 'discipline_officer',
                'kind' => 'academic_periods',
                'title' => "Update {$i}",
                'message' => "Test message number {$i}.",
            ]);
        }

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee(route('announcements.index'), false)
            ->assertSee('View All');

        $response = $this->actingAs($officer)
            ->get(route('announcements.index'))
            ->assertOk();

        $this->assertSame(5, substr_count($response->getContent(), '<li>'));
    }

    private function student(string $email): User
    {
        $student = $this->user('student', $email);
        $program = AcademicProgram::query()->firstOrFail();
        $student->studentProfile()->create([
            'student_number' => fake()->unique()->numerify('2026-#####'),
            'program' => $program->name,
            'year_level' => '1st Year',
            'contact_number' => '09171234567',
            'guardian_name' => 'Guardian Name',
            'guardian_contact' => '09179876543',
            'guardian_relationship' => 'Parent',
            'data_accuracy_acknowledged_at' => now(),
        ]);

        return $student;
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
