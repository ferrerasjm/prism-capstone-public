<?php

declare(strict_types=1);

namespace Tests\Unit\SanctionReferralMonitoring;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Services\CoordinatorCaseService;
use App\Services\MajorOffenseWorkflow;
use App\Support\UpccProgressPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 5 - Sanction and Referral Monitoring
 * Component: UPCC Referral Lifecycle (App\Services\CoordinatorCaseService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Walks the referral from scheduling through the conference, the decision,
 * its distribution and its finalisation, checking the state each step
 * leaves behind for the next one.
 */
class UpccReferralLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: scheduling opens the referral and issues a notice of hearing. */
    public function test_scheduling_opens_the_referral_and_issues_a_notice_of_hearing(): void
    {
        [$case, $coordinator] = $this->caseAtUpcc();

        $referral = (new CoordinatorCaseService)->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $this->assertSame('scheduled', $referral->status);
        $this->assertSame($case->id, $referral->case_id);
        $this->assertSame($coordinator->id, $referral->referred_by);
        $this->assertSame(
            1,
            CaseNotice::where('case_id', $case->id)->where('notice_type', 'notice_of_hearing')->count()
        );
    }

    /** Scenario 2 - Normal: recording attendance starts the proceedings. */
    public function test_recording_attendance_starts_the_proceedings(): void
    {
        [$case, $coordinator] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $service->recordAttendance($referral, true, $coordinator->id);

        $fresh = $referral->fresh();
        $this->assertTrue($fresh->student_attended);
        $this->assertSame('in_progress', $fresh->status);
        $this->assertNotNull($fresh->proceedings_started_at);
        $this->assertNull($fresh->student_no_show_recorded_by);
    }

    /** Scenario 3 - Boundary: a no-show is recorded against the coordinator who observed it. */
    public function test_a_no_show_is_recorded_against_the_observing_coordinator(): void
    {
        [$case, $coordinator] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $service->recordAttendance($referral, false, $coordinator->id);

        $fresh = $referral->fresh();
        $this->assertFalse($fresh->student_attended);
        $this->assertSame($coordinator->id, $fresh->student_no_show_recorded_by);
        $this->assertSame('in_progress', $fresh->status);
    }

    /** Scenario 4 - Normal: distributing the decision opens the five-working-day appeal window. */
    public function test_distributing_the_decision_opens_the_five_day_appeal_window(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        [$case, $coordinator] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $service->recordAttendance($referral, true, $coordinator->id);
        $service->recordDecision($referral->fresh(), 'The committee found the charge substantiated.', 'violation_established', $coordinator->id);
        $service->distributeDecision($referral->fresh(), $coordinator->id);

        $fresh = $referral->fresh();
        $this->assertSame('concluded', $fresh->status);
        $this->assertNotNull($fresh->decision_issued_at);
        $this->assertSame('2026-08-03', $fresh->appeal_window_start->toDateString());
        $this->assertSame('2026-08-10', $fresh->appeal_window_end->toDateString());
        $this->assertSame(
            MajorOffenseWorkflow::appealDeadline(Carbon::parse('2026-08-03 10:00:00'))->toDateString(),
            $fresh->appeal_window_end->toDateString()
        );
        $this->assertTrue($fresh->isAppealWindowOpen());
    }

    /** Scenario 5 - Normal: waiving the appeal finalises the decision and readies the resolution. */
    public function test_waiving_the_appeal_finalises_the_decision_and_readies_the_resolution(): void
    {
        Carbon::setTestNow('2026-08-03 10:00:00');
        [$case, $coordinator, $student] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $service->recordAttendance($referral, true, $coordinator->id);
        $service->recordDecision($referral->fresh(), 'The committee found the charge substantiated.', 'violation_established', $coordinator->id);
        $service->distributeDecision($referral->fresh(), $coordinator->id);
        $service->waiveAppealAndFinalize($case->fresh(), $referral->fresh(), $student->id);

        $fresh = $referral->fresh();
        $this->assertSame($student->id, $fresh->appeal_waived_by);
        $this->assertNotNull($fresh->appeal_waived_at);
        $this->assertNotNull($fresh->decision_final_at);
        $this->assertNotNull($fresh->records_filed_at);
        $this->assertSame('pending_resolution', $case->fresh()->status);
        $this->assertFalse($fresh->isAppealWindowOpen());
    }

    /** Scenario 6 - Normal: a "no violation" decision closes the case immediately, skipping Council action and resolution. */
    public function test_a_no_violation_decision_closes_the_case_without_council_action_or_resolution(): void
    {
        [$case, $coordinator] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $service->recordAttendance($referral, true, $coordinator->id);
        $service->recordDecision($referral->fresh(), 'The committee found no basis for the alleged offense.', 'no_violation', $coordinator->id);
        $service->closeAsNoViolation($referral->fresh(), $coordinator->id);

        $fresh = $referral->fresh();
        $this->assertSame('no_violation', $fresh->outcome);
        $this->assertNull($fresh->presidents_council_action);
        $this->assertNotNull($fresh->decision_distributed_at);
        $this->assertNotNull($fresh->decision_final_at);

        $freshCase = $case->fresh();
        $this->assertSame('dismissed', $freshCase->status);
        $this->assertSame('The committee found no basis for the alleged offense.', $freshCase->dismissal_reason);
        $this->assertSame($coordinator->id, $freshCase->resolved_by);
        $this->assertTrue($freshCase->isClosed());

        $progress = UpccProgressPresenter::for($freshCase);
        $this->assertSame('No Violation Found', $progress->nextActionTitle);
        $this->assertCount(4, $progress->stageSteps, 'no-violation closure should drop the Appeal Window and Resolution steps');
        $this->assertSame(3, $progress->workflowIndex);
        $this->assertTrue($progress->isStepDone(3), 'the final step should render done, not merely active, once the case is closed');
        $this->assertFalse($progress->isStepActive(3));
        $this->assertFalse($progress->isCouncilActionStep);
        $this->assertFalse($progress->isDistributeStep);
    }

    /** Scenario 7 - Boundary: the tracker for a normal (sanctioned) flow keeps all six steps and marks the active one correctly at each stage. */
    public function test_the_progress_presenter_tracks_each_stage_of_the_normal_sanctioned_flow(): void
    {
        [$case, $coordinator] = $this->caseAtUpcc();
        $service = new CoordinatorCaseService;
        $referral = $service->scheduleUpcc($case, $this->scheduleData(), $coordinator->id);

        $progress = UpccProgressPresenter::for($case->fresh());
        $this->assertCount(6, $progress->stageSteps);
        $this->assertSame(1, $progress->workflowIndex, 'awaiting the conference should highlight Step 2 (Conduct Hearing)');
        $this->assertTrue($progress->isStepActive(1));
        $this->assertTrue($progress->isStepDone(0));

        $service->recordAttendance($referral, true, $coordinator->id);
        $progress = UpccProgressPresenter::for($case->fresh());
        $this->assertSame('Record UPCC Decision', $progress->nextActionTitle);
        $this->assertSame(2, $progress->workflowIndex);

        $service->recordDecision($referral->fresh(), 'The charge was substantiated.', 'violation_established', $coordinator->id);
        $progress = UpccProgressPresenter::for($case->fresh());
        $this->assertSame('Record Council Action', $progress->nextActionTitle);
        $this->assertTrue($progress->isCouncilActionStep);
        $this->assertFalse($progress->isDistributeStep);

        $service->recordCouncilAction($referral->fresh(), 'approved', null, $coordinator->id);
        $progress = UpccProgressPresenter::for($case->fresh());
        $this->assertTrue($progress->isDistributeStep, 'once Council has acted, the card should switch to the distribute step');
        $this->assertFalse($progress->isCouncilActionStep);
    }

    private function scheduleData(): array
    {
        return [
            'scheduled_conference_at' => '2026-08-10 13:00:00',
            'conference_location' => 'SDAO Conference Room',
            'proceedings_type' => 'full_hearing',
            'sdao_head_name' => 'Demo Student 132',
            'dean_or_program_chair_name' => 'Dean Ramirez',
            'cross_program_faculty_name' => 'Prof. Villareal',
            'student_representative_name' => 'Council Rep. Diaz',
            'presiding_officer_name' => 'Atty. Salazar',
            'thru' => 'Office of Student Affairs',
        ];
    }

    /** @return array{0: DisciplinaryCase, 1: User, 2: User} */
    private function caseAtUpcc(): array
    {
        static $sequence = 0;
        $sequence++;

        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Referred'.$sequence,
            'email' => "upcc.student{$sequence}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $coordinator = User::create([
            'last_name' => 'Aquino',
            'first_name' => 'Coordinating'.$sequence,
            'email' => "upcc.coordinator{$sequence}@nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'discipline_coordinator',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-76'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
            'student_id' => $student->id,
            'case_type' => 'major',
            'status' => 'pending_upcc',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => true,
            'void_requires_coordinator_approval' => false,
        ]);

        return [$case, $coordinator, $student];
    }
}
