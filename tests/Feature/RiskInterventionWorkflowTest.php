<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\AuditLog;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\Sanction;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RiskInterventionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_record_human_reviewed_support_action_for_elevated_risk(): void
    {
        $officer = $this->user('discipline_officer', 'intervention-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'intervention-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 76, 'critical');

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer))
            ->assertRedirect()
            ->assertSessionHas('success');

        $intervention = RiskIntervention::firstOrFail();

        $this->assertSame($student->id, $intervention->student_id);
        $this->assertSame($officer->id, $intervention->created_by);
        $this->assertSame('in_progress', $intervention->status);
        $this->assertSame('76.00', $intervention->risk_score);
        $this->assertSame('critical', $intervention->risk_band);
        $this->assertSame(config('risk_scoring.methodology_version'), $intervention->methodology_version);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'risk_interventions',
            'record_id' => $intervention->id,
        ]);
        $this->assertSame(0, Sanction::count(), 'Risk support actions must not create sanctions.');
    }

    public function test_new_action_is_rejected_without_current_high_or_critical_assessment(): void
    {
        $officer = $this->user('discipline_officer', 'moderate-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'moderate-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 50, 'moderate');

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('risk_interventions', 0);
    }

    public function test_dashboard_surfaces_elevated_students_needing_review_and_open_follow_up(): void
    {
        $officer = $this->user('discipline_officer', 'dashboard-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'dashboard-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 60, 'high');

        $this->actingAs($officer)
            ->get(route('officer.risk.index'))
            ->assertOk()
            ->assertSee('Needs Review');

        $this->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer));

        $this->get(route('officer.risk.index'))
            ->assertOk()
            ->assertSee('In Progress')
            ->assertDontSee('Needs Review');
    }

    public function test_empty_dashboard_does_not_expose_a_console_command(): void
    {
        $officer = $this->user('discipline_officer', 'empty-risk-dashboard@nu-clark.edu.ph');

        $this->actingAs($officer)
            ->get(route('officer.risk.index'))
            ->assertOk()
            ->assertSee('No risk scores are available yet')
            ->assertDontSee('php artisan risk:compute');
    }

    public function test_student_risk_profile_does_not_expose_manual_recomputation(): void
    {
        $officer = $this->user('discipline_officer', 'automatic-risk-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'automatic-risk-student@students.nu-clark.edu.ph');
        // RiskDashboardController::show() only exposes students inside the officer's
        // departments, so both sides need that link for the page to be reachable.
        $this->shareDepartment($officer, $student);
        $this->riskScore($student, 40, 'moderate');

        // The profile is department-scoped, so the officer has to actually cover this
        // student: an assigned department, and a student profile it matches on.
        $department = AcademicUnit::query()->whereNotNull('scope_year_level')->firstOrFail();
        $officer->academicUnits()->sync([$department->id]);
        StudentProfile::updateOrCreate(['user_id' => $student->id], [
            'user_id' => $student->id,
            'student_number' => '2026-00042',
            'year_level' => $department->scope_year_level,
        ]);

        $this->assertFalse(Route::has('officer.risk.recompute'));

        $this->actingAs($officer)
            ->get(route('officer.risk.show', $student))
            ->assertOk()
            ->assertSee('update automatically as case and compliance records change')
            ->assertDontSee('Recompute');
    }

    public function test_only_discipline_officers_can_manage_support_actions(): void
    {
        $officer = $this->user('discipline_officer', 'assigned-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'restricted-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 60, 'high');

        $this->actingAs($student)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer))
            ->assertForbidden();

        $this->assertDatabaseCount('risk_interventions', 0);
    }

    public function test_action_progress_and_outcome_are_audited_without_changing_sanctions(): void
    {
        $officer = $this->user('discipline_officer', 'workflow-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'workflow-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 60, 'high');

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer));

        $intervention = RiskIntervention::firstOrFail();
        $common = [
            'follow_up_on' => today()->addDays(7)->format('Y-m-d'),
        ];

        $this->patch(route('officer.risk.interventions.update', [$student, $intervention]), [
            ...$common,
            'status' => 'in_progress',
            'outcome' => 'Initial student support meeting scheduled.',
        ])->assertSessionHas('success');

        $this->patch(route('officer.risk.interventions.update', [$student, $intervention]), [
            ...$common,
            'status' => 'completed',
            'outcome' => 'Review completed and a voluntary monitoring plan was agreed upon.',
        ])->assertSessionHas('success');

        $intervention->refresh();
        $this->assertSame('completed', $intervention->status);
        $this->assertNotNull($intervention->started_at);
        $this->assertNotNull($intervention->closed_at);
        $this->assertSame($officer->id, $intervention->closed_by);
        $this->assertSame(3, AuditLog::where('module', 'risk_interventions')->count());
        $this->assertSame(0, Sanction::count());

        $this->patch(route('officer.risk.interventions.update', [$student, $intervention]), [
            ...$common,
            'status' => 'completed',
            'outcome' => 'Attempted change after closure.',
        ])->assertSessionHas('error');

        $this->assertSame(
            'Review completed and a voluntary monitoring plan was agreed upon.',
            $intervention->fresh()->outcome
        );

        $this->get(route('officer.risk.index'))
            ->assertOk()
            ->assertSee('Completed')
            ->assertDontSee('Needs Review');
    }

    public function test_the_student_page_separates_open_actions_from_the_closed_archive(): void
    {
        $officer = $this->user('discipline_officer', 'archive-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'archive-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 80, 'critical');

        $department = AcademicUnit::query()->whereNotNull('scope_year_level')->firstOrFail();
        $officer->academicUnits()->sync([$department->id]);
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-00043',
            'year_level' => $department->scope_year_level,
        ]);

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer));

        $open = RiskIntervention::firstOrFail();

        $closed = RiskIntervention::create([
            'student_id' => $student->id,
            'created_by' => $officer->id,
            'assigned_to' => $officer->id,
            'action_type' => 'compliance_follow_up',
            'action_plan' => 'Confirm the outstanding community service hours were served in full.',
            'risk_score' => 80,
            'risk_band' => 'critical',
            'status' => 'completed',
            'follow_up_on' => today()->subDays(3),
            'outcome' => 'The student completed the remaining hours and the sanction is now compliant.',
            'closed_at' => now(),
            'closed_by' => $officer->id,
        ]);

        $response = $this->get(route('officer.risk.show', $student))->assertOk();

        // With support work recorded on a critical assessment, that card leads and
        // the indicators behind it collapse.
        $response->assertSee('risk-section-lead', false);
        $response->assertSee('riskIndicatorsCollapse', false);

        // The open action stays expanded; the closed one is folded into the archive.
        $response->assertSee('support-action-'.$open->id, false);
        $response->assertSee('support-action-'.$closed->id, false);
        $response->assertSee('Closed actions (1)');
        $response->assertSee('Update status');
    }

    public function test_a_recorded_action_opens_in_progress_and_closes_in_one_step(): void
    {
        $officer = $this->user('discipline_officer', 'autostatus-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'autostatus-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 70, 'high');

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $this->createPayload($officer));

        $intervention = RiskIntervention::firstOrFail();
        $this->assertSame('in_progress', $intervention->status);
        $this->assertNotNull($intervention->started_at);

        // No intermediate click: the officer records the outcome and closes it.
        $this->patch(route('officer.risk.interventions.update', [$student, $intervention]), [
            'status' => 'completed',
            'follow_up_on' => today()->addDays(7)->format('Y-m-d'),
            'outcome' => 'Met the student and agreed on a weekly check-in for the next month.',
        ])->assertSessionHas('success');

        $intervention->refresh();
        $this->assertSame('completed', $intervention->status);
        $this->assertSame($officer->id, $intervention->closed_by);
    }

    public function test_the_dashboard_counts_support_follow_ups_the_officer_has_run_past(): void
    {
        $officer = $this->user('discipline_officer', 'followup-officer@nu-clark.edu.ph');
        $other = $this->user('discipline_officer', 'followup-other@nu-clark.edu.ph');
        $student = $this->user('student', 'followup-student@students.nu-clark.edu.ph');

        $base = [
            'student_id' => $student->id,
            'created_by' => $officer->id,
            'action_type' => 'monitoring_plan',
            'action_plan' => 'Track the outstanding obligations and confirm the agreed check-ins happen.',
            'risk_score' => 70,
            'risk_band' => 'high',
        ];

        // Overdue and owned by this officer - the only one that should be counted.
        RiskIntervention::create([...$base, 'assigned_to' => $officer->id, 'status' => 'in_progress', 'follow_up_on' => today()->subDay()]);
        // Overdue, but someone else's queue.
        RiskIntervention::create([...$base, 'assigned_to' => $other->id, 'status' => 'in_progress', 'follow_up_on' => today()->subDay()]);
        // This officer's, but not yet due.
        RiskIntervention::create([...$base, 'assigned_to' => $officer->id, 'status' => 'in_progress', 'follow_up_on' => today()->addDay()]);
        // Overdue on paper, but already closed.
        RiskIntervention::create([
            ...$base,
            'assigned_to' => $officer->id,
            'status' => 'completed',
            'follow_up_on' => today()->subDays(5),
            'outcome' => 'The agreed check-ins were completed and no further action is needed.',
            'closed_at' => now(),
            'closed_by' => $officer->id,
        ]);

        $this->actingAs($officer)
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertViewHas('attention', fn (array $attention) => $attention['overdue_follow_ups'] === 1)
            ->assertSee('Overdue Support Follow-ups');

        // The card links into a filtered list rather than a bare count.
        $this->get(route('officer.risk.index', ['follow_up' => 'overdue']))
            ->assertOk()
            ->assertSee('overdue support follow-up assigned to you');
    }

    public function test_a_second_open_action_of_the_same_type_is_refused_but_other_types_are_not(): void
    {
        $officer = $this->user('discipline_officer', 'duplicate-officer@nu-clark.edu.ph');
        $student = $this->user('student', 'duplicate-student@students.nu-clark.edu.ph');
        $this->riskScore($student, 80, 'critical');

        $payload = $this->createPayload($officer);

        $this->actingAs($officer)
            ->post(route('officer.risk.interventions.store', $student), $payload)
            ->assertSessionHas('success');

        // The same type again, while the first is still open.
        $this->post(route('officer.risk.interventions.store', $student), $payload)
            ->assertSessionHas('error', 'A Student Support Meeting is already open for this student. Close it before recording another.');
        $this->assertDatabaseCount('risk_interventions', 1);

        // A different type is never blocked - officers still choose the mix and order.
        $this->post(route('officer.risk.interventions.store', $student), [
            ...$payload,
            'action_type' => 'monitoring_plan',
        ])->assertSessionHas('success');
        $this->assertDatabaseCount('risk_interventions', 2);

        // Once the first is closed, that type can be recorded again.
        $first = RiskIntervention::where('action_type', 'student_support_meeting')->firstOrFail();
        $this->patch(route('officer.risk.interventions.update', [$student, $first]), [
            'status' => 'completed',
            'follow_up_on' => today()->addDays(7)->format('Y-m-d'),
            'outcome' => 'The meeting took place and the agreed support steps were documented.',
        ])->assertSessionHas('success');

        $this->post(route('officer.risk.interventions.store', $student), $payload)
            ->assertSessionHas('success');
        $this->assertDatabaseCount('risk_interventions', 3);
    }

    private function createPayload(User $officer): array
    {
        return [
            'action_type' => 'student_support_meeting',
            'action_plan' => 'Meet with the student, review the documented indicators, and agree on appropriate support steps.',
            'follow_up_on' => today()->addDays(7)->format('Y-m-d'),
        ];
    }

    private function riskScore(User $student, float $score, string $band): RiskScore
    {
        return RiskScore::create([
            'student_id' => $student->id,
            'composite_score' => $score,
            'risk_band' => $band,
            'contributing_factors' => [],
            'computed_at' => now(),
        ]);
    }

    /** Put the student in a department the officer is assigned to. */
    private function shareDepartment(User $officer, User $student): void
    {
        $unit = AcademicUnit::query()->whereNull('scope_year_level')->firstOrFail();
        $program = AcademicProgram::query()->where('academic_unit_id', $unit->id)->firstOrFail();

        $officer->academicUnits()->syncWithoutDetaching([$unit->id]);

        StudentProfile::updateOrCreate(
            ['user_id' => $student->id],
            [
                'student_number' => '2026-88001',
                'program' => $program->name,
                'year_level' => '3',
            ]
        );
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
