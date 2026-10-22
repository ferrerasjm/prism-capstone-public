<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\Sanction;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Category 1 probation is the only intervention the handbook attaches an escalation clause
 * to (Section VI.E): a subsequent major offense "and/or infringement of the University
 * Rules and Regulations" during the period means suspension or non-readmission.
 */
class ProbationPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clean_probation_period_closes_itself_as_completed(): void
    {
        [$officer, , $case] = $this->context('DC-PROB-CLEAN');
        $sanction = $this->probationSanction($case, $officer, endedDaysAgo: 2);

        $this->artisan('sanctions:close-probation-periods')->assertSuccessful();

        $sanction->refresh();
        $this->assertSame('completed', $sanction->status);
        $this->assertNotNull($sanction->completed_at);
        $this->assertNull($sanction->monitoring_review_flagged_at);
    }

    public function test_a_new_case_during_probation_is_flagged_for_officer_decision(): void
    {
        [$officer, $student, $case] = $this->context('DC-PROB-CASE');
        $sanction = $this->probationSanction($case, $officer, endedDaysAgo: 2);
        $this->caseOpenedDaysAgo($student, $officer, 'DC-PROB-CASE-2', 5);

        $this->artisan('sanctions:close-probation-periods')->assertSuccessful();

        $sanction->refresh();
        // Suspension and non-readmission are the officer's call between them, so the
        // scheduler flags the breach rather than imposing either one.
        $this->assertSame('in_progress', $sanction->status);
        $this->assertNotNull($sanction->monitoring_review_flagged_at);
    }

    /**
     * The handbook escalates on "any major offense and/or infringement", and minor
     * offenses are recorded as violation slips that never become cases on their own.
     * A case-only breach check would miss them entirely.
     */
    public function test_a_violation_slip_during_probation_counts_as_a_breach(): void
    {
        [$officer, $student, $case] = $this->context('DC-PROB-SLIP');
        $sanction = $this->probationSanction($case, $officer, endedDaysAgo: 2);
        $this->violationSlipIssuedDaysAgo($student, $officer, 'VS-PROB-SLIP-1', 5);

        $this->artisan('sanctions:close-probation-periods')->assertSuccessful();

        $sanction->refresh();
        $this->assertSame('in_progress', $sanction->status);
        $this->assertNotNull($sanction->monitoring_review_flagged_at);
    }

    public function test_a_flagged_probation_period_is_not_reflagged_on_a_rerun(): void
    {
        [$officer, $student, $case] = $this->context('DC-PROB-RERUN');
        $sanction = $this->probationSanction($case, $officer, endedDaysAgo: 2);
        $this->caseOpenedDaysAgo($student, $officer, 'DC-PROB-RERUN-2', 5);

        $this->artisan('sanctions:close-probation-periods')->assertSuccessful();
        $flaggedAt = $sanction->fresh()->monitoring_review_flagged_at;

        $this->artisan('sanctions:close-probation-periods')->assertSuccessful();

        $this->assertEquals($flaggedAt, $sanction->fresh()->monitoring_review_flagged_at);
        $this->assertSame(1, AuditLog::where('module', 'sanctions')->where('record_id', $sanction->id)->count());
    }

    /**
     * "Three (3) academic terms" is a property of the academic calendar, not a date an
     * officer should be typing by hand into a form.
     */
    public function test_the_probation_end_date_is_derived_from_the_academic_calendar(): void
    {
        [$officer, , $case] = $this->context('DC-PROB-CALENDAR', category: 1);

        $start = today()->subWeek();
        foreach (range(1, 3) as $index) {
            AcademicPeriod::create([
                'academic_year' => '2026-2027',
                'term_name' => "Term {$index}",
                'starts_on' => $start->copy()->addMonths(4 * ($index - 1)),
                'ends_on' => $start->copy()->addMonths(4 * $index)->subDay(),
            ]);
        }

        $thirdTermEnd = AcademicPeriod::orderBy('starts_on')->skip(2)->first()->ends_on;

        $caseService = app(CaseService::class);
        $caseService->initiateResolution($case, $officer->id);
        $caseService->recordResolution($case->fresh(), [
            'resolution_level' => 'sdo_level',
            'intervention_category' => 1,
            'probation_terms' => 3,
            // Deliberately wrong: the calendar must win over the typed date.
            'probation_end_date' => today()->addDays(10)->toDateString(),
        ], $officer->id);

        $sanction = Sanction::where('sanction_type', 'probation')->firstOrFail();

        $this->assertSame($thirdTermEnd->toDateString(), $sanction->due_date->toDateString());
        $this->assertSame(
            $thirdTermEnd->toDateString(),
            $case->fresh()->resolution->probation_end_date->toDateString(),
        );
    }

    private function caseOpenedDaysAgo(User $student, User $officer, string $caseNumber, int $daysAgo): void
    {
        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'minor',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 1,
        ]);

        $case->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
    }

    private function violationSlipIssuedDaysAgo(User $student, User $officer, string $reportNumber, int $daysAgo): void
    {
        $report = IncidentReport::create([
            'report_number' => $reportNumber,
            'source_type' => 'violation_slip',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => today()->subDays($daysAgo),
            'description' => 'Incomplete uniform.',
            'status' => 'pending_review',
        ]);

        $report->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();
    }

    private function probationSanction(DisciplinaryCase $case, User $officer, int $endedDaysAgo): Sanction
    {
        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 1,
            'probation_terms' => 3,
            'probation_end_date' => today()->subDays($endedDaysAgo),
            'resolved_by' => $officer->id,
            'resolved_at' => now()->subMonth(),
            'appeal_deadline' => now()->subWeeks(3),
            'appeal_filed' => false,
            'created_at' => now()->subMonth(),
        ]);

        $sanction = Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $case->student_id,
            'sanction_type' => 'probation',
            'status' => 'in_progress',
            'due_date' => today()->subDays($endedDaysAgo)->toDateString(),
        ]);

        $sanction->forceFill(['created_at' => now()->subMonth()])->saveQuietly();

        return $sanction->fresh();
    }

    /**
     * @return array{0: User, 1: User, 2: DisciplinaryCase}
     */
    private function context(string $caseNumber, int $category = 1): array
    {
        $officer = $this->user('discipline_officer', "officer-{$caseNumber}@example.test");
        $student = $this->user('student', "student-{$caseNumber}@example.test");

        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'under_review',
            'prior_offense_count' => 0,
            'assigned_sanction_category' => $category,
        ]);

        return [$officer, $student, $case];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
