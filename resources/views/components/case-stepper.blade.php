@props(['case'])

@php
    $isMinor = $case->case_type === 'minor';
    $isClosed = in_array($case->status, ['dismissed', 'voided'], true);
    $isResolved = $case->status === 'resolved';

    // Facts that are true regardless of the current status — used to mark a step
    // "done" even after the case has moved past it, and to show how far a
    // dismissed/voided case actually got before it closed.
    $hasOffense = (bool) $case->confirmed_offense_id;
    $hasSanction = (bool) $case->assigned_sanction_category;
    $hasNotice = $case->notices->isNotEmpty();
    $hasExplanation = (bool) $case->explanation;
    $isEvaluated = (bool) $case->explanation?->evaluated_at;
    $isForwarded = (bool) $case->forwarded_to_coordinator;

    // Every "doneAnchor" below only exists in the page once that step is actually
    // done — the review card either replaces itself with the next action, or never
    // renders until reached. Point there ONLY when done; every not-yet-done step
    // (current or upcoming) falls back to #action-panel, which always holds
    // whatever the real current action is. Without this, clicking a step that
    // hasn't happened yet — including the current one, before its own review
    // element exists — silently does nothing.
    // A full URL, not a bare hash: #action-panel also exists on any review sub-view
    // (e.g. ?review_sanction=1), so a bare hash would just scroll within that same
    // page instead of actually leaving it to reach the real current action.
    $actionAnchor = route('officer.cases.show', $case).'#action-panel';

    if ($isMinor) {
        $steps = [
            ['label' => 'Classify', 'doneAnchor' => '#case-step-classify', 'done' => $hasOffense],
            ['label' => 'Resolved', 'doneAnchor' => $actionAnchor, 'done' => $isResolved],
        ];
    } else {
        $steps = [
            ['label' => 'Classify', 'doneAnchor' => '#case-step-classify', 'done' => $hasOffense],
            [
                'label' => 'Sanction',
                // Once confirmed, the Confirm Sanction card is replaced on the page by the
                // next action, so reopen that same card to review it. After the case leaves
                // 'open' the sanction is locked and the card can't come back at all — then
                // Case Details is the only place it still shows.
                'doneAnchor' => $case->status === 'open'
                    ? route('officer.cases.show', ['case' => $case, 'review_sanction' => 1]).'#case-step-sanction'
                    : '#case-details',
                'done' => $hasSanction,
                // Reopened for review right now — the stepper should show where the
                // officer actually is, not just where the case is.
                'reviewing' => request()->boolean('review_sanction'),
            ],
            [
                'label' => 'Notify',
                // The live "NTE Issued" card only exists while pending_nte; once the case
                // moves on it's gone, and the notice only still shows in the persistent
                // history disclosure further down the page.
                'doneAnchor' => $case->status === 'pending_nte' ? '#case-step-notify' : '#case-step-notify-history',
                'done' => $hasNotice,
            ],
            [
                'label' => 'Explain',
                // Same pattern: the live explanation card only exists during
                // awaiting_explanation; afterwards it only lives in the history section.
                'doneAnchor' => $case->status === 'awaiting_explanation' ? '#case-step-explain' : '#case-step-explain-history',
                'done' => $hasExplanation,
            ],
            [
                'label' => 'Evaluate',
                // While under_review, the evaluation can still be corrected — reopen the
                // inline Correct Evaluation card via a query flag rather than just landing
                // on the page. Once the case moves on, correction is blocked server-side
                // (CaseService::correctEvaluation), so all that's left to show is the
                // read-only record in the Student Explanation history card.
                'doneAnchor' => $case->status === 'under_review'
                    ? route('officer.cases.show', ['case' => $case, 'review_evaluation' => 1]).'#action-panel'
                    : '#case-step-explain-history',
                'done' => $isEvaluated,
                // Reopened for review right now — the stepper should show where the
                // officer actually is, not just where the case is (same as Sanction above).
                'reviewing' => request()->boolean('review_evaluation'),
            ],
        ];

        $steps[] = $isForwarded
            ? ['label' => 'Coordinator', 'doneAnchor' => $actionAnchor, 'done' => $isResolved]
            : ['label' => 'Resolve', 'doneAnchor' => $actionAnchor, 'done' => $isResolved];
    }

    foreach ($steps as $index => &$step) {
        $step['anchor'] = $step['done'] ? $step['doneAnchor'] : $actionAnchor;
    }
    unset($step);

    // The first not-yet-done step is "current" — unless the case is closed, in
    // which case nothing further is current; a terminal marker takes its place.
    $currentIndex = null;
    if (! $isClosed) {
        foreach ($steps as $index => $step) {
            if (! $step['done']) {
                $currentIndex = $index;
                break;
            }
        }
    }

    foreach ($steps as $index => &$step) {
        $step['state'] = match (true) {
            $step['done'] => 'done',
            $index === $currentIndex => 'current',
            default => 'upcoming',
        };
    }
    unset($step);

    // A closed case never reaches its remaining steps — showing them grayed out
    // beside a "Dismissed"/"Voided" marker at the far end reads as if the case
    // had gotten that far and THEN closed. Truncate to only what actually
    // happened, so the terminal marker sits right where progress really stopped.
    $visibleSteps = $steps;
    if ($isClosed) {
        $lastDoneIndex = null;
        foreach ($steps as $index => $step) {
            if ($step['done']) {
                $lastDoneIndex = $index;
            }
        }
        $visibleSteps = $lastDoneIndex === null ? [] : array_slice($steps, 0, $lastDoneIndex + 1);
    }
@endphp

<div class="case-stepper-scroller">
<div class="case-stepper {{ count($visibleSteps) === 2 ? 'is-two-step' : '' }}" role="list" aria-label="Case progress" tabindex="0">
    @foreach ($visibleSteps as $index => $step)
        @if ($index > 0)
            <div class="case-stepper-connector {{ $visibleSteps[$index - 1]['state'] === 'done' ? 'is-done' : '' }}"></div>
        @endif
        @php $isReviewing = $step['reviewing'] ?? false; @endphp
        <a href="{{ $step['anchor'] }}"
           role="listitem"
           data-scroll-flash
           class="case-stepper-step is-{{ $step['state'] }} {{ $isReviewing ? 'is-reviewing' : '' }}"
           @if ($isReviewing) aria-current="step" @endif
           title="{{ match (true) {
               $isReviewing => $step['label'].' — you are reviewing this step',
               $step['state'] === 'upcoming' => $step['label'].' — not reached yet',
               default => $step['label'],
           } }}">
            <span class="case-stepper-node">
                @if ($step['state'] === 'done')
                    <i class="bi bi-check-lg"></i>
                @else
                    {{ $index + 1 }}
                @endif
            </span>
            <span class="case-stepper-label">{{ $step['label'] }}</span>
        </a>
    @endforeach

    @if ($isClosed)
        @if (! empty($visibleSteps))
            <div class="case-stepper-connector is-closed"></div>
        @endif
        <div class="case-stepper-step is-closed" role="listitem"
             title="{{ ucfirst($case->status) }} — this case will not proceed further">
            <span class="case-stepper-node">
                <i class="bi bi-x-lg"></i>
            </span>
            <span class="case-stepper-label">{{ ucfirst($case->status) }}</span>
        </div>
    @endif
</div>
</div>
