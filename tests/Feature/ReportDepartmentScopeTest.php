<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_summary_counts_only_students_in_the_officers_assigned_departments(): void
    {
        [$officer, $insideStudent] = $this->scopedOfficerWithStudent('BS Report Testing');
        $outsideStudent = $this->student('BS Something Else');

        $this->caseFor($insideStudent, $officer, 'minor');
        $this->caseFor($outsideStudent, $officer, 'major');

        $service = app(ReportService::class);
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $this->assertSame(2, $service->caseSummary($from, $to, null, null, null)['total']);
        $this->assertSame(1, $service->caseSummary($from, $to, null, null, $officer)['total']);
        $this->assertSame(1, $service->caseSummary($from, $to, null, null, $officer)['minor']);
        $this->assertSame(0, $service->caseSummary($from, $to, null, null, $officer)['major']);
    }

    public function test_officer_case_summary_page_is_scoped_and_coordinator_page_is_not(): void
    {
        [$officer, $insideStudent] = $this->scopedOfficerWithStudent('BS Scoped Program');
        $outsideStudent = $this->student('BS Outside Program');
        $this->caseFor($insideStudent, $officer, 'minor');
        $this->caseFor($outsideStudent, $officer, 'major');

        $this->actingAs($officer)
            ->get(route('officer.reports.case-summary'))
            ->assertOk()
            ->assertViewHas('total', 1);

        $this->actingAs($this->user('discipline_coordinator'))
            ->get(route('coordinator.reports.case-summary'))
            ->assertOk()
            ->assertViewHas('total', 2);
    }

    public function test_an_officer_without_assigned_departments_still_sees_every_student(): void
    {
        $officer = $this->user('discipline_officer');
        $this->caseFor($this->student('BS A'), $officer, 'minor');
        $this->caseFor($this->student('BS B'), $officer, 'major');

        $this->actingAs($officer)
            ->get(route('officer.reports.case-summary'))
            ->assertOk()
            ->assertViewHas('total', 2);
    }

    public function test_student_case_report_is_not_found_for_a_student_outside_the_officers_departments(): void
    {
        [$officer] = $this->scopedOfficerWithStudent('BS Only Mine');
        $outsideStudent = $this->student('BS Not Mine');

        $this->actingAs($officer)
            ->get(route('officer.reports.student-case', ['student_id' => $outsideStudent->id]))
            ->assertNotFound();
    }

    public function test_program_filter_is_hidden_when_the_scoped_officer_has_a_single_program(): void
    {
        [$officer] = $this->scopedOfficerWithStudent('BS Lone Program');

        $response = $this->actingAs($officer)->get(route('officer.reports.case-summary'));

        $response->assertOk();
        $response->assertDontSee('id="programFilter"', false);

        // The coordinator, institution-wide, still gets the dropdown.
        $this->actingAs($this->user('discipline_coordinator'))
            ->get(route('coordinator.reports.case-summary'))
            ->assertSee('id="programFilter"', false);
    }

    public function test_assigned_departments_pill_renders_for_a_scoped_officer_only(): void
    {
        [$officer] = $this->scopedOfficerWithStudent('BS Pill Program', 'Pill Test School');

        $this->actingAs($officer)
            ->get(route('officer.reports.index'))
            ->assertOk()
            ->assertSee('prism-dept-scope', false)
            ->assertSee('Pill Test School');

        $this->actingAs($this->user('discipline_coordinator'))
            ->get(route('coordinator.reports.index'))
            ->assertOk()
            ->assertDontSee('prism-dept-scope', false);
    }

    public function test_risk_dashboard_shows_the_assigned_departments_pill_for_a_scoped_officer(): void
    {
        [$officer] = $this->scopedOfficerWithStudent('BS Risk Pill', 'Risk Pill School');

        $this->actingAs($officer)
            ->get(route('officer.risk.index'))
            ->assertOk()
            ->assertSee('prism-dept-scope', false)
            ->assertSee('Risk Pill School');
    }

    /**
     * @return array{0: User, 1: User} the officer and one student inside their scope
     */
    private function scopedOfficerWithStudent(string $program, string $unitName = 'Report Scope Test School'): array
    {
        $unit = AcademicUnit::create(['name' => $unitName, 'is_active' => true]);
        AcademicProgram::create([
            'academic_unit_id' => $unit->id,
            'name' => $program,
            'is_active' => true,
        ]);

        $officer = $this->user('discipline_officer');
        $officer->academicUnits()->sync([$unit->id]);
        $officer->forceFill(['academic_unit_id' => $unit->id])->save();
        $officer->refresh();

        return [$officer, $this->student($program)];
    }

    private function student(string $program): User
    {
        $student = $this->user('student');
        $student->studentProfile()->create([
            'student_number' => strtoupper(substr(md5($program.microtime()), 0, 8)),
            'program' => $program,
        ]);

        return $student;
    }

    private function caseFor(User $student, User $officer, string $type): DisciplinaryCase
    {
        $incident = IncidentReport::create([
            'report_number' => 'IR-'.strtoupper(substr(md5($student->id.$type.microtime()), 0, 10)),
            'source_type' => 'direct_encoding',
            'student_id' => $student->id,
            'date_of_incident' => now()->toDateString(),
            'description' => 'Report department-scope test incident.',
            'status' => 'converted_to_case',
        ]);

        return DisciplinaryCase::create([
            'case_number' => 'CASE-'.strtoupper(substr(md5($student->id.$type.microtime()), 0, 10)),
            'incident_report_id' => $incident->id,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => $type,
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'first_name' => 'Scope',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
