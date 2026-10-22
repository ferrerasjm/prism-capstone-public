<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\ScheduledReport;
use App\Models\StudentMonitoringFlag;
use App\Models\User;
use App\Services\ReportDocumentBuilder;
use App\Services\ReportService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_catalog_links_use_view_action_label(): void
    {
        foreach (['discipline_officer' => 'officer', 'discipline_coordinator' => 'coordinator'] as $role => $prefix) {
            $response = $this->actingAs($this->user($role))
                ->get(route($prefix.'.reports.index'));

            $response
                ->assertOk()
                ->assertDontSee('Recently Generated')
                ->assertSee('View');
        }
    }

    public function test_report_pages_show_export_before_print_for_each_reporting_role(): void
    {
        foreach (['discipline_officer' => 'officer', 'discipline_coordinator' => 'coordinator'] as $role => $prefix) {
            $this->actingAs($this->user($role))
                ->get(route($prefix.'.reports.monthly-violation'))
                ->assertOk()
                ->assertSeeInOrder(['Export', 'Print']);
        }
    }

    public function test_print_action_returns_the_same_pdf_layout_used_for_export(): void
    {
        foreach (['discipline_officer' => 'officer', 'discipline_coordinator' => 'coordinator'] as $role => $prefix) {
            $response = $this->actingAs($this->user($role))
                ->get(route($prefix.'.reports.print', [
                    'type' => 'monthly_violation_summary',
                    'year' => (int) today()->year,
                ]));

            $response->assertOk();
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
            $this->assertStringStartsWith('%PDF', $response->getContent());
        }
    }

    public function test_generate_action_adds_a_downloadable_entry_to_report_history(): void
    {
        Storage::fake('local');

        foreach (['discipline_officer' => 'officer', 'discipline_coordinator' => 'coordinator'] as $role => $prefix) {
            $user = $this->user($role);
            $year = (int) today()->year;

            $this->actingAs($user)
                ->get(route($prefix.'.reports.monthly-violation', [
                    'year' => $year,
                    'generate' => 1,
                ]))
                ->assertRedirect(route($prefix.'.reports.monthly-violation', ['year' => $year]));

            $report = ScheduledReport::query()
                ->where('generated_by', $user->id)
                ->latest('id')
                ->firstOrFail();

            $this->assertSame('monthly_violation_summary', $report->report_type);
            Storage::disk('local')->assertExists($report->file_path);
        }
    }

    public function test_officer_can_generate_and_download_a_real_case_summary_excel_workbook(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->actingAs($officer)->post(
            route('officer.reports.export', 'case_summary'),
            ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]
        );

        $report = ScheduledReport::firstOrFail();
        $response->assertDownload();
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertStringEndsWith('.xlsx', $report->file_path);
        $this->assertSame($officer->id, $report->generated_by);
    }

    public function test_excel_export_contains_styled_sheets_with_matching_report_data(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = $this->offense('MINOR-CSV-STRUCTURED');
        $case = DisciplinaryCase::create([
            'case_number' => 'CSV-STRUCTURED-001',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'status' => 'resolved',
            'prior_offense_count' => 0,
            'resolved_at' => now(),
        ]);
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->post(
            route('officer.reports.export', 'case_summary'),
            ['from' => today()->toDateString(), 'to' => today()->toDateString(), 'format' => 'xlsx']
        )->assertDownload();

        $report = ScheduledReport::firstOrFail();
        $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));
        $summary = $workbook->getSheetByName('Report Summary');
        $offenses = $workbook->getSheetByName('Confirmed Offense Findings');

        $this->assertNotNull($summary);
        $this->assertSame('Case Summary Report', $summary->getCell('A4')->getValue());
        $this->assertSame('1E3276', $summary->getStyle('A1')->getFill()->getStartColor()->getRGB());
        $this->assertNotNull($offenses);
        $this->assertSame(['Code', 'Offense', 'Type', 'Count'], array_slice($offenses->rangeToArray('A4:D4')[0], 0, 4));
        $this->assertSame('MINOR-CSV-STRUCTURED', $offenses->getCell('A5')->getValue());
        $this->assertSame('MINOR-CSV-STRUCTURED title', $offenses->getCell('B5')->getValue());
        $this->assertSame('Minor', $offenses->getCell('C5')->getValue());
        $this->assertSame(1, $offenses->getCell('D5')->getValue());
        $this->assertSame('A5', $offenses->getFreezePane());

        $workbook->disconnectWorksheets();
    }

    public function test_every_report_document_has_consistent_headers_and_row_widths(): void
    {
        $student = $this->user('student');
        $today = today()->toDateString();
        $documents = app(ReportDocumentBuilder::class);
        $reports = [
            'monthly_violation_summary' => ['year' => (int) today()->year],
            'case_summary' => ['from' => $today, 'to' => $today],
            'student_case_report' => ['student_id' => $student->id],
            'us_compliance' => ['from' => $today, 'to' => $today],
            'upcc_case_report' => ['from' => $today, 'to' => $today],
            'registration_hold_report' => ['active_only' => true],
            'escalation_report' => ['from' => $today, 'to' => $today, 'status' => 'all'],
            'risk_analytics_report' => ['from' => $today, 'to' => $today],
        ];

        foreach ($reports as $type => $filters) {
            $document = $documents->build($type, $filters);

            $this->assertNotSame('', $document['title'], $type);
            $this->assertNotEmpty($document['sections'], $type);
            foreach ($document['sections'] as $section) {
                $this->assertNotEmpty($section['headers'], "{$type}: {$section['title']}");
                foreach ($section['rows'] as $row) {
                    $this->assertCount(count($section['headers']), $row, "{$type}: {$section['title']}");
                }
            }
        }
    }

    public function test_every_report_type_renders_as_a_dedicated_pdf_document(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $today = today()->toDateString();
        $reports = [
            'monthly_violation_summary' => ['year' => (int) today()->year],
            'case_summary' => ['from' => $today, 'to' => $today],
            'student_case_report' => ['student_id' => $student->id],
            'us_compliance' => ['from' => $today, 'to' => $today],
            'upcc_case_report' => ['from' => $today, 'to' => $today],
            'registration_hold_report' => ['active_only' => true],
            'escalation_report' => ['from' => $today, 'to' => $today, 'status' => 'all'],
            'risk_analytics_report' => ['from' => $today, 'to' => $today],
        ];
        $this->withoutMiddleware(PreventRequestForgery::class);

        foreach ($reports as $type => $filters) {
            $this->actingAs($officer)->post(
                route('officer.reports.export', $type),
                [...$filters, 'format' => 'pdf']
            )->assertDownload();

            $report = ScheduledReport::query()->where('report_type', $type)->latest('id')->firstOrFail();
            $contents = Storage::disk('local')->get($report->file_path);
            $this->assertStringStartsWith('%PDF', $contents, $type);
            $this->assertGreaterThan(1000, strlen($contents), $type);
        }
    }

    public function test_registration_hold_excel_export_preserves_the_all_records_filter(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        StudentMonitoringFlag::create([
            'student_id' => $student->id,
            'flagged_by' => $officer->id,
            'flag_type' => 'registration_hold',
            'flag_reason' => 'Completed hold retained for reporting.',
            'is_active' => false,
            'created_at' => now()->subDay(),
            'deactivated_by' => $officer->id,
            'deactivated_at' => now(),
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->post(
            route('officer.reports.export', 'registration_hold_report'),
            ['active_only' => '0', 'format' => 'xlsx']
        )->assertDownload();

        $report = ScheduledReport::firstOrFail();
        $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));
        $summaryValues = collect($workbook->getSheetByName('Report Summary')->toArray())->flatten();
        $holds = $workbook->getSheetByName('Registration Hold Details');

        $this->assertTrue($summaryValues->contains('All holds'));
        $this->assertSame('Completed hold retained for reporting.', $holds->getCell('D5')->getValue());
        $this->assertSame('Lifted', $holds->getCell('E5')->getValue());
        $workbook->disconnectWorksheets();
    }

    public function test_officer_can_generate_and_download_a_case_summary_pdf(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this->actingAs($officer)->post(
            route('officer.reports.export', 'case_summary'),
            [
                'from' => today()->startOfMonth()->toDateString(),
                'to' => today()->toDateString(),
                'format' => 'pdf',
            ]
        );

        $report = ScheduledReport::firstOrFail();
        $response->assertDownload();
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertStringEndsWith('.pdf', $report->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
        $this->assertSame($officer->id, $report->generated_by);
    }

    public function test_student_cannot_access_officer_report_exports(): void
    {
        $student = $this->user('student');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($student)->post(
            route('officer.reports.export', 'case_summary'),
            ['from' => today()->toDateString(), 'to' => today()->toDateString()]
        )->assertForbidden();
    }

    public function test_offense_report_counts_every_confirmed_finding_in_a_multi_offense_case(): void
    {
        $student = $this->user('student');
        $officer = $this->user('discipline_officer');
        $first = $this->offense('MINOR-CSV-1');
        $second = $this->offense('MINOR-CSV-2');
        $case = DisciplinaryCase::create([
            'case_number' => 'REPORT-CASE-1',
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);

        foreach ([$first, $second] as $offense) {
            $case->caseOffenses()->create([
                'offense_catalog_id' => $offense->id,
                'status' => 'confirmed',
                'confirmed_by' => $officer->id,
                'confirmed_at' => now(),
            ]);
        }

        $data = (new ReportService)->caseSummary(today()->toDateString(), today()->toDateString());

        $this->assertCount(2, $data['byOffenseType']);
        $this->assertSame(2, (int) $data['byOffenseType']->sum('total'));
    }

    public function test_monthly_report_query_is_database_portable(): void
    {
        $data = (new ReportService)->monthlyViolation((int) today()->year);

        $this->assertCount(12, $data['months']);
        $this->assertSame((int) today()->year, $data['year']);
    }

    public function test_monthly_report_identifies_three_programs_with_the_lowest_violation_counts(): void
    {
        $officer = $this->user('discipline_officer');
        $accountancy = $this->user('student');
        $engineering = $this->user('student');
        $politicalScience = $this->user('student');
        $tourism = $this->user('student');

        foreach ([
            [$accountancy, 'BS Accountancy', '2026-100001'],
            [$engineering, 'BS Computer Engineering', '2026-100002'],
            [$politicalScience, 'BA Political Science', '2026-100003'],
            [$tourism, 'BS Tourism Management', '2026-100005'],
        ] as [$student, $program, $studentNumber]) {
            $student->studentProfile()->create([
                'student_number' => $studentNumber,
                'program' => $program,
            ]);
        }

        foreach ([
            ['PROGRAM-MINOR-1', $accountancy, 'minor', 'resolved'],
            ['PROGRAM-MAJOR-1', $engineering, 'major', 'resolved'],
            ['PROGRAM-VOIDED-1', $politicalScience, 'minor', 'voided'],
        ] as [$number, $student, $type, $status]) {
            DisciplinaryCase::create([
                'case_number' => $number,
                'student_id' => $student->id,
                'assigned_to' => $officer->id,
                'case_type' => $type,
                'status' => $status,
                'prior_offense_count' => 0,
            ]);
        }

        $data = (new ReportService)->monthlyViolation((int) today()->year);

        $this->assertSame(
            ['BA Political Science', 'BS Computer Engineering', 'BS Tourism Management'],
            $data['lowestMinorPrograms']->pluck('program')->all()
        );
        $this->assertSame(
            ['BA Political Science', 'BS Accountancy', 'BS Tourism Management'],
            $data['lowestMajorPrograms']->pluck('program')->all()
        );

        $this->actingAs($officer)
            ->get(route('officer.reports.monthly-violation'))
            ->assertOk()
            ->assertSee('Programs with the Lowest Violation Counts')
            ->assertSee('Lowest Minor Violations')
            ->assertSee('Lowest Major Violations')
            ->assertSee('BA Political Science');
    }

    public function test_monthly_report_does_not_rank_programs_when_every_count_is_zero(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $student->studentProfile()->create([
            'student_number' => '2026-100004',
            'program' => 'BS Information Technology',
        ]);

        $data = (new ReportService)->monthlyViolation((int) today()->year);

        $this->assertFalse($data['hasMinorViolations']);
        $this->assertFalse($data['hasMajorViolations']);

        $this->actingAs($officer)
            ->get(route('officer.reports.monthly-violation'))
            ->assertOk()
            ->assertSee('No minor violations were recorded for any represented program')
            ->assertSee('No major violations were recorded for any represented program')
            ->assertDontSee('0 cases');
    }

    public function test_coordinator_report_pages_render_real_report_data(): void
    {
        $coordinator = $this->user('discipline_coordinator');

        $this->actingAs($coordinator)
            ->get(route('coordinator.reports.case-summary', [
                'from' => today()->toDateString(),
                'to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Case Summary Report')
            ->assertSee('Export')
            ->assertSee('PDF')
            ->assertSee('Total Cases');
    }

    public function test_coordinator_can_generate_a_private_report_export(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($coordinator)->post(
            route('coordinator.reports.export', 'case_summary'),
            ['from' => today()->toDateString(), 'to' => today()->toDateString()]
        )->assertDownload();

        $report = ScheduledReport::firstOrFail();
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertSame($coordinator->id, $report->generated_by);
    }

    public function test_escalation_report_lists_flagged_habitual_offense_cases(): void
    {
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = $this->offense('MINOR-ESC-1');
        $case = $this->flaggedEscalationCase($student, $officer, $offense);

        $this->actingAs($officer)
            ->get(route('officer.reports.escalations', [
                'from' => today()->startOfYear()->toDateString(),
                'to' => today()->toDateString(),
                'status' => 'pending',
            ]))
            ->assertOk()
            ->assertSee('Escalation / Habitual Offense Report')
            ->assertSee($case->case_number)
            ->assertSee($student->list_name)
            ->assertSee('Pending Review')
            ->assertSee('Export')
            ->assertSee('PDF');
    }

    public function test_escalation_report_can_be_exported_as_pdf(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $offense = $this->offense('MINOR-ESC-2');
        $this->flaggedEscalationCase($student, $officer, $offense);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->post(
            route('officer.reports.export', 'escalation_report'),
            [
                'from' => today()->startOfYear()->toDateString(),
                'to' => today()->toDateString(),
                'status' => 'pending',
                'format' => 'pdf',
            ]
        )->assertDownload();

        $report = ScheduledReport::firstOrFail();
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertSame('escalation_report', $report->report_type);
        $this->assertStringEndsWith('.pdf', $report->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }

    public function test_risk_analytics_report_summarizes_scores_trends_and_interventions(): void
    {
        $officer = $this->user('discipline_officer');
        $supportedStudent = $this->user('student');
        $unsupportedStudent = $this->user('student');
        $improvingStudent = $this->user('student');

        $this->riskScore($supportedStudent, 82.5, 'critical', 'increasing', [
            'escalation_flag' => ['factor_name' => 'Escalation Flag', 'triggered' => true, 'contribution' => 15],
            'major_offense_count' => ['factor_name' => 'Major Offense Severity', 'raw_value' => 3, 'contribution' => 20],
        ]);
        $this->riskScore($unsupportedStudent, 61.25, 'high', 'stable', [
            'unresolved_cases' => ['factor_name' => 'Unresolved Cases', 'raw_value' => 2, 'contribution' => 6.67],
            'non_compliant_sanctions' => ['factor_name' => 'Sanction Non-Compliance', 'raw_value' => 1, 'contribution' => 5],
        ]);
        $this->riskScore($improvingStudent, 34, 'moderate', 'decreasing', [
            'total_offense_count' => ['factor_name' => 'Total Confirmed Offense Count', 'raw_value' => 3, 'contribution' => 15],
        ]);

        RiskScoreSnapshot::create([
            'student_id' => $supportedStudent->id,
            'snapshot_type' => 'event',
            'composite_score' => 82.5,
            'previous_composite_score' => 60,
            'risk_band' => 'critical',
            'previous_risk_band' => 'high',
            'contributing_factors' => [],
            'factor_changes' => [],
            'methodology_version' => '1.0',
            'trigger' => 'case_escalation_flagged',
            'snapshot_date' => today(),
            'created_at' => now(),
        ]);
        RiskScoreSnapshot::create([
            'student_id' => $improvingStudent->id,
            'snapshot_type' => 'event',
            'composite_score' => 34,
            'previous_composite_score' => 58,
            'risk_band' => 'moderate',
            'previous_risk_band' => 'high',
            'contributing_factors' => [],
            'factor_changes' => [],
            'methodology_version' => '1.0',
            'trigger' => 'sanction_compliance_restored',
            'snapshot_date' => today(),
            'created_at' => now(),
        ]);

        RiskIntervention::create([
            'student_id' => $supportedStudent->id,
            'created_by' => $officer->id,
            'assigned_to' => $officer->id,
            'action_type' => 'staff_review',
            'action_plan' => 'Review risk drivers and assign support.',
            'risk_score' => 82.5,
            'risk_band' => 'critical',
            'methodology_version' => '1.0',
            'status' => 'in_progress',
            'follow_up_on' => today()->addWeek(),
        ]);
        RiskIntervention::create([
            'student_id' => $improvingStudent->id,
            'created_by' => $officer->id,
            'assigned_to' => $officer->id,
            'action_type' => 'monitoring_plan',
            'action_plan' => 'Monitor compliance after intervention.',
            'risk_score' => 58,
            'risk_band' => 'high',
            'methodology_version' => '1.0',
            'status' => 'completed',
            'follow_up_on' => today()->subWeek(),
            'outcome' => 'Student complied with support plan.',
            'closed_at' => now(),
            'closed_by' => $officer->id,
        ]);

        $data = (new ReportService)->riskAnalytics(
            today()->startOfYear()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(3, $data['totalAssessed']);
        $this->assertSame(1, $data['bandCounts']['moderate']);
        $this->assertSame(1, $data['bandCounts']['high']);
        $this->assertSame(1, $data['bandCounts']['critical']);
        $this->assertSame(1, $data['movementCounts']['improved']);
        $this->assertSame(1, $data['movementCounts']['worsened']);
        $this->assertSame(2, $data['interventionCoverage']['high_risk_total']);
        $this->assertSame(1, $data['interventionCoverage']['high_risk_with_active_support']);
        $this->assertSame(1, $data['interventionCoverage']['high_risk_without_active_support']);
        $this->assertSame(2, $data['interventionCoverage']['created_in_period']);
        $this->assertSame(1, $data['interventionCoverage']['completed_in_period']);
        $this->assertSame(1, $data['outcomeSummary']['improved']);
        $this->assertTrue($data['studentsNeedingSupport']->contains('student_id', $unsupportedStudent->id));

        $this->actingAs($officer)
            ->get(route('officer.reports.risk-analytics', [
                'from' => today()->startOfYear()->toDateString(),
                'to' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Risk Analytics Report')
            ->assertSee('Students Assessed')
            ->assertSee('Escalation Flag')
            ->assertSee('Students Requiring Support')
            ->assertSee($unsupportedStudent->list_name)
            ->assertSee('Follow-up Outcomes')
            ->assertSee('Export')
            ->assertSee('PDF');
    }

    public function test_risk_analytics_report_can_be_exported_as_pdf(): void
    {
        Storage::fake('local');
        $officer = $this->user('discipline_officer');
        $student = $this->user('student');
        $this->riskScore($student, 72, 'high', 'increasing', [
            'escalation_flag' => ['factor_name' => 'Escalation Flag', 'triggered' => true, 'contribution' => 15],
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->post(
            route('officer.reports.export', 'risk_analytics_report'),
            [
                'from' => today()->startOfYear()->toDateString(),
                'to' => today()->toDateString(),
                'format' => 'pdf',
            ]
        )->assertDownload();

        $report = ScheduledReport::firstOrFail();
        Storage::disk('local')->assertExists($report->file_path);
        $this->assertSame('risk_analytics_report', $report->report_type);
        $this->assertStringEndsWith('.pdf', $report->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($report->file_path));
    }

    public function test_risk_analytics_score_distribution_and_factors_respect_date_filter(): void
    {
        $currentStudent = $this->user('student');
        $oldStudent = $this->user('student');

        $this->riskScore($currentStudent, 25, 'low', 'stable', [
            'recent_factor' => [
                'factor_name' => 'Recent Factor',
                'triggered' => true,
                'contribution' => 10,
            ],
        ]);

        $oldScore = $this->riskScore($oldStudent, 80, 'critical', 'increasing', [
            'old_factor' => [
                'factor_name' => 'Out-of-Period Factor',
                'triggered' => true,
                'contribution' => 50,
            ],
        ]);
        $oldScore->forceFill(['computed_at' => today()->subYear()])->save();

        $data = (new ReportService)->riskAnalytics(
            today()->toDateString(),
            today()->toDateString()
        );

        $this->assertSame(1, $data['totalAssessed']);
        $this->assertSame(1, $data['bandCounts']['low']);
        $this->assertSame(0, $data['bandCounts']['critical']);
        $this->assertSame(['Recent Factor'], $data['topFactors']->pluck('factor_name')->all());
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Report',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function offense(string $code): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => 'minor',
            'title' => "{$code} title",
            'description' => 'Report test offense.',
            'keywords' => ['report'],
            'created_at' => now(),
        ]);
    }

    private function flaggedEscalationCase(User $student, User $officer, OffenseCatalog $offense): DisciplinaryCase
    {
        $case = DisciplinaryCase::create([
            'case_number' => 'ESC-REPORT-'.$offense->code,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'status' => 'open',
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 2,
            'escalation_flagged' => true,
            'escalation_flag_reason' => 'Third offense of the same type in the current escalation cycle. Escalation to Major Offense (Habitual Disregard) recommended.',
        ]);

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => 2,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        return $case;
    }

    private function riskScore(User $student, float $score, string $band, string $trend, array $factors): RiskScore
    {
        return RiskScore::create([
            'student_id' => $student->id,
            'composite_score' => $score,
            'risk_band' => $band,
            'trend' => $trend,
            'contributing_factors' => $factors,
            'computed_at' => now(),
        ]);
    }
}
