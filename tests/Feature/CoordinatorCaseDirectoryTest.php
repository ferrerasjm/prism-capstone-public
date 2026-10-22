<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorCaseDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_browse_all_cases_and_filter_by_type(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $minorStudent = $this->user('student', 'minor.student@example.test', 'Minor Student');
        $majorStudent = $this->user('student', 'major.student@example.test', 'Major Student');

        $minor = $this->case('CASE-MINOR-001', 'minor', $minorStudent, $officer);
        $major = $this->case('CASE-MAJOR-001', 'major', $majorStudent, $officer);

        $this->actingAs($coordinator)
            ->get(route('coordinator.case-records.index'))
            ->assertOk()
            ->assertViewHas('coordActiveNav', 'cases')
            ->assertSee($minor->case_number)
            ->assertSee($major->case_number);

        $this->get(route('coordinator.case-records.index', ['case_type' => 'major']))
            ->assertOk()
            ->assertSee($major->case_number)
            ->assertDontSee($minor->case_number);
    }

    public function test_case_directory_uses_the_shared_table_design_sorted_newest_first_with_a_department_column(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $student = $this->user('student', 'sait.student@example.test', 'SAIT Student');

        \App\Models\StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2099-0000023',
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        $older = $this->case('CASE-OLD-001', 'major', $student, $officer);
        $older->forceFill(['created_at' => now()->subDays(5)])->save();
        $newer = $this->case('CASE-NEW-001', 'major', $student, $officer);
        $newer->forceFill(['created_at' => now()])->save();

        $response = $this->actingAs($coordinator)
            ->get(route('coordinator.case-records.index'))
            ->assertOk();

        $unitName = \App\Models\AcademicProgram::where('name', 'BS Information Technology')
            ->firstOrFail()->academicUnit->name;
        $acronym = \App\Models\AcademicUnit::abbreviatedName($unitName);

        $response->assertSee('Case No. &amp; Status', false);
        $response->assertSee('Department');
        // Department shows the unit acronym in the row (the full name still
        // appears in the filter dropdown, so only assert the acronym is present).
        $response->assertSee('<td class="text-muted-cell">'.$acronym.'</td>', false);
        // Assigned Officer column is gone.
        $response->assertDontSee('Assigned Officer');
        $response->assertDontSee($officer->email);
        // Case number renders as the shared navy ref-link, not plain text.
        $response->assertSee('prism-ref-link case-number-link', false);
        // Default order: newest case created first.
        $response->assertSeeInOrder(['CASE-NEW-001', 'CASE-OLD-001']);
    }

    public function test_coordinator_can_open_a_case_record_without_officer_controls(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $student = $this->user('student', 'student@example.test', 'Read Only Student');
        $case = $this->case('CASE-READ-ONLY-001', 'minor', $student, $officer);

        $this->actingAs($coordinator)
            ->get(route('coordinator.case-records.show', $case))
            ->assertOk()
            ->assertViewHas('coordActiveNav', 'cases')
            ->assertSee($case->case_number)
            ->assertSee($student->list_name)
            ->assertDontSee('Confirm Offense')
            ->assertDontSee('Issue NTE')
            ->assertDontSee('Save Evaluation');
    }

    public function test_a_forwarded_case_record_shows_the_view_upcc_button_and_blue_forwarded_header(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@example.test');
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $student = $this->user('student', 'student@example.test', 'Forwarded Student');

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-REC',
            'offense_type' => 'major',
            'title' => 'Bringing in, carrying, possession, or use of deadly weapons inside the university premises',
            'description' => 'Weapons offense used for the record-details view test.',
            'keywords' => ['weapon'],
            'created_at' => now(),
        ]);

        $case = $this->case('CASE-FWD-UPCC-001', 'major', $student, $officer);
        $case->update([
            'status' => 'pending_upcc',
            'confirmed_offense_id' => $offense->id,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now(),
            'forwarded_reason' => 'The student fully denies the allegation.',
        ]);

        $response = $this->actingAs($coordinator)
            ->get(route('coordinator.case-records.show', $case))
            ->assertOk();

        // View UPCC primary button links to the UPCC workflow page.
        $response->assertSee('View UPCC');
        $response->assertSee(route('coordinator.cases.show', $case), false);
        // Forwarded card header is blue, no redundant status badge.
        $response->assertSee('prism-card-header--blue', false);
        // The offense title is shown in full, no "See more" toggle.
        $response->assertSee('deadly weapons inside the university premises');
        $response->assertDontSee('See more');
    }

    public function test_non_coordinator_cannot_access_the_coordinator_case_directory(): void
    {
        $this->actingAs($this->user('discipline_officer', 'officer@example.test'))
            ->get(route('coordinator.case-records.index'))
            ->assertForbidden();
    }

    private function case(string $number, string $type, User $student, User $officer): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $number,
            'case_type' => $type,
            'case_type_locked' => true,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
    }

    private function user(string $role, string $email, string $name = 'Test User'): User
    {
        [$firstName, $lastName] = explode(' ', $name, 2);

        return User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
