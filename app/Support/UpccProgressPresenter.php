<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;

/**
 * Derives the UPCC step-tracker and next-action state shown on the
 * coordinator's case progress page. Pulled out of progress.blade.php so the
 * workflow-state rules (which step is active, what title/copy to show, when
 * Council action or a sanction resolution applies) have one place to live
 * and can be unit tested without rendering a view.
 */
class UpccProgressPresenter
{
    public readonly bool $isNoViolationClosure;

    public readonly array $stageSteps;

    public readonly int $workflowIndex;

    public readonly bool $requiresCouncilAction;

    public readonly bool $canRecordCoordinatorAppeal;

    public readonly bool $canFinalizeDecision;

    public readonly string $nextActionTitle;

    public readonly string $nextActionCopy;

    public readonly bool $isDistributeStep;

    public readonly bool $isCouncilActionStep;

    public readonly bool $isScheduleStep;

    public readonly bool $isFinalizedStep;

    public readonly bool $isCaseComplete;

    public readonly string $headerTitle;

    public function __construct(
        public readonly DisciplinaryCase $case,
        public readonly ?UpccReferral $referral,
    ) {
        $nfiIssued = $case->notices
            ->where('notice_type', 'notice_of_formative_intervention')
            ->where('is_voided', false)
            ->isNotEmpty();

        $this->isNoViolationClosure = $case->status === 'dismissed' && $referral?->outcome === 'no_violation';

        if ($this->isNoViolationClosure) {
            // No sanction means no appeal window or resolution — those steps
            // never applied, so they're dropped rather than shown as "done".
            $this->stageSteps = [
                ['key' => 'schedule', 'title' => 'Schedule Hearing', 'note' => 'Set conference details'],
                ['key' => 'hearing', 'title' => 'Conduct Hearing', 'note' => 'Record attendance'],
                ['key' => 'decision', 'title' => 'Record Decision', 'note' => 'Capture UPCC outcome'],
                ['key' => 'closed', 'title' => 'No Violation Found', 'note' => 'Case closed, no sanction'],
            ];
            $this->workflowIndex = 3;
        } else {
            $workflowStage = match (true) {
                in_array($case->status, ['pending_resolution', 'resolved'], true) => 'resolution',
                (bool) $referral?->decision_final_at => 'finalize',
                $referral?->status === 'reconsideration_filed' => 'appeal',
                (bool) $referral?->decision_distributed_at => 'appeal',
                $referral?->status === 'concluded' => 'decision',
                $referral?->status === 'in_progress' => 'decision',
                $referral?->status === 'scheduled' => 'hearing',
                default => 'schedule',
            };
            $workflowOrder = ['schedule', 'hearing', 'decision', 'appeal', 'finalize', 'resolution'];
            $this->workflowIndex = array_search($workflowStage, $workflowOrder, true);
            $this->stageSteps = [
                ['key' => 'schedule', 'title' => 'Schedule Hearing', 'note' => 'Set conference details'],
                ['key' => 'hearing', 'title' => 'Conduct Hearing', 'note' => 'Record attendance'],
                ['key' => 'decision', 'title' => 'Record Decision', 'note' => 'Capture UPCC outcome'],
                ['key' => 'appeal', 'title' => 'Appeal Window', 'note' => 'Distribute and monitor'],
                ['key' => 'finalize', 'title' => 'Finalize', 'note' => 'Close out UPCC review'],
                ['key' => 'resolution', 'title' => 'Resolution', 'note' => 'Record sanctions'],
            ];
        }

        // Handbook VI.G.3.b.5-6: the President's Council approves every UPCC
        // resolution, not only the Category 4-5 ones.
        $this->requiresCouncilAction = (bool) $referral && ! $case->isClosed();

        $this->canRecordCoordinatorAppeal = (bool) ($referral
            && $referral->decision_distributed_at
            && ! $referral->appeal_filed
            && ! $referral->appeal_waived_at
            && ! $referral->decision_final_at
            && $referral->appeal_window_end
            && today()->lte($referral->appeal_window_end));

        $this->canFinalizeDecision = (bool) ($referral
            && $referral->decision_distributed_at
            && ! $referral->decision_final_at
            && (! $referral->appeal_window_end || today()->gt($referral->appeal_window_end) || $referral->appeal_filed)
            && $referral->status !== 'reconsideration_filed');

        [$this->nextActionTitle, $this->nextActionCopy] = match (true) {
            $this->isNoViolationClosure => ['No Violation Found', 'The UPCC found no violation. The case is closed and the student has been notified.'],
            $case->status === 'pending_resolution' => ['Record Resolution', 'The UPCC decision is final. Record the formal resolution to close the case.'],
            $case->status === 'resolved' && $case->resolution?->intervention_category === 2 && ! $nfiIssued => ['Issue NFI', 'Review and issue the Notice of Formative Intervention to activate the recorded Category 2 sanctions.'],
            $case->status === 'resolved' => ['Case Resolved', 'The case has been resolved. No further coordinator action is required.'],
            $case->status === 'pending_upcc' && ! $referral => ['Schedule UPCC Hearing', 'Set the conference date, location, and proceedings type so the student can be notified.'],
            $referral?->status === 'scheduled' => ['Record Attendance', 'Mark whether the student showed up for the conference on '.$referral->scheduled_conference_at->format('F d, Y \a\t g:i A').'. The panel then has 10 days to finish.'],
            $referral?->status === 'in_progress' => ['Record UPCC Decision', 'Enter the decision summary from the UPCC proceedings.'],
            $referral?->status === 'concluded' && $this->requiresCouncilAction && ! $referral->presidents_council_action => ['Record Council Action', 'The UPCC decision needs President\'s Council approval before it can be distributed.'],
            $referral?->status === 'concluded' && ! $referral->decision_distributed_at => ['Distribute Decision', 'Send the recorded decision to the student and open the appeal window.'],
            $referral?->status === 'reconsideration_filed' => ['Record Reconsideration', 'Resolve the filed appeal before the decision can be finalized.'],
            $this->canRecordCoordinatorAppeal => ['Record Appeal', 'Encode an appeal received outside the student portal while the appeal window is open.'],
            $this->canFinalizeDecision => ['Finalize Decision', 'Finalize the UPCC decision so the case can continue to resolution processing.'],
            default => ['No Action Required', 'The case is waiting for the next workflow condition before another coordinator action is available.'],
        };

        $this->isDistributeStep = $referral?->status === 'concluded'
            && ! $referral->decision_distributed_at
            && (! $this->requiresCouncilAction || $referral->presidents_council_action);

        $this->isCouncilActionStep = $referral?->status === 'concluded'
            && $this->requiresCouncilAction
            && ! $referral->presidents_council_action;

        $this->isScheduleStep = $case->status === 'pending_upcc' && ! $referral;

        $this->isFinalizedStep = (bool) $referral?->decision_final_at;

        $this->isCaseComplete = in_array($this->nextActionTitle, ['Case Resolved', 'No Violation Found'], true);

        $this->headerTitle = $this->isDistributeStep ? 'Record Decision' : $this->nextActionTitle;
    }

    public static function for(DisciplinaryCase $case): self
    {
        return new self($case, $case->upccReferral);
    }

    /** Whether the tracker's step $i should render as done (green, checked). */
    public function isStepDone(int $i): bool
    {
        return $i < $this->workflowIndex || ($i === $this->workflowIndex && $this->case->isClosed());
    }

    /** Whether the tracker's step $i should render as the active (blue) step. */
    public function isStepActive(int $i): bool
    {
        return $i === $this->workflowIndex && ! $this->case->isClosed();
    }

    /** The title to show for step $i — the dynamic header title for the active step, otherwise its static label. */
    public function stepTitle(int $i): string
    {
        return $i === $this->workflowIndex ? $this->headerTitle : $this->stageSteps[$i]['title'];
    }
}
