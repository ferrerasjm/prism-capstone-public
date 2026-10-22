@extends('layouts.coordinator')
@section('title', $case->case_number . ' UPCC Progress')
@section('css')
<style>
    .upcc-progress-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        margin-bottom: 24px;
    }

    .upcc-progress-header-copy {
        min-width: 0;
    }

    .upcc-progress-header-actions {
        display: flex;
        flex-shrink: 0;
        align-items: center;
        gap: 10px;
    }

    .upcc-step-track {
        display: flex;
        align-items: stretch;
        gap: 0;
        border: 1px solid #dbe4f0;
        border-radius: 10px;
        overflow: hidden;
        background: #e6edf7;
    }

    .upcc-step {
        flex: 1 1 0;
        position: relative;
        min-width: 0;
        padding: 14px 16px;
        background: #fff;
    }

    .upcc-step + .upcc-step {
        border-left: 1px solid #dbe4f0;
    }

    .upcc-step.is-done {
        background: #f4fbf7;
    }

    .upcc-step.is-active {
        background: #eef5ff;
    }

    .upcc-step.is-active::after {
        content: "";
        position: absolute;
        inset: 0;
        z-index: 1;
        border: 2px solid #3156c7;
        pointer-events: none;
    }

    .upcc-step:first-child.is-active::after {
        border-top-left-radius: 9px;
        border-bottom-left-radius: 9px;
    }

    .upcc-step:last-child.is-active::after {
        border-top-right-radius: 9px;
        border-bottom-right-radius: 9px;
    }

    .upcc-step-kicker {
        display: block;
        margin-bottom: 5px;
        color: #64748b;
        font-size: .66rem;
        font-weight: 800;
        letter-spacing: .05em;
        text-transform: uppercase;
    }

    .upcc-step-title {
        color: #111827;
        font-size: .86rem;
        font-weight: 750;
        line-height: 1.3;
    }

    .upcc-step-note {
        margin-top: 3px;
        color: #64748b;
        font-size: .74rem;
        line-height: 1.35;
    }

    .upcc-next-action {
        border: 1px solid #d7e2f3;
        box-shadow: none;
    }

    .upcc-next-action > .prism-card-header {
        border-bottom-color: #3156c7;
        background: #3156c7;
        color: #fff;
    }

    .upcc-next-action .prism-card-title {
        color: #fff;
    }

    .upcc-next-action .prism-badge {
        border: 1px solid rgba(255, 255, 255, .38);
        background: rgba(255, 255, 255, .14);
        color: #fff;
    }

    /* Every action form fills the card width — the card is already the measure. */
    .upcc-action-intro {
        margin-bottom: 16px;
        padding: 13px 15px;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
        background: #eff6ff;
        color: #334155;
        font-size: .86rem;
        line-height: 1.6;
    }

    .upcc-action-form {
        display: grid;
        gap: 14px;
    }

    .upcc-schedule-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .upcc-panel-heading {
        margin-top: 6px;
        font-size: .82rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: #3156c7;
        border-top: 1px solid #dbe4f0;
        padding-top: 14px;
    }

    .upcc-action-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 4px;
        padding-top: 16px;
        border-top: 1px solid #e6ecf5;
    }

    .upcc-attendance-options {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .upcc-attendance-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 13px 15px;
        border: 2px solid var(--border);
        border-radius: var(--radius-sm);
        background: #fff;
        cursor: pointer;
        transition: border-color .15s, background-color .15s, box-shadow .15s;
    }

    .upcc-attendance-option:hover:not(.is-disabled) {
        border-color: #a9bde8;
        background: #fbfcfe;
    }

    .upcc-attendance-option.is-checked,
    .upcc-attendance-option:has(input:checked) {
        border-color: var(--blue);
        background: #f4f7fe;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .09);
    }

    .upcc-attendance-option.is-disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    .upcc-attendance-option input {
        margin-top: 3px;
        flex-shrink: 0;
    }

    .upcc-attendance-option-title {
        display: flex;
        align-items: center;
        gap: 7px;
        font-weight: 700;
        font-size: .875rem;
        color: #1e293b;
    }

    .upcc-attendance-option-title i {
        color: #3156c7;
        font-size: .95rem;
    }

    .upcc-attendance-option-hint {
        display: block;
        margin-top: 3px;
        font-size: .78rem;
        line-height: 1.5;
        color: var(--text-secondary);
    }

    @media (max-width: 575.98px) {
        .upcc-attendance-options {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 991.98px) {
        .upcc-progress-header {
            align-items: flex-start;
            flex-direction: column;
        }

        .upcc-progress-header-actions {
            width: 100%;
        }

        .upcc-progress-header-actions .prism-btn {
            width: 100%;
        }

        .upcc-step-track {
            display: block;
        }

        .upcc-step + .upcc-step {
            border-left: 0;
            border-top: 1px solid #dbe4f0;
        }

        .upcc-schedule-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection
@section('content')
    @php
        $progress = $progress ?? \App\Support\UpccProgressPresenter::for($case);
        $referral = $progress->referral;
        $requiresCouncilAction = $progress->requiresCouncilAction;
        $canRecordCoordinatorAppeal = $progress->canRecordCoordinatorAppeal;
        $canFinalizeDecision = $progress->canFinalizeDecision;
        $nextActionTitle = $progress->nextActionTitle;
        $nextActionCopy = $progress->nextActionCopy;
        $isDistributeStep = $progress->isDistributeStep;
        $headerTitle = $progress->headerTitle;
        $stageSteps = $progress->stageSteps;
        $workflowIndex = $progress->workflowIndex;
    @endphp

    <div class="prism-content">
        <div class="upcc-progress-header">
            <div class="upcc-progress-header-copy">
                <x-breadcrumbs :items="[
                    ['label' => $caseIndexLabel ?? 'UPCC Cases', 'url' => $caseIndexUrl ?? route('coordinator.cases.index')],
                    ['label' => $case->case_number, 'url' => route('coordinator.cases.show', $case)],
                    ['label' => 'Progress'],
                ]" />
                <h1 class="prism-page-title">{{ $case->case_number }} Progress</h1>
                <p class="prism-page-sub">
                    {{ $headerTitle }}
                    &middot;
                    {{ $case->assignedOfficer?->list_name ?? 'Unassigned' }}
                </p>
            </div>
            <div class="upcc-progress-header-actions">
                <a href="{{ route('coordinator.cases.show', $case) }}" class="prism-btn prism-btn-blue-outline">
                    Case Details
                </a>
            </div>
        </div>

        @if($errors->any())
            <div class="prism-alert prism-alert-danger mb-4">
                <i class="bi bi-exclamation-circle-fill"></i>
                <div>{{ $errors->first() }}</div>
            </div>
        @endif

        @include('coordinator.cases._upcc-progress-card')
        @include('coordinator.cases._upcc-next-action')
    </div>
@endsection
