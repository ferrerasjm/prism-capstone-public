<?php

declare(strict_types=1);

namespace Tests\Feature\Integration;

use App\Models\AuditLog;
use App\Models\CaseNotice;
use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\ScheduledReport;
use App\Models\StudentProfile;
use App\Models\UpccReferral;
use App\Models\User;
use App\Notifications\CoordinatorCaseNotification;
use App\Notifications\NteOverdueNotification;
use App\Notifications\NteOverdueStudentNotification;
use App\Notifications\UpccDecisionAvailableToComplainantNotification;
use App\Notifications\UpccDecisionNotification;
use App\Services\CaseService;
use App\Services\RiskScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * INTEGRATION TEST GROUP 4 — UPCC Major-Offense Proceedings, Behavioural Risk
 * Scoring and Reporting.
 *
 * Exercises the seam between the officer-to-coordinator handoff, the UPCC
 * conference lifecycle (CoordinatorCaseService + MajorOffenseWorkflow deadlines
 * + hearing notices + student/complainant notifications), the appeal-window
 * scheduler command, RiskScoringService aggregation across case and sanction
 * records, the human-reviewed RiskInterventionService, and the report export
 * pipeline that reads those persisted records back out.
 *
 * Run only this group:
 *   php artisan test tests/Feature/Integration/UpccRiskAndReportingIntegrationTest.php
 */
class UpccRiskAndReportingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Several scenarios advance Carbon::setTestNow() across calendar days (a
        // conference date, an appeal window) while staying authenticated across
        // requests. EnforceIdleSessionTimeout measures that same wall-clock gap
        // and would otherwise sign the actor out mid-scenario; idle-timeout
        // behavior itself is covered by SessionLifetimeTest, not here.
        config()->set('session.lifetime', 20160);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * IT-04-01 — Officer forwarding ↔ coordinator UPCC scheduling ↔ hearing notice
     * ↔ decision recording ↔ appeal-window distribution and notification fan-out.
     */
    public function test_a_forwarded_major_case_runs_through_scheduling_decision_and_appeal_window_distribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 08:00:00'));

        [$coordinator, $officer, $student, $reporter, $case] = $this->majorCaseUnderReview();

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.forward', $case), [
                'reason' => 'Category 3 sanction requires University Panel on Case Conference proceedings.',
            ])
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHas('success');

        $case->refresh();

        $this->assertSame('pending_upcc', $case->status);
        $this->assertTrue((bool) $case->forwarded_to_coordinator);
        $this->assertDatabaseHas('notifications', [
            'type' => CoordinatorCaseNotification::class,
            'notifiable_id' => $coordinator->id,
        ]);

        $conferenceAt = Carbon::parse('2026-08-24 09:00:00');

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.schedule-upcc', $case), [
                'scheduled_conference_date' => $conferenceAt->toDateString(),
                'scheduled_conference_time' => '09:00',
                'conference_location' => 'SDO Conference Room',
                'proceedings_type' => 'full_hearing',
                'sdao_head_name' => 'Atty. Lourdes Marquez',
                'dean_or_program_chair_name' => 'Dr. Elena Bautista',
                'cross_program_faculty_name' => 'Prof. Ramon Ledesma',
                'student_representative_name' => 'Kim Villaraza',
                'presiding_officer_name' => 'Atty. Lourdes Marquez',
            ])
            ->assertRedirect(route('coordinator.cases.progress', $case))
            ->assertSessionHas('success');

        $referral = UpccReferral::where('case_id', $case->id)->firstOrFail();
        $hearingNotice = CaseNotice::where('case_id', $case->id)
            ->where('notice_type', 'notice_of_hearing')
            ->firstOrFail();

        $this->assertSame('scheduled', $referral->status);
        $this->assertSame($coordinator->id, $referral->referred_by);
        $this->assertSame('SDO Conference Room', $hearingNotice->scheduled_location);
        $this->assertSame(
            $conferenceAt->toDateTimeString(),
            $hearingNotice->scheduled_at->toDateTimeString()
        );
        $this->assertSame($referral->id, $hearingNotice->additional_content['upcc_referral_id']);

        // The conference date arrives; attendance can now be recorded.
        Carbon::setTestNow($conferenceAt->copy()->addHour());

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.record-attendance', $case), ['attended' => '1'])
            ->assertRedirect(route('coordinator.cases.progress', $case));

        $this->assertSame('in_progress', $referral->fresh()->status);
        $this->assertTrue((bool) $referral->fresh()->student_attended);

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.record-decision', $case), [
                'decision_summary' => 'The panel found the student liable and recommends a Category 3 sanction.',
                'outcome' => 'violation_established',
            ])
            ->assertRedirect(route('coordinator.cases.progress', $case));

        $this->assertSame('concluded', $referral->fresh()->status);

        // Handbook VI.G.3.b.5-6: the decision cannot reach the student until the
        // President's Council has acted on the UPCC's recommendation.
        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.distribute-decision', $case))
            ->assertSessionHas('error');

        $this->assertNull($referral->fresh()->decision_distributed_at);

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.record-council-action', $case), [
                'action' => 'approved',
                'notes' => 'Council affirmed the panel recommendation.',
            ])
            ->assertRedirect(route('coordinator.cases.progress', $case));

        $this->assertSame('approved', $referral->fresh()->presidents_council_action);

        $this->actingAs($coordinator)
            ->post(route('coordinator.cases.distribute-decision', $case))
            ->assertRedirect(route('coordinator.cases.progress', $case))
            ->assertSessionHas('success');

        $referral->refresh();

        $this->assertNotNull($referral->decision_distributed_at);
        $this->assertSame(today()->toDateString(), $referral->appeal_window_start->toDateString());
        $this->assertSame(
            \App\Services\MajorOffenseWorkflow::appealDeadline(today())->toDateString(),
            $referral->appeal_window_end->toDateString(),
            'The appeal window must use the 5-working-day rule from Student Handbook VI.G.4.'
        );
        $this->assertDatabaseHas('notifications', [
            'type' => UpccDecisionNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => UpccDecisionAvailableToComplainantNotification::class,
            'notifiable_id' => $reporter->id,
        ]);
    }

    /**
     * IT-04-02 — Scheduled automation pass ↔ NTE deadline module ↔ UPCC appeal
     * window module ↔ notification fan-out, run as one nightly sweep.
     *
     * Consolidates the per-command checks into the scheduler's actual behaviour:
     * a single pass must advance each module independently and stay idempotent.
     */
    public function test_one_nightly_automation_pass_advances_each_module_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 06:00:00'));

        [$coordinator, $officer, $expiredStudent, , $expiredCase] = $this->majorCaseUnderReview('IT0402A', 'expired');
        [, , $openStudent, , $openCase] = $this->majorCaseUnderReview('IT0402B', 'open', $coordinator, $officer);

        $expiredReferral = $this->distributedReferral($expiredCase, $coordinator, today()->subDay());
        $openReferral = $this->distributedReferral($openCase, $coordinator, today()->addDays(3));

        $expiredCase->update(['status' => 'pending_upcc']);
        $openCase->update(['status' => 'pending_upcc']);

        // A separate case awaiting an NTE response, already past its deadline.
        $nteStudent = $this->student('it0402-nte@students.nu-clark.edu.ph', '2026-40021');
        $nteOffense = $this->offense('MINOR-21', 'minor', 'Loitering in restricted areas');
        $nteCase = DisciplinaryCase::create([
            'case_number' => 'DC-2026-IT0402C',
            'student_id' => $nteStudent->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'case_type_locked' => true,
            'status' => 'pending_nte',
            'confirmed_offense_id' => $nteOffense->id,
            'prior_offense_count' => 0,
        ]);
        CaseNotice::create([
            'case_id' => $nteCase->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now()->subDays(7),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '48',
            'incident_date' => today()->subDays(10)->toDateString(),
            'response_deadline' => today()->subDay()->toDateString(),
            'is_voided' => false,
            'created_at' => now()->subDays(7),
        ]);

        // The nightly pass, in the order routes/console.php schedules it.
        $this->artisan('cases:finalize-expired-appeals')
            ->expectsOutput('Finalized 1 expired UPCC appeal window(s).')
            ->assertSuccessful();
        $this->artisan('cases:process-nte-deadlines')
            ->expectsOutput('Sent 0 NTE reminder(s); flagged 1 overdue NTE(s).')
            ->assertSuccessful();

        $expiredReferral->refresh();
        $openReferral->refresh();

        $this->assertNotNull($expiredReferral->decision_final_at);
        $this->assertNotNull($expiredReferral->records_filed_at);
        $this->assertSame('pending_resolution', $expiredCase->fresh()->status);
        $this->assertNull($openReferral->decision_final_at);
        $this->assertSame('pending_upcc', $openCase->fresh()->status);

        $overdueNotice = CaseNotice::where('case_id', $nteCase->id)->firstOrFail();
        $this->assertNotNull($overdueNotice->overdue_flagged_at);

        $this->assertDatabaseHas('notifications', [
            'type' => UpccDecisionNotification::class,
            'notifiable_id' => $expiredStudent->id,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'type' => UpccDecisionNotification::class,
            'notifiable_id' => $openStudent->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => NteOverdueStudentNotification::class,
            'notifiable_id' => $nteStudent->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => NteOverdueNotification::class,
            'notifiable_id' => $officer->id,
        ]);

        $notificationCount = DatabaseNotification::count();

        // The next night's pass must not re-finalize or re-flag anything.
        $this->artisan('cases:finalize-expired-appeals')
            ->expectsOutput('Finalized 0 expired UPCC appeal window(s).')
            ->assertSuccessful();
        $this->artisan('cases:process-nte-deadlines')
            ->expectsOutput('Sent 0 NTE reminder(s); flagged 0 overdue NTE(s).')
            ->assertSuccessful();

        $this->assertSame($notificationCount, DatabaseNotification::count());
    }

    /**
     * IT-04-03 — Case, escalation and sanction records ↔ RiskScoringService
     * weighted aggregation ↔ explainable risk snapshot.
     */
    public function test_case_and_sanction_history_aggregate_into_an_explainable_weighted_risk_score(): void
    {
        [$officer, $student] = $this->riskHistory('IT0403');

        $score = app(RiskScoringService::class)
            ->computeForStudent($student->id, 'manual_recompute', $officer->id);

        $factors = $score->contributing_factors;

        $this->assertSame(2, $factors['total_offense_count']['raw_value']);
        $this->assertSame(2, $factors['major_offense_count']['raw_value']);
        $this->assertTrue($factors['recent_violation']['triggered']);
        $this->assertTrue($factors['escalation_flag']['triggered']);
        $this->assertSame(1, $factors['unresolved_cases']['raw_value']);
        $this->assertSame(1, $factors['non_compliant_sanctions']['raw_value']);

        $this->assertEqualsWithDelta(61.67, (float) $score->composite_score, 0.01);
        $this->assertSame('high', $score->risk_band);

        $snapshot = RiskScoreSnapshot::where('student_id', $student->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('manual_recompute', $snapshot->trigger);
        $this->assertSame($officer->id, $snapshot->triggered_by);
        $this->assertSame(config('risk_scoring.methodology_version'), $snapshot->methodology_version);
        $this->assertNotEmpty($snapshot->factor_changes);
        $this->assertSame(
            (float) $score->composite_score,
            (float) $snapshot->composite_score
        );
    }

    /**
     * IT-04-04 — Multi-module case workflow ↔ AuditLog trail ↔ coordinator
     * audit-log viewer ↔ secret redaction.
     *
     * Verifies that actions taken across separate modules land in one coherent,
     * attributable activity trail that the oversight role can actually read back.
     */
    public function test_workflow_actions_across_modules_produce_one_readable_and_redacted_activity_trail(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));

        $coordinator = $this->user('discipline_coordinator', 'it0404-coordinator@nu-clark.edu.ph');
        $officer = $this->user('discipline_officer', 'it0404-officer@nu-clark.edu.ph');
        $student = $this->student('it0404-student@students.nu-clark.edu.ph', '2026-40041');
        $offense = $this->offense('MAJOR-41', 'major', 'Serious misconduct');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-IT0404',
            'incident_report_id' => null,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => null,
            'case_type_locked' => false,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);

        // Three different modules act on the same case.
        app(CaseService::class)->confirmOffense($case, $offense->id, $officer->id);
        app(CaseService::class)->confirmSanction($case->fresh(), 2, $officer->id);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.issue-nte', $case), [
                'incident_date' => today()->subDays(3)->toDateString(),
            ])
            ->assertSessionHas('success');

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.void', $case), [
                'reason' => 'Filed against the wrong student record; reissuing under the correct case.',
            ])
            ->assertSessionHas('success');

        $modules = AuditLog::query()->pluck('module')->unique()->values();

        $this->assertTrue($modules->contains('cases'));
        $this->assertTrue($modules->contains('case_notices'));
        $this->assertSame(
            0,
            AuditLog::whereNull('user_id')->count(),
            'Every workflow action must be attributable to the acting user.'
        );

        $this->assertGreaterThanOrEqual(
            1,
            AuditLog::where('module', 'case_notices')->where('action', 'create')->count()
        );
        $this->assertGreaterThanOrEqual(
            1,
            AuditLog::where('module', 'cases')->where('action', 'void')->count()
        );

        // The coordinator's activity log opens over that same trail.
        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index'))
            ->assertOk();

        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index', ['module' => 'case_notices', 'action' => 'create']))
            ->assertOk();

        // Secrets recorded by other modules are redacted before they are displayed.
        AuditLog::create([
            'user_id' => $officer->id,
            'action' => 'update',
            'module' => 'us_assignments',
            'record_id' => $case->id,
            'new_values' => ['totp_secret' => 'JBSWY3DPEHPK3PXP', 'office_name' => 'Library'],
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index', ['module' => 'us_assignments']))
            ->assertOk()
            ->assertSee('[REDACTED]')
            ->assertDontSee('JBSWY3DPEHPK3PXP');

        // A non-oversight role cannot reach the activity log at all.
        $this->actingAs($officer)
            ->get(route('coordinator.audit-logs.index'))
            ->assertForbidden();
    }

    /**
     * IT-04-05 — Coordinator report screen ↔ ReportService/ReportDocumentBuilder
     * ↔ ReportExportService ↔ storage disk ↔ ScheduledReport archive record.
     */
    public function test_exporting_the_escalation_report_reads_live_case_records_and_archives_a_downloadable_file(): void
    {
        Storage::fake('local');
        Carbon::setTestNow(Carbon::parse('2026-08-17 09:00:00'));

        $coordinator = $this->user('discipline_coordinator', 'it0405-coordinator@nu-clark.edu.ph');
        $officer = $this->user('discipline_officer', 'it0405-officer@nu-clark.edu.ph');
        $student = $this->student('it0405-student@students.nu-clark.edu.ph', '2026-40005');
        $offense = $this->offense('MINOR-01', 'minor', 'Non-wearing of the prescribed uniform');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-IT0405',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'case_type_locked' => false,
            'status' => 'open',
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 2,
            'escalation_flagged' => true,
            'escalation_flag_reason' => 'Third offense of the same type in the current escalation cycle.',
        ]);
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 2,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->post(route('coordinator.reports.export', 'escalation_report'), [
                'from' => today()->subMonth()->toDateString(),
                'to' => today()->toDateString(),
                'status' => 'pending',
                'format' => 'xlsx',
            ])
            ->assertOk();

        $report = ScheduledReport::firstOrFail();

        $this->assertSame('escalation_report', $report->report_type);
        $this->assertSame($coordinator->id, $report->generated_by);
        $this->assertStringEndsWith('.xlsx', $report->file_path);
        Storage::disk('local')->assertExists($report->file_path);

        $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));
        $details = $workbook->getSheetByName('Escalation Details');

        $this->assertNotNull($details);
        $this->assertSame('DC-2026-IT0405', $details->getCell('A5')->getValue());
        $this->assertSame('2026-40005', (string) $details->getCell('B5')->getValue());
        $workbook->disconnectWorksheets();

        $this->actingAs($coordinator)
            ->get(route('coordinator.reports.download', $report))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );
    }

    /** @return array{0: User, 1: User, 2: User, 3: User, 4: DisciplinaryCase} */
    private function majorCaseUnderReview(
        string $suffix = 'IT0401',
        string $tag = 'primary',
        ?User $coordinator = null,
        ?User $officer = null,
    ): array {
        $coordinator ??= $this->user('discipline_coordinator', "{$tag}-coordinator@nu-clark.edu.ph");
        $officer ??= $this->user('discipline_officer', "{$tag}-officer@nu-clark.edu.ph");
        $reporter = $this->user('reporting_user', "{$tag}-reporter@nu-clark.edu.ph");
        $student = $this->student("{$tag}-student@students.nu-clark.edu.ph", '2026-4'.substr(md5($tag), 0, 4));

        $offense = $this->offense('MAJOR-'.strtoupper(substr(md5($suffix), 0, 4)), 'major', 'Serious misconduct');

        $report = IncidentReport::create([
            'report_number' => "IR-2026-{$suffix}",
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $reporter->id,
            'reported_by_name' => $reporter->full_name,
            'student_id' => $student->id,
            'date_of_incident' => today()->subDays(10)->toDateString(),
            'description' => 'The student was documented committing serious misconduct inside the laboratory.',
            'violation_type' => 'Others',
            'status' => 'converted_to_case',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}",
            'incident_report_id' => $report->id,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'under_review',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => 3,
            'prior_offense_count' => 0,
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 0,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now()->subDays(6),
        ]);

        return [$coordinator, $officer, $student, $reporter, $case];
    }

    private function distributedReferral(
        DisciplinaryCase $case,
        User $coordinator,
        Carbon $appealWindowEnd,
    ): UpccReferral {
        return UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(9),
            'scheduled_conference_at' => now()->subDays(7),
            'conference_location' => 'SDO Conference Room',
            'proceedings_type' => 'full_hearing',
            'status' => 'concluded',
            'student_attended' => true,
            'decision_summary' => 'The panel found the student liable for the charged offense.',
            'decision_issued_at' => now()->subDays(6),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now()->subDays(6),
            'appeal_window_start' => $appealWindowEnd->copy()->subDays(5)->toDateString(),
            'appeal_window_end' => $appealWindowEnd->toDateString(),
            'appeal_filed' => false,
        ]);
    }

    /** @return array{0: User, 1: User} */
    private function riskHistory(string $suffix): array
    {
        $officer = $this->user('discipline_officer', strtolower($suffix).'-risk-officer@nu-clark.edu.ph');
        $student = $this->student(strtolower($suffix).'-risk-student@students.nu-clark.edu.ph', '2026-9'.substr($suffix, -3));
        $offense = $this->offense('MAJOR-'.strtoupper(substr(md5($suffix.'risk'), 0, 4)), 'major', 'Serious misconduct');

        foreach ([1, 2] as $sequence) {
            $resolved = DisciplinaryCase::create([
                'case_number' => "DC-2026-{$suffix}-R{$sequence}",
                'student_id' => $student->id,
                'assigned_to' => $officer->id,
                'case_type' => 'major',
                'case_type_locked' => true,
                'status' => 'resolved',
                'confirmed_offense_id' => $offense->id,
                'prior_offense_count' => $sequence - 1,
                'resolved_at' => now()->subMonths($sequence),
            ]);
            $resolved->caseOffenses()->create([
                'offense_catalog_id' => $offense->id,
                'status' => 'confirmed',
                'prior_offense_count' => $sequence - 1,
                'confirmed_by' => $officer->id,
                'confirmed_at' => now()->subMonths($sequence),
            ]);

            if ($sequence === 1) {
                $resolution = CaseResolution::create([
                    'case_id' => $resolved->id,
                    'resolution_level' => 'sdo_level',
                    'intervention_category' => 2,
                    'us_hours_assigned' => 12.0,
                    'resolved_by' => $officer->id,
                    'resolved_at' => now()->subMonths($sequence),
                    'appeal_deadline' => today()->subMonths($sequence)->addDays(5)->toDateString(),
                    'appeal_filed' => false,
                    'created_at' => now()->subMonths($sequence),
                ]);

                Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $student->id,
                    'sanction_type' => 'university_service',
                    'status' => 'non_compliant',
                ]);
            }
        }

        DisciplinaryCase::create([
            'case_number' => "DC-2026-{$suffix}-OPEN",
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'case_type_locked' => false,
            'status' => 'open',
            'prior_offense_count' => 2,
            'escalation_flagged' => true,
            'escalation_flag_reason' => 'Third offense of the same type in the current escalation cycle.',
        ]);

        return [$officer, $student];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => 'Navarro',
            'first_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function student(string $email, string $studentNumber): User
    {
        $student = $this->user('student', $email);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => $studentNumber,
            'program' => 'BS Information Technology',
            'year_level' => '3',
        ]);

        return $student;
    }

    private function offense(string $code, string $type, string $title): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $title,
            'description' => $title.'.',
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '52',
            'keywords' => [strtolower($title)],
            'created_at' => now(),
        ]);
    }
}
