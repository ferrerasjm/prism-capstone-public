<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AcademicProgram;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcademicPeriodReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_a_period_and_existing_incidents_are_synchronized(): void
    {
        $admin = $this->user('super_admin');
        $incident = IncidentReport::create([
            'report_number' => 'IR-PERIOD-001',
            'source_type' => 'direct_encoding',
            'date_of_incident' => '2025-09-15',
            'description' => 'Existing incident awaiting academic-period configuration.',
            'status' => 'under_review',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.academic-periods.store'), [
                'academic_year' => '2025-2026',
                'term_name' => '1st Term',
                'starts_on' => '2025-08-01',
                'ends_on' => '2025-12-20',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $period = AcademicPeriod::firstOrFail();

        $this->assertSame($period->id, $incident->fresh()->academic_period_id);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'academic_periods',
            'record_id' => $period->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.academic-periods.index'))
            ->assertOk()
            ->assertSee('editPeriodModal')
            ->assertSee('periodStatusModal')
            ->assertSee('Archive Academic Period')
            ->assertSee('Archive Reason')
            ->assertSee('Save Changes')
            ->assertDontSee('<i class="bi bi-check-lg"></i> Save Changes', false)
            ->assertSee('<span class="visually-hidden">Actions</span>', false)
            ->assertSee('Archive academic period')
            ->assertDontSee('return confirm', false)
            ->assertDontSee('data-period-field', false);
    }

    public function test_end_date_cannot_be_earlier_than_start_date(): void
    {
        $admin = $this->user('super_admin');

        $this->actingAs($admin)
            ->post(route('admin.academic-periods.store'), [
                'academic_year' => '2025-2026',
                'term_name' => '1st Term',
                'starts_on' => '2025-12-20',
                'ends_on' => '2025-08-01',
            ])
            ->assertSessionHas('error', 'The end date cannot be earlier than the start date.');

        $this->assertDatabaseCount('academic_periods', 0);
    }

    public function test_archiving_a_period_preserves_history_and_stops_new_automatic_assignments(): void
    {
        $admin = $this->user('super_admin');
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'term_name' => '1st Term',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
        ]);
        $historicalIncident = IncidentReport::create([
            'report_number' => 'IR-PERIOD-HISTORY',
            'source_type' => 'direct_encoding',
            'date_of_incident' => '2026-09-15',
            'description' => 'Incident linked before archival.',
            'status' => 'under_review',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-periods.toggle-status', $period), [
                'status_target_id' => $period->id,
            ])
            ->assertSessionHasErrors('archive_reason');

        $this->assertTrue($period->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.academic-periods.toggle-status', $period), [
                'status_target_id' => $period->id,
                'archive_reason' => 'The academic term has concluded.',
            ])
            ->assertSessionHas('success');

        $this->assertFalse($period->fresh()->is_active);
        $this->assertSame($period->id, $historicalIncident->fresh()->academic_period_id);
        $this->actingAs($admin)
            ->get(route('admin.academic-periods.index'))
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee('Restore academic period');

        $newIncident = IncidentReport::create([
            'report_number' => 'IR-PERIOD-AFTER-ARCHIVE',
            'source_type' => 'direct_encoding',
            'date_of_incident' => '2026-10-01',
            'description' => 'Incident created while the period is archived.',
            'status' => 'under_review',
        ]);
        $this->assertNull($newIncident->academic_period_id);

        $this->actingAs($admin)
            ->patch(route('admin.academic-periods.toggle-status', $period))
            ->assertSessionHas('success');

        $this->assertTrue($period->fresh()->is_active);
        $this->assertSame($period->id, $newIncident->fresh()->academic_period_id);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'update',
            'module' => 'academic_periods',
            'record_id' => $period->id,
            'reason' => 'The academic term has concluded.',
        ]);
    }

    public function test_archived_period_must_be_restored_before_editing(): void
    {
        $admin = $this->user('super_admin');
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'term_name' => '1st Term',
            'starts_on' => '2026-08-01',
            'ends_on' => '2026-12-20',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-periods.update', $period), [
                'academic_year' => '2026-2027',
                'term_name' => '2nd Term',
                'starts_on' => '2027-01-05',
                'ends_on' => '2027-04-30',
            ])
            ->assertSessionHas('error', 'Restore this academic period before editing it.');

        $this->assertSame('1st Term', $period->fresh()->term_name);
    }

    public function test_overlapping_academic_periods_are_rejected(): void
    {
        $admin = $this->user('super_admin');
        AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '1st Term',
            'starts_on' => '2025-08-01',
            'ends_on' => '2025-12-20',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.academic-periods.store'), [
                'academic_year' => '2025-2026',
                'term_name' => '2nd Term',
                'starts_on' => '2025-12-01',
                'ends_on' => '2026-03-31',
            ])
            ->assertSessionHas('error', 'Academic-period date ranges cannot overlap.');

        $this->assertDatabaseCount('academic_periods', 1);
    }

    public function test_new_incidents_are_automatically_assigned_by_incident_date(): void
    {
        $period = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '2nd Term',
            'starts_on' => '2026-01-05',
            'ends_on' => '2026-04-30',
        ]);

        $incident = IncidentReport::create([
            'report_number' => 'IR-PERIOD-002',
            'source_type' => 'direct_encoding',
            'date_of_incident' => '2026-02-10',
            'description' => 'Incident occurring during the configured second term.',
            'status' => 'under_review',
        ]);

        $this->assertSame($period->id, $incident->academic_period_id);
    }

    public function test_recording_a_violation_slip_assigns_the_active_academic_period(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $program = AcademicProgram::query()->firstOrFail();
        $officer->update(['academic_unit_id' => $program->academic_unit_id]);
        $student->studentProfile()->create([
            'student_number' => '2026-PERIOD-001',
            'program' => $program->name,
        ]);
        $period = AcademicPeriod::create([
            'academic_year' => '2026-2027',
            'term_name' => '1st Term',
            'starts_on' => '2026-07-01',
            'ends_on' => '2026-12-20',
            'is_active' => true,
        ]);

        $this->actingAs($officer)
            ->post(route('officer.violations.store'), [
                'student_id' => $student->id,
                'date_of_incident' => '2026-07-30',
                'time_of_incident' => '09:30',
                'location' => 'Main Campus',
                'description' => 'Student committed a documented uniform violation.',
                'violation_type' => 'Non-wearing of prescribed uniform',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $report = IncidentReport::where('source_type', 'violation_slip')->firstOrFail();

        $this->assertSame($period->id, $report->academic_period_id);
        $this->assertSame($student->id, $report->student_id);
    }

    public function test_monthly_report_filters_cases_by_incident_academic_period(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $student->studentProfile()->create([
            'student_number' => '2026-TERM-001',
            'program' => 'BS Information Technology',
        ]);
        $firstTerm = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '1st Term',
            'starts_on' => '2025-08-01',
            'ends_on' => '2025-12-20',
        ]);
        $secondTerm = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '2nd Term',
            'starts_on' => '2026-01-05',
            'ends_on' => '2026-04-30',
        ]);

        $firstIncident = $this->incident('IR-PERIOD-FIRST', $student, '2025-09-15');
        $secondIncident = $this->incident('IR-PERIOD-SECOND', $student, '2026-02-10');
        $this->case('CASE-PERIOD-FIRST', $firstIncident, $student, $officer, 'minor');
        $this->case('CASE-PERIOD-SECOND', $secondIncident, $student, $officer, 'major');

        $firstData = (new ReportService)->monthlyViolation(2025, null, $firstTerm);
        $secondData = (new ReportService)->monthlyViolation(2026, null, $secondTerm);

        $this->assertSame(1, (int) $firstData['minorByMonth']->sum('total'));
        $this->assertSame(0, (int) $firstData['majorByMonth']->sum('total'));
        $this->assertSame(0, (int) $secondData['minorByMonth']->sum('total'));
        $this->assertSame(1, (int) $secondData['majorByMonth']->sum('total'));

        $this->actingAs($officer)
            ->get(route('officer.reports.monthly-violation', [
                'academic_period_id' => $firstTerm->id,
            ]))
            ->assertOk()
            ->assertSee('1st Term A.Y. 2025-2026')
            ->assertSee('Academic Term');

        Storage::fake('local');
        $this->actingAs($officer)
            ->post(route('officer.reports.export', 'monthly_violation_summary'), [
                'academic_period_id' => $firstTerm->id,
                'format' => 'xlsx',
            ])
            ->assertDownload();

        $export = ScheduledReport::latest('id')->firstOrFail();
        $this->assertSame('2025-08-01', $export->period_start->toDateString());
        $this->assertSame('2025-12-20', $export->period_end->toDateString());
        Storage::disk('local')->assertExists($export->file_path);
    }

    public function test_incident_and_case_worklists_can_be_filtered_by_academic_period(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $program = AcademicProgram::query()->firstOrFail();
        $officer->update(['academic_unit_id' => $program->academic_unit_id]);
        $student->studentProfile()->create([
            'student_number' => '2026-WORKLIST-001',
            'program' => $program->name,
        ]);
        $firstTerm = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '1st Term',
            'starts_on' => '2025-08-01',
            'ends_on' => '2025-12-20',
        ]);
        AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '2nd Term',
            'starts_on' => '2026-01-05',
            'ends_on' => '2026-04-30',
        ]);

        $firstIncident = $this->incident('IR-WORKLIST-FIRST', $student, '2025-09-15');
        $secondIncident = $this->incident('IR-WORKLIST-SECOND', $student, '2026-02-10');
        $this->case('CASE-WORKLIST-FIRST', $firstIncident, $student, $officer, 'minor');
        $this->case('CASE-WORKLIST-SECOND', $secondIncident, $student, $officer, 'major');

        $this->actingAs($officer)
            ->get(route('officer.incidents.index', ['academic_period_id' => $firstTerm->id]))
            ->assertOk()
            ->assertSee('IR-WORKLIST-FIRST')
            ->assertDontSee('IR-WORKLIST-SECOND')
            ->assertSee('All Academic Periods')
            ->assertViewHas('stats', fn (array $stats) => $stats['converted'] === 1);

        $this->actingAs($officer)
            ->get(route('officer.cases.index', ['academic_period_id' => $firstTerm->id]))
            ->assertOk()
            ->assertSee('CASE-WORKLIST-FIRST')
            ->assertDontSee('CASE-WORKLIST-SECOND')
            ->assertSee('All Academic Periods')
            ->assertViewHas('stats', fn (array $stats) => $stats['resolved'] === 1);
    }

    public function test_case_summary_uses_the_incident_academic_period_instead_of_case_creation_date(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $firstTerm = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '1st Term',
            'starts_on' => '2025-08-01',
            'ends_on' => '2025-12-20',
        ]);
        $secondTerm = AcademicPeriod::create([
            'academic_year' => '2025-2026',
            'term_name' => '2nd Term',
            'starts_on' => '2026-01-05',
            'ends_on' => '2026-04-30',
        ]);

        $firstIncident = $this->incident('IR-SUMMARY-FIRST', $student, '2025-10-01');
        $secondIncident = $this->incident('IR-SUMMARY-SECOND', $student, '2026-02-01');
        $this->case('CASE-SUMMARY-FIRST', $firstIncident, $student, $officer, 'minor');
        $this->case('CASE-SUMMARY-SECOND', $secondIncident, $student, $officer, 'major');

        $data = (new ReportService)->caseSummary(
            $firstTerm->starts_on->toDateString(),
            $firstTerm->ends_on->toDateString(),
            $firstTerm,
        );

        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['minor']);
        $this->assertSame(0, $data['major']);

        $this->actingAs($officer)
            ->get(route('officer.reports.case-summary', [
                'academic_period_id' => $secondTerm->id,
            ]))
            ->assertOk()
            ->assertSee('Select term')
            ->assertSee('2nd Term A.Y. 2025-2026')
            ->assertSee('2026-01-05')
            ->assertSee('2026-04-30');
    }

    public function test_case_summary_can_be_filtered_by_student_program(): void
    {
        $this->travelTo(Carbon::parse('2026-07-20 12:00:00'));
        $officer = $this->user('discipline_officer');
        $itStudent = $this->user('student');
        $businessStudent = $this->user('student');
        $itStudent->studentProfile()->create(['student_number' => 'IT-001', 'program' => 'BSIT']);
        $businessStudent->studentProfile()->create(['student_number' => 'BA-001', 'program' => 'BSBA']);

        $this->case('CASE-PROGRAM-IT', $this->incident('IR-PROGRAM-IT', $itStudent, '2026-07-10'), $itStudent, $officer, 'minor');
        $this->case('CASE-PROGRAM-BA', $this->incident('IR-PROGRAM-BA', $businessStudent, '2026-07-11'), $businessStudent, $officer, 'major');
        $this->travelBack();

        $this->actingAs($officer)
            ->get(route('officer.reports.case-summary', [
                'from' => '2026-07-01',
                'to' => '2026-07-31',
                'program' => 'BSIT',
            ]))
            ->assertOk()
            ->assertViewHas('total', 1)
            ->assertViewHas('minor', 1)
            ->assertViewHas('major', 0)
            ->assertSee('BSIT');
    }

    private function incident(string $number, User $student, string $date): IncidentReport
    {
        return IncidentReport::create([
            'report_number' => $number,
            'source_type' => 'direct_encoding',
            'student_id' => $student->id,
            'date_of_incident' => $date,
            'description' => 'Academic-period report filtering test incident.',
            'status' => 'converted_to_case',
        ]);
    }

    private function case(
        string $number,
        IncidentReport $incident,
        User $student,
        User $officer,
        string $type
    ): DisciplinaryCase {
        return DisciplinaryCase::create([
            'case_number' => $number,
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
            'last_name' => ucfirst($role),
            'first_name' => 'Academic',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
