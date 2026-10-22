@extends('layouts.guidance')
@section('title', 'Referral Detail')
@section('css')
<style>
    .guidance-referral-detail .prism-card { border-radius: 12px; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .guidance-referral-detail .prism-card-header { min-height: 54px; padding: 14px 18px; background: #fbfcfe; }
    .guidance-referral-detail .status-panel-header { background: #3156c7; border-bottom-color: #3156c7; }
    .guidance-referral-detail .status-panel-header .prism-card-title { color: #fff; }
    .guidance-referral-detail .prism-card-body { padding: 18px; }
    .guidance-referral-detail .prism-detail-table th { color: #64748b; font-weight: 600; white-space: nowrap; }
    .guidance-referral-detail .prism-detail-table td { color: #334155; font-weight: 600; }
    .guidance-referral-detail .prism-btn { min-height: 40px; border-radius: 8px; }
    .guidance-referral-detail .status-action {
        display: inline-flex;
        width: auto;
        min-height: 36px;
        padding: 8px 14px;
        font-size: .76rem;
        font-weight: 700;
    }
    .guidance-referral-detail .status-action-wrap { display: flex; justify-content: flex-end; }
    .guidance-referral-detail .status-option {
        padding: 14px;
        border: 1px solid #dbe3ee;
        border-radius: 9px;
        background: #fff;
    }
    .guidance-referral-detail .status-option--incomplete { border-color: #fecaca; }
    .guidance-referral-detail .urgency-cell { display: flex; flex-direction: row; align-items: center; flex-wrap: wrap; gap: 6px 10px; padding: 2px 0; }
    .guidance-referral-detail .urgency-turnaround { font-size: .78rem; font-weight: 500; line-height: 1.45; color: #64748b; }
    .guidance-referral-detail .referral-reason-block { margin-top: 14px; }
    .guidance-referral-detail .referral-reason-block > .prism-detail-label { display: block; margin-bottom: 6px; color: #475569; font-size: .76rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
    .guidance-referral-detail .referral-reason-text {
        max-height: 240px;
        overflow-y: auto;
        padding: 12px 14px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
        color: #334155;
        font-size: .85rem;
        line-height: 1.7;
        white-space: pre-wrap;
        overflow-wrap: anywhere;
        word-break: break-word;
    }
    .guidance-referral-detail .referral-reason-text.is-empty { color: #94a3b8; font-style: italic; }
    .guidance-referral-detail .referral-detail-grid { display: grid; grid-template-columns: 1fr; gap: 16px; align-items: start; }
    @media (min-width: 992px) {
        .guidance-referral-detail .referral-detail-grid { grid-template-columns: minmax(380px, 460px) minmax(0, 1fr); }
        .guidance-referral-detail .referral-detail-grid .non-compliance-card { grid-column: 2; }
    }
    .guidance-referral-detail .referral-detail-grid .prism-card { margin-bottom: 0; width: 100%; }
    .guidance-referral-detail .referral-notice-card { margin-bottom: 0; }
    .guidance-referral-detail .referral-notice-card + .referral-detail-grid { margin-top: 10px; }
    .guidance-referral-detail .referral-notice-body {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }
    .guidance-referral-detail .referral-notice-text { display: flex; align-items: flex-start; gap: 12px; min-width: 0; }
    .guidance-referral-detail .referral-notice-action { flex-shrink: 0; }
    @media (max-width: 767.98px) {
        .guidance-referral-detail .referral-notice-body { flex-direction: column; align-items: stretch; }
        .guidance-referral-detail .referral-notice-action .prism-btn { width: 100%; justify-content: center; }
    }
    .guidance-referral-detail .compact-status-actions { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .guidance-referral-detail .compact-status-actions .prism-btn { min-height:36px; padding:8px 13px; font-size:.76rem; }
    .guidance-referral-detail .student-status-row { display:grid; grid-template-columns:22% 1fr; align-items:center; gap:10px; margin-top:0; padding-top:12px; border-top:1px solid #edf2f7; }
    .guidance-referral-detail .student-status-label { color:#64748b; font-size:.75rem; font-weight:600; }
    .guidance-referral-detail .student-status-row .compact-status-actions { justify-content:flex-start; }
    @media (max-width:575.98px) { .guidance-referral-detail .student-status-row { grid-template-columns:1fr; gap:10px; } .guidance-referral-detail .student-status-row .compact-status-actions { justify-content:flex-start; } }
    .guidance-referral-detail .student-status-action {
        display: inline-flex;
        margin-top: 16px;
        padding: 8px 13px;
        border: 1px solid #cbd8f4;
        border-radius: 7px;
        color: #3156c7;
        background: #f8faff;
        font-size: .76rem;
        font-weight: 700;
        text-decoration: none;
    }
    .guidance-referral-detail .student-status-action:hover { background: #eff4ff; }
    .guidance-referral-detail .privacy-notice {
        width: 100%;
        margin: 24px 0 0;
        padding: 10px 12px !important;
        font-size: .82rem !important;
        line-height: 1.5;
    }
    .guidance-referral-detail .referral-info-notice { margin: 24px 0 24px; }
    .guidance-referral-detail .incomplete-modal .modal-dialog { max-width: 520px; }
    .guidance-referral-detail .incomplete-modal .modal-content { overflow: hidden; border: 1px solid #dbe3ee; border-radius: 12px; box-shadow: 0 18px 45px rgba(15,23,42,.18); }
    .guidance-referral-detail .incomplete-modal .modal-header { padding: 18px 22px; border-bottom: 1px solid #e5eaf2; }
    .guidance-referral-detail .incomplete-modal .modal-title { color: #1e293b; font-size: 1.05rem; font-weight: 700; }
    .guidance-referral-detail .incomplete-modal .btn-close { opacity: .55; }
    .guidance-referral-detail .incomplete-modal .modal-body { padding: 18px 22px 20px; }
    .guidance-referral-detail .incomplete-modal .modal-intro { margin: 0 0 16px; color: #64748b; font-size: .8rem; line-height: 1.55; }
    .guidance-referral-detail .incomplete-modal .modal-body .prism-label { margin-bottom: 6px; color: #334155; font-size: .76rem; font-weight: 700; }
    .guidance-referral-detail .incomplete-modal textarea { min-height: 108px; resize: vertical; }
    .guidance-referral-detail .incomplete-modal .modal-footer { gap: 8px; padding: 14px 22px 18px; border-top: 1px solid #e5eaf2; }
    .guidance-referral-detail .incomplete-modal .modal-footer .prism-btn { min-height: 36px; padding: 8px 14px; font-size: .76rem; }
    .guidance-referral-detail .referral-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .guidance-referral-detail .referral-page-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    @media (max-width: 575.98px) {
        .guidance-referral-detail .referral-page-header { flex-direction: column; align-items: stretch; }
        .guidance-referral-detail .referral-page-actions { flex-direction: column; align-items: stretch; }
        .guidance-referral-detail .referral-page-actions form { width: 100%; }
        .guidance-referral-detail .referral-page-actions .prism-btn {
            /* Reset the flex-basis inherited from the shared row-layout
               header rule (.prism-page-header .prism-btn { flex: 1 1 160px })
               — in this column layout that basis sizes height, not width,
               which is what blew the button up on small screens. */
            flex: 0 0 auto;
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection
@section('content')
<div class="prism-content guidance-referral-detail">

    <div class="prism-page-header referral-page-header">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'My Referrals', 'url' => route('guidance.referrals.index')],
                ['label' => $referral->student?->full_name ?? 'Referral'],
            ]" />
            <h1 class="prism-page-title">Counseling Referral</h1>
        </div>
        @if($referral->status === 'pending' && $referral->isAcknowledged())
            <div class="referral-page-actions">
                <button type="button" class="prism-btn prism-btn-blue-outline" data-bs-toggle="modal" data-bs-target="#incompleteReferralModal">Mark Incomplete</button>
                <button type="button" class="prism-btn prism-btn-primary" data-bs-toggle="modal" data-bs-target="#completeReferralModal">Mark Completed</button>
            </div>
        @endif
    </div>

    @if($errors->any())
        <div class="prism-toast-region" aria-live="polite" aria-atomic="true">
            <div class="toast fade prism-toast prism-toast-danger" role="status" aria-live="polite" aria-atomic="true">
                <span class="prism-toast-icon" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                <div class="toast-body"><span class="visually-hidden">Error: </span>{{ $errors->first() }}</div>
                <button type="button" class="btn-close prism-toast-close" aria-label="Close"></button>
            </div>
        </div>
    @endif

    @if(! $referral->isAcknowledged() && $referral->status === 'pending')
        <div class="prism-card referral-notice-card"
             style="border:1px solid var(--blue);">
            <div class="prism-card-body">
                <div class="referral-notice-body">
                    <div class="referral-notice-text">
                        <i class="bi bi-bell-fill"
                           style="color:var(--blue);font-size:1.2rem;
                                  flex-shrink:0;margin-top:2px;"></i>
                        <div>
                            <p style="font-weight:700;margin-bottom:4px;">
                                New Referral Received
                            </p>
                            <p style="font-size:0.84rem;margin-bottom:0;">
                                Acknowledge this referral to confirm you
                                have received it and will coordinate with
                                the student.
                            </p>
                        </div>
                    </div>
                    <form method="POST"
                          action="{{ route('guidance.referrals.acknowledge', $referral) }}"
                          class="referral-notice-action">
                        @csrf
                        <button type="submit"
                                class="prism-btn prism-btn-primary status-action">
                            Acknowledge Referral
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="privacy-notice referral-info-notice" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:var(--radius-sm);color:#526b8a;display:flex;gap:7px;align-items:flex-start;">
        <i class="bi bi-shield-lock-fill" style="flex-shrink:0;margin-top:1px;"></i>
        <span>Case details and disciplinary records are not accessible from this view in accordance with institutional data privacy policies. Only referral information is available here.</span>
    </div>

    <div class="referral-detail-grid">
        <div class="prism-card">
            <div class="prism-card-header">
                <h2 class="prism-card-title">Student</h2>
            </div>
            <div class="prism-card-body">
                <table class="prism-detail-table">
                    <tr>
                        <th>Name</th>
                        <td>{{ $referral->student?->list_name }}</td>
                    </tr>
                    <tr>
                        <th>Student No.</th>
                        <td class="prism-ref-number">
                            {{ $referral->student?->studentProfile?->student_number ?? '—' }}
                        </td>
                    </tr>
                    <tr>
                        <th>Program</th>
                        <td>{{ $referral->student?->studentProfile?->program ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Year</th>
                        <td>{{ $referral->student?->studentProfile?->year_level ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>School Status</th>
                        <td>{{ \App\Models\User::STUDENT_STATUSES[$referral->student?->student_status ?? ''] ?? 'Not Yet Confirmed' }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td style="word-break:break-all;">
                            {{ $referral->student?->email ?? '—' }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="prism-card">
            <div class="prism-card-header status-panel-header">
                <h2 class="prism-card-title">Referral Info</h2>
            </div>
            <div class="prism-card-body">
                <table class="prism-detail-table">
                    <tr><th>Date Referred</th><td>{{ $referral->referred_at->format('F d, Y') }}</td></tr>
                    <tr><th>Referred By</th><td>{{ $referral->referredBy?->list_name ?? '—' }}</td></tr>
                    <tr>
                        <th>Urgency</th>
                        <td>
                            @php $urgency = $referral->urgencyDetails(); @endphp
                            <div class="urgency-cell">
                                <span class="prism-badge {{ $urgency['badge'] }}">{{ $urgency['label'] }}</span>
                                <span class="urgency-turnaround">{{ $urgency['turnaround'] }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            @php
                                $statusMap = [
                                    'pending' => ['Pending', 'prism-badge-orange'],
                                    'completed' => ['Completed', 'prism-badge-green'],
                                    'incomplete' => ['Incomplete', 'prism-badge-red'],
                                ];
                                [$label, $badge] = $statusMap[$referral->status];
                            @endphp
                            <div class="urgency-cell">
                                <span class="prism-badge {{ $badge }}">{{ $label }}</span>
                                @if($referral->status === 'completed' && $referral->completed_at)
                                    <span class="urgency-turnaround">Fulfilled on {{ $referral->completed_at->format('F d, Y') }}. No further action required.</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @if($referral->acknowledged_at)
                        <tr><th>Acknowledged</th><td>{{ $referral->acknowledged_at->format('M d, Y \a\t g:i A') }}</td></tr>
                    @endif
                </table>

                <div class="referral-reason-block">
                    <span class="prism-detail-label">Referral Reason</span>
                    <div class="referral-reason-text {{ $referral->referral_reason ? '' : 'is-empty' }}">{{ $referral->referral_reason ?: 'No reason was recorded for this referral.' }}</div>
                </div>
            </div>
        </div>

        @if($referral->status === 'pending' && $referral->isAcknowledged())
        <div class="modal fade incomplete-modal" id="completeReferralModal" tabindex="-1" aria-labelledby="completeReferralModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="completeReferralModalLabel">Mark Referral Completed</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('guidance.referrals.complete', $referral) }}">
                        @csrf
                        <div class="modal-body">
                            <p class="modal-intro mb-0">Mark this referral as completed?</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="prism-btn prism-btn-primary">Confirm</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($referral->status === 'incomplete')
            <div class="prism-card non-compliance-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Non-Compliance Recorded</h2>
                    <span class="prism-badge prism-badge-red">Incomplete</span>
                </div>
                <div class="prism-card-body">
                    <div class="referral-reason-block" style="margin-top:0;">
                        <span class="prism-detail-label">Reason</span>
                        <div class="referral-reason-text">{{ $referral->incomplete_reason }}</div>
                    </div>
                    <div class="prism-note mt-3">
                        <i class="bi bi-info-circle me-1"></i>
                        This status is visible to the Student Discipline Office
                        for the required follow-up.
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if($referral->status === 'pending' && $referral->isAcknowledged())
        <div class="modal fade incomplete-modal" id="incompleteReferralModal" tabindex="-1" aria-labelledby="incompleteReferralModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="incompleteReferralModalLabel">Mark Referral Incomplete</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('guidance.referrals.incomplete', $referral) }}">
                        @csrf
                        <div class="modal-body">
                            <p class="modal-intro">Record the administrative reason for non-compliance. Do not include counseling session content or psychological assessment details.</p>
                            <label class="prism-label" for="incompleteReferralReason">Reason <span class="text-danger">*</span></label>
                            <textarea id="incompleteReferralReason" name="reason" class="prism-input" rows="4" required minlength="20" maxlength="1000" placeholder="State the administrative non-compliance reason."></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="prism-btn prism-btn-primary">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
