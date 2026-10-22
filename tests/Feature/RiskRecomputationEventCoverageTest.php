<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\OffenseCatalog;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\UsAssignment;
use App\Models\User;
use App\Services\CaseService;
use App\Services\CoordinatorCaseService;
use App\Services\IncidentReportService;
use App\Services\RiskScoringService;
use App\Services\USAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RiskRecomputationEventCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_creation_and_immediate_void_recompute_risk(): void
    {
        $officer = $this->user('discipline_officer', 'case-event-officer@example.test');
        $student = $this->user('student', 'case-event-student@example.test');
        $report = IncidentReport::create([
            'report_number' => 'IR-RISK-EVENT-001',
            'source_type' => 'direct_encoding',
            'reported_by_user_id' => $officer->id,
            'student_id' => $student->id,
            'date_of_incident' => today(),
            'description' => 'Controlled event-driven risk recomputation test report.',
            'status' => 'under_review',
        ]);

        $case = (new IncidentReportService)->convertToCase($report, $officer)[0];

        $this->assertSame('3.33', RiskScore::where('student_id', $student->id)->value('composite_score'));
        $this->assertSame('case_created', $this->latestSnapshot($student)->trigger);

        $voided = (new CaseService)->requestVoid(
            $case,
            'Duplicate report confirmed during initial review.',
            $officer->id
        );

        $this->assertTrue($voided);
        $this->assertSame('0.00', RiskScore::where('student_id', $student->id)->value('composite_score'));
        $this->assertSame('case_voided', $this->latestSnapshot($student)->trigger);
        $this->assertSame(2, RiskScoreSnapshot::where('student_id', $student->id)->count());
    }

    public function test_coordinator_approved_void_recomputes_risk(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'void-coordinator@example.test');
        $student = $this->user('student', 'void-student@example.test');
        $case = DisciplinaryCase::create([
            'case_number' => 'DC-RISK-VOID-001',
            'student_id' => $student->id,
            'case_type' => 'major',
            'status' => 'pending_resolution',
            'escalation_flagged' => true,
            'void_requires_coordinator_approval' => true,
            'prior_offense_count' => 0,
        ]);
        app(RiskScoringService::class)->computeForStudent($student->id, 'manual_recompute', $coordinator->id);

        (new CoordinatorCaseService)->approveVoid($case, $coordinator->id);

        $this->assertSame('voided', $case->fresh()->status);
        $this->assertSame('0.00', RiskScore::where('student_id', $student->id)->value('composite_score'));
        $this->assertSame('case_voided', $this->latestSnapshot($student)->trigger);
    }

    public function test_new_escalation_flag_recomputes_risk_immediately(): void
    {
        $officer = $this->user('discipline_officer', 'escalation-officer@example.test');
        $student = $this->user('student', 'escalation-student@example.test');
        $minor = $this->offense('MINOR-RISK-EVENT', 'minor');

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);
        $current = DisciplinaryCase::create([
            'case_number' => 'DC-RISK-ESC-003',
            'student_id' => $student->id,
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 2,
        ]);

        $this->actingAs($officer)
            ->post(route('officer.cases.confirm-offense', $current), [
                'offense_id' => $minor->id,
                'override_reason' => 'Controlled test classification.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($current->fresh()->escalation_flagged);
        $snapshot = $this->latestSnapshot($student);
        $this->assertSame('case_escalation_flagged', $snapshot->trigger);
        $this->assertTrue($snapshot->contributing_factors['escalation_flag']['triggered']);
        $this->assertSame('Case flagged for escalation', $snapshot->triggerLabel());
    }

    public function test_restored_university_service_compliance_recomputes_risk(): void
    {
        $officer = $this->user('discipline_officer', 'compliance-officer@example.test');
        $supervisor = $this->user('us_supervisor', 'compliance-supervisor@example.test');
        $student = $this->user('student', 'compliance-student@example.test');
        $case = DisciplinaryCase::create([
            'case_number' => 'DC-RISK-COMPLIANCE-001',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'resolved_at' => now(),
            'prior_offense_count' => 0,
        ]);
        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 8,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => today()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);
        Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'non_compliant',
        ]);
        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today(),
            'status' => 'non_compliant',
        ]);
        app(RiskScoringService::class)->computeForStudent(
            $student->id,
            'sanction_non_compliance',
            $officer->id
        );

        (new USAssignmentService)->logSession($assignment, [
            'date_of_service' => today()->toDateString(),
            'hours_rendered' => 8,
            'log_method' => 'manual_web',
        ], $supervisor->id);

        $this->assertSame('completed', $assignment->fresh()->status);
        $this->assertSame('completed', Sanction::firstOrFail()->status);
        $this->assertSame('0.00', RiskScore::where('student_id', $student->id)->value('composite_score'));
        $this->assertSame('sanction_compliance_restored', $this->latestSnapshot($student)->trigger);
    }

    public function test_partial_service_hours_do_not_clear_non_compliance(): void
    {
        $officer = $this->user('discipline_officer', 'partial-compliance-officer@example.test');
        $supervisor = $this->user('us_supervisor', 'partial-compliance-supervisor@example.test');
        $student = $this->user('student', 'partial-compliance-student@example.test');
        $case = DisciplinaryCase::create([
            'case_number' => 'DC-RISK-COMPLIANCE-002',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'resolved_at' => now(),
            'prior_offense_count' => 0,
        ]);
        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 8,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => today()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);
        Sanction::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'sanction_type' => 'university_service',
            'status' => 'non_compliant',
        ]);
        $assignment = UsAssignment::create([
            'case_resolution_id' => $resolution->id,
            'student_id' => $student->id,
            'supervisor_id' => $supervisor->id,
            'assigned_by' => $officer->id,
            'office_name' => 'Library',
            'total_hours_required' => 8,
            'hours_rendered' => 0,
            'start_date' => today()->subWeek(),
            'target_completion_date' => today()->subDay(),
            'status' => 'non_compliant',
        ]);

        (new USAssignmentService)->logSession($assignment, [
            'date_of_service' => today()->toDateString(),
            'hours_rendered' => 4,
            'log_method' => 'manual_web',
        ], $supervisor->id);

        $this->assertSame('non_compliant', $assignment->fresh()->status);
        $this->assertSame('non_compliant', Sanction::firstOrFail()->status);
    }

    private function latestSnapshot(User $student): RiskScoreSnapshot
    {
        return RiskScoreSnapshot::where('student_id', $student->id)->latest('id')->firstOrFail();
    }

    private function resolvedMinorCase(
        User $student,
        User $officer,
        OffenseCatalog $offense,
        int $sequence
    ): void {
        $case = DisciplinaryCase::create([
            'case_number' => "DC-RISK-ESC-00{$sequence}",
            'student_id' => $student->id,
            'case_type' => 'minor',
            'case_type_locked' => true,
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'confirmed_offense_id' => $offense->id,
            'resolved_at' => now(),
            'prior_offense_count' => $sequence - 1,
        ]);
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'prior_offense_count' => $sequence - 1,
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
    }

    private function offense(string $code, string $type): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => 'Risk event test offense',
            'description' => 'Used to verify event-driven risk recomputation.',
            'keywords' => ['risk'],
            'created_at' => now(),
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Risk',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
