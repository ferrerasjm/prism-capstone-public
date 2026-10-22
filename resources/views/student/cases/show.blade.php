@extends('layouts.student')
@section('title', $case->case_number)
@section('css')
<style>
    .student-incident-meta th { width: 150px; padding-right: 16px; vertical-align: middle; }
    .student-incident-meta td { vertical-align: middle; }
    .student-offense-note { display: flex; align-items: center; gap: 8px; }
    .student-offense-note i { flex: 0 0 auto; line-height: 1; }
    .student-offense-note div { min-width: 0; }
    .student-notices-card { margin-top: 16px; }
    .student-notices-card .prism-card-body { padding: 14px; }
    /* Notice cards: a tinted glyph per notice type carries the identity, a hairline
       ring holds the card, and the whole tile is the link — the row used to be a flat
       grey box whose only affordance was a small text link at the bottom. */
    .student-notice-row {
        position: relative;
        padding: 14px 16px;
        border-radius: 12px;
        background: #fff;
        box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .09);
        transition: box-shadow .18s ease, background-color .18s ease;
    }
    .student-notice-row + .student-notice-row { margin-top: 10px; }
    /* Hover is a visible but neutral state change — a blue wash matched the
       icon's own light-blue background closely enough to wash the icon out, so
       this stays grey instead. The quiet corner link picks up the accent on the
       same trigger, confirming the whole tile is what opens. */
    .student-notice-row:has(.student-notice-link:hover),
    .student-notice-row:has(.student-notice-link:focus-visible) {
        background: #f8fafc;
        box-shadow: inset 0 0 0 1.5px rgba(15, 23, 42, .18);
    }
    .student-notice-row:has(.student-notice-link:hover) .student-notice-link,
    .student-notice-row:has(.student-notice-link:focus-visible) .student-notice-link { color: #3156c7; }
    .student-notice-icon {
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: #eff4ff;
        color: #3156c7;
        font-size: .95rem;
    }
    /* Top band: icon + title on the left, the "View Notice" affordance on the
       right. align-items:center lines the link up with the title (both centred
       on the 34px icon) instead of letting it float above. The date pills sit on
       their own line below, indented past the icon; the two never wrap apart. */
    .student-notice-top { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .student-notice-heading { display: flex; flex: 1 1 auto; min-width: 0; align-items: center; gap: 12px; }
    .student-notice-title { min-width: 0; color: #0f172a; font-size: .92rem; font-weight: 700; line-height: 1.35; }
    .student-notice-body { min-width: 0; margin-left: 46px; margin-top: 8px; }
    .student-notice-dates { display: flex; align-items: center; flex-wrap: nowrap; gap: 5px 6px; overflow-x: auto; }
    .student-notice-date {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: baseline;
        gap: 5px;
        padding: 2px 8px;
        border-radius: 999px;
        background: #f1f5f9;
        font-size: .68rem;
        white-space: nowrap;
    }
    .student-notice-date-label { color: #94a3b8; font-weight: 600; }
    .student-notice-date-value { color: #334155; font-weight: 700; }
    /* The deadline still needs to stand out from "Issued" — done with the same
       blue accent and a bold value, not a separate hue. */
    .student-notice-date.is-deadline { background: #eff4ff; }
    .student-notice-date.is-deadline .student-notice-date-label { color: #6b8afd; }
    .student-notice-date.is-deadline .student-notice-date-value { color: #3156c7; }
    /* A lapsed, still-unanswered NTE deadline: the pill goes red and its label
       reads "Overdue" rather than "Deadline". */
    .student-notice-date.is-overdue { background: #fee2e2; }
    .student-notice-date.is-overdue .student-notice-date-label { color: #dc2626; }
    .student-notice-date.is-overdue .student-notice-date-value { color: #991b1b; }
    /* A <button>, not an <a> — the notice opens in the shared attachment-preview
       modal (see partials.attachment-preview-modal) rather than a new tab, so
       the reset strips the browser's default button chrome. It is the visible
       affordance for a tile that is entirely clickable, so it stays a quiet
       grey with a disclosure chevron and only takes the accent on row hover —
       no second bold element competing with the title. */
    .student-notice-link {
        display: inline-flex;
        flex: 0 0 auto;
        align-items: center;
        gap: 4px;
        padding: 0;
        border: 0;
        background: none;
        color: #64748b;
        font: inherit;
        font-size: .72rem;
        font-weight: 600;
        white-space: nowrap;
        text-decoration: none;
        cursor: pointer;
        transition: color .18s ease;
    }
    .student-notice-link i { font-size: .82rem; }
    /* The tile is one target: the link's box is stretched over the whole card. */
    .student-notice-link::after { content: ""; position: absolute; inset: 0; border-radius: inherit; }
    .student-incident-none { margin: 0; color: #94a3b8; font-size: .76rem; }
    .student-incident-summary { padding: 12px 14px; border-radius: 8px; background: #f8fafc; color: #334155; font-size: .84rem; line-height: 1.6; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
    .student-explanation-status { padding: 3px 9px; border-radius: 999px; background: #ecfdf3; color: #15803d; font-size: .66rem; font-weight: 700; }
    .student-explanation-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e9f2; }
    .student-explanation-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 8px; color: #0f172a; font-size: .86rem; font-weight: 700; }
    .student-explanation-text { max-height: 340px; overflow-y: auto; padding: 16px 18px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; color: #334155; font-size: .84rem; line-height: 1.7; white-space: pre-wrap; overflow-wrap: anywhere; word-break: break-word; }
    .student-explanation-meta { display: flex; flex-wrap: wrap; gap: 6px 16px; margin-top: 12px; color: #64748b; font-size: .7rem; }
    .student-case-grid { display: grid; grid-template-columns: minmax(280px, 1fr) minmax(0, 2.1fr); gap: 20px 28px; align-items: start; }
    .student-case-sidebar, .student-case-main { display: flex; flex-direction: column; gap: 20px; }
    .student-notices-card { max-height: 380px; margin-top: 0; }
    .student-notices-card > .prism-card-body { overflow-y: auto; }
    .student-upcc-card { margin-bottom: 0; }
    .student-appeal-note { display: flex; align-items: center; gap: 8px; border-color: #bfdbfe; }
    .student-appeal-note i { flex: 0 0 auto; line-height: 1; }
    .student-attachment { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 9px; background: #fff; }
    .student-attachment + .student-attachment { margin-top: 8px; }
    .student-attachment-name { color: #1e293b; font-size: .82rem; font-weight: 600; overflow-wrap: anywhere; }
    .student-attachment-meta { margin-top: 2px; color: #64748b; font-size: .72rem; }
    .student-attachment-view { min-height: 34px !important; padding: 6px 11px !important; font-size: .74rem !important; }
    @media(max-width:575.98px){.student-attachment{align-items:flex-start;flex-direction:column}.student-attachment-view{justify-content:center;width:100%}}
    .student-appeal-dropzone {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 28px 20px;
        border: 1.5px dashed #c7d2e8;
        border-radius: 14px;
        background: #f8faff;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .student-appeal-dropzone:hover,
    .student-appeal-dropzone:focus-visible { border-color: #93b4f4; background: #f2f6ff; }
    .student-appeal-dropzone.is-dragover { border-color: #3156c7; background: #eef3ff; }
    .student-appeal-dropzone.has-files { border-style: solid; border-color: #93b4f4; }
    .student-appeal-dropzone-icon {
        display: grid;
        place-items: center;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #e7ecfb;
        color: #3156c7;
        font-size: 1.15rem;
    }
    .student-appeal-dropzone-text { max-width: 100%; overflow-wrap: anywhere; font-size: .86rem; color: #475569; }
    .student-appeal-dropzone-link { color: #3156c7; font-weight: 700; }
    .student-appeal-dropzone-hint { font-size: .74rem; color: #94a3b8; }
    .student-appeal-dropzone-files { display: none; align-items: center; gap: 6px; max-width: 100%; color: #1e293b; font-size: .8rem; font-weight: 600; }
    .student-appeal-dropzone-files span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .student-appeal-dropzone.has-files .student-appeal-dropzone-files { display: flex; }
    .student-appeal-dropzone.has-files .student-appeal-dropzone-text,
    .student-appeal-dropzone.has-files .student-appeal-dropzone-hint { display: none; }
    .student-appeal-actions { display: flex; justify-content: flex-end; gap: 8px; }
    .student-no-appeal-btn,
    .student-no-appeal-btn:hover,
    .student-no-appeal-btn:focus,
    .student-no-appeal-btn:active { box-shadow: none; }
    .student-case-details-card,
    .student-notices-card { display: flex; flex-direction: column; }
    .student-case-details-card > .prism-card-body,
    .student-notices-card > .prism-card-body { flex: 1; }
    @media (max-width: 991.98px) {
        .student-case-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection
@section('content')
@php
    $caseNotices = $case->notices;
    $referral = $case->upccReferral;
    $studentState = $case->studentWorkflowState();
    $conferenceSchedule = $referral?->scheduled_conference_at?->format('F d, Y \a\t g:i A');
    $appealDeadline = $referral?->appeal_window_end?->format('F d, Y');

    // NTE response prompt — drives the header button, the banner below, and the
    // overdue variants of the stage panel and status badge once the deadline lapses.
    $showNtePrompt = $case->status === 'pending_nte' && ! $case->explanation;
    $activeNte = $showNtePrompt
        ? $case->notices->where('notice_type', 'notice_to_explain')->where('is_voided', false)->first()
        : null;
    $nteOverdue = (bool) $activeNte?->isOverdue();

    $stage = match ($studentState) {
        'open' => ['Initial Review', 'The Student Discipline Office is reviewing the incident and determining the applicable offense. No response is required yet.', 'bi-search'],
        'pending_nte' => ['Explanation Required', 'A Notice to Explain has been issued. Review the notice and submit your explanation before the deadline.', 'bi-pencil-square'],
        'awaiting_explanation' => ['Explanation Received', 'Your explanation was submitted and is awaiting evaluation by the Discipline Officer.', 'bi-hourglass-split'],
        'under_review' => ['Under Evaluation', 'The Discipline Officer is evaluating the available information and determining the appropriate next step.', 'bi-clipboard-check'],
        'upcc_awaiting_schedule' => ['Awaiting Case Conference Schedule', 'The case has been referred for formal review. Hearing information will appear here once issued.', 'bi-people'],
        'upcc_scheduled' => ['Case Conference Scheduled', 'Your case conference is scheduled'.($conferenceSchedule ? ' for '.$conferenceSchedule : '').'. Please review the issued notice for details.', 'bi-calendar-event'],
        'upcc_in_progress' => ['Case Conference in Progress', 'The UPCC is reviewing the case conference record and supporting information.', 'bi-people-fill'],
        'upcc_decision_pending_release' => ['Decision Pending Release', 'The case conference has concluded and the formal decision is being prepared for distribution.', 'bi-file-earmark-text'],
        'upcc_appeal_window' => ['Appeal Window Open', 'The UPCC decision has been distributed. You may file an appeal until '.($appealDeadline ?: 'the indicated appeal deadline').'.', 'bi-hourglass-bottom'],
        'upcc_appeal_filed' => ['Appeal Filed', 'An appeal or request for reconsideration has been recorded and is awaiting UPCC review.', 'bi-arrow-counterclockwise'],
        'upcc_appeal_window_closed' => ['Appeal Window Closed', 'The appeal window has closed. The decision is awaiting finalization by the Discipline Office.', 'bi-lock'],
        'upcc_decision_final' => ['Decision Finalized', 'The UPCC decision has been finalized and the case is moving to resolution processing.', 'bi-check2-circle'],
        'pending_resolution' => ['Pending Resolution', 'Review is complete and the formal resolution is being prepared.', 'bi-file-earmark-check'],
        'resolved' => ['Resolved', 'A formal resolution has been recorded. Review the resolution and active interventions below.', 'bi-check-circle'],
        'dismissed' => ['Dismissed', 'This case is closed and no action is required from you.', 'bi-x-circle'],
        'voided' => ['Voided', 'This case record has been voided. No action is required from you.', 'bi-slash-circle'],
        default => ['In Progress', 'The case is currently being processed by the Student Discipline Office.', 'bi-clock'],
    };
    if ($nteOverdue) {
        $stage = [
            'Explanation Overdue',
            'The response deadline has passed. You may still submit your written explanation until the Discipline Officer proceeds with the case.',
            'bi-exclamation-triangle',
        ];
    }
    if ($studentState === 'dismissed' && $referral?->outcome === 'no_violation') {
        $stage = [
            'No Violation Found',
            'The University Panel on Cases and Conduct reviewed this case and found no violation. The case is closed and no sanction applies.',
            'bi-shield-check',
        ];
    }
    $statusBadge = match ($studentState) {
        'open' => ['Initial Review', 'prism-badge-blue'],
        'pending_nte' => ['Explanation Required', 'prism-badge-orange'],
        'awaiting_explanation' => ['Awaiting Evaluation', 'prism-badge-violet'],
        'under_review' => ['Under Review', 'prism-badge-teal'],
        'upcc_awaiting_schedule' => ['Awaiting Schedule', 'prism-badge-orange'],
        'upcc_scheduled' => ['Conference Scheduled', 'prism-badge-blue'],
        'upcc_in_progress' => ['Conference in Progress', 'prism-badge-blue'],
        'upcc_decision_pending_release' => ['Decision Pending Release', 'prism-badge-violet'],
        'upcc_appeal_window' => ['Appeal Window Open', 'prism-badge-orange'],
        'upcc_appeal_filed' => ['Appeal Filed', 'prism-badge-violet'],
        'upcc_appeal_window_closed' => ['Appeal Window Closed', 'prism-badge-gray'],
        'upcc_decision_final' => ['Decision Finalized', 'prism-badge-green'],
        'pending_resolution' => ['Pending Resolution', 'prism-badge-amber'],
        'resolved' => ['Resolved', 'prism-badge-green'],
        'dismissed' => ['Dismissed', 'prism-badge-gray'],
        'voided' => ['Voided', 'prism-badge-red'],
        default => [ucwords(str_replace('_', ' ', $studentState)), 'prism-badge-blue'],
    };
    if ($nteOverdue) {
        $statusBadge = ['Explanation Overdue', 'prism-badge-red'];
    }
    if ($studentState === 'dismissed' && $referral?->outcome === 'no_violation') {
        $statusBadge = ['No Violation Found', 'prism-badge-green'];
    }
@endphp
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Cases', 'url' => route('student.cases.index')],
            ['label' => $case->case_number],
        ]" />
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <h1 class="prism-page-title">{{ $case->case_number }}</h1>
                <p class="prism-page-sub">Opened {{ $case->created_at->format('F d, Y') }}</p>
            </div>
            @if($showNtePrompt)
                <div class="d-flex gap-2 mt-2">
                    <a href="{{ route('student.cases.explain', $case) }}" class="prism-btn prism-btn-primary">Respond Now</a>
                </div>
            @endif
        </div>
    </div>

    @if($showNtePrompt)
        <div class="prism-alert {{ $nteOverdue ? 'prism-alert-danger' : 'prism-alert-warning' }} mb-4">
            <span style="font-size:0.84rem;">
                @if($nteOverdue)
                    Your written explanation is overdue.
                    @if($activeNte)
                        The deadline was <strong>{{ $activeNte->response_deadline->format('F d, Y') }}</strong>.
                    @endif
                    You may still submit it until the Discipline Officer proceeds with the case.
                @else
                    You are required to submit your written explanation.
                    @if($activeNte)
                        Deadline: <strong>{{ $activeNte->response_deadline->format('F d, Y') }}</strong>.
                    @endif
                @endif
            </span>
        </div>
    @endif

    <div class="student-case-grid">
        <div class="student-case-sidebar">
            <div class="prism-card student-case-details-card">
                <div class="prism-card-header"><h2 class="prism-card-title">Case Details</h2></div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr><th>Case No.</th><td class="prism-ref-number">{{ $case->case_number }}</td></tr>
                        <tr><th>Type</th><td><span class="prism-badge {{ $case->case_type === 'minor' ? 'prism-badge-blue' : ($case->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}">{{ $case->case_type ? ucfirst($case->case_type) : 'Unclassified' }}</span></td></tr>
                        <tr><th>Status</th><td><span class="prism-badge {{ $statusBadge[1] }}">{{ $statusBadge[0] }}</span></td></tr>
                        <tr><th>Opened</th><td>{{ $case->created_at->format('M d, Y') }}</td></tr>
                        <tr><th>Last Updated</th><td>{{ $case->updated_at->format('M d, Y') }}</td></tr>
                        @if($case->resolution)
                            <tr><th>Sanction</th><td>{{ \App\Models\CaseResolution::interventionLabel($case->resolution->intervention_category) }}</td></tr>
                        @endif
                    </table>
                    <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <span style="display:flex;align-items:center;justify-content:center;width:32px;height:32px;flex:0 0 32px;border-radius:8px;{{ $nteOverdue ? 'background:#fee2e2;color:#dc2626;' : 'background:var(--blue-soft);color:var(--blue);' }}">
                                <i class="bi {{ $stage[2] }}"></i>
                            </span>
                            <div>
                                <div style="color:var(--text-muted);font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;">Current Stage</div>
                                <div style="margin-top:2px;color:#0f172a;font-size:.82rem;font-weight:700;">{{ $stage[0] }}</div>
                                <p style="margin:4px 0 0;color:var(--text-secondary);font-size:.72rem;line-height:1.55;">{{ $stage[1] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($caseNotices->isNotEmpty())
                <div class="prism-card student-notices-card">
                    <div class="prism-card-header"><h2 class="prism-card-title">Notices</h2></div>
                    <div class="prism-card-body">
                        @foreach($caseNotices as $notice)
                            @php
                                // Title per notice type. The glyph and accent colour stay the same
                                // across every type — one blue identity for "a notice", rather than a
                                // different hue per type competing with the status/deadline pills.
                                [$noticeTitle, $noticeUrl] = match ($notice->notice_type) {
                                    'notice_to_explain' => ['Notice to Explain', route('student.cases.nte-notice', $case)],
                                    'notice_of_hearing' => ['Notice of Hearing', route('student.cases.hearing-notice', $case)],
                                    'notice_of_formative_intervention' => ['Notice of Formative Intervention', route('student.cases.nfi', $case)],
                                    default => [ucwords(str_replace('_', ' ', $notice->notice_type)), null],
                                };
                            @endphp
                            <div class="student-notice-row">
                                <div class="student-notice-top">
                                    <div class="student-notice-heading">
                                        <span class="student-notice-icon" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></span>
                                        <div class="student-notice-title">{{ $noticeTitle }}</div>
                                    </div>
                                    @if($noticeUrl)
                                        <button type="button" class="student-notice-link"
                                                data-attachment-preview
                                                data-attachment-url="{{ $noticeUrl }}"
                                                data-attachment-name="{{ $noticeTitle }}"
                                                data-attachment-type="pdf">
                                            View Notice
                                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                        </button>
                                    @endif
                                </div>
                                <div class="student-notice-body">
                                    <div class="student-notice-dates">
                                        <span class="student-notice-date">
                                            <span class="student-notice-date-label">Issued</span>
                                            <span class="student-notice-date-value">{{ $notice->issued_at->format('M d, Y') }}</span>
                                        </span>
                                        @if($notice->response_deadline)
                                            <span class="student-notice-date is-deadline{{ $notice->isOverdue() ? ' is-overdue' : '' }}">
                                                <span class="student-notice-date-label">{{ $notice->isOverdue() ? 'Overdue' : 'Deadline' }}</span>
                                                <span class="student-notice-date-value">{{ $notice->response_deadline->format('M d, Y') }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        <div class="student-case-main">

            @if($referral?->decision_distributed_at)
                <div class="prism-card student-upcc-card">
                    <div class="prism-card-header prism-card-header--blue" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <h2 class="prism-card-title">UPCC Decision</h2>
                        @if($referral->decision_final_at)
                            <span class="prism-badge prism-badge-green">Finalized</span>
                        @elseif($referral->appeal_filed)
                            <span class="prism-badge prism-badge-violet">Appeal Filed</span>
                        @elseif($referral->isAppealWindowOpen())
                            <span class="prism-badge prism-badge-orange">Appeal Window Open</span>
                        @else
                            <span class="prism-badge prism-badge-gray">Appeal Window Closed</span>
                        @endif
                    </div>
                    <div class="prism-card-body">
                        <table class="prism-detail-table mb-3">
                            <tr>
                                <th>Distributed</th>
                                <td>{{ $referral->decision_distributed_at->format('F d, Y \a\t g:i A') }}</td>
                            </tr>
                            <tr>
                                <th>Appeal Deadline</th>
                                <td>
                                    {{ $referral->appeal_window_end?->format('F d, Y') ?? 'Not specified' }}
                                    @if($referral->appeal_window_end && !$referral->isAppealWindowOpen())
                                        <span style="font-size:0.78rem;color:var(--text-muted);margin-left:6px;">Closed</span>
                                    @endif
                                </td>
                            </tr>
                            @if($referral->appeal_filed)
                                <tr>
                                    <th>Appeal Submitted</th>
                                    <td>{{ $referral->appeal_filed_at?->format('F d, Y \a\t g:i A') ?? 'Recorded' }}</td>
                                </tr>
                            @endif
                            @if($referral->presidents_council_action)
                                <tr>
                                    <th>President's Council</th>
                                    <td>{{ ucfirst($referral->presidents_council_action) }}</td>
                                </tr>
                            @endif
                        </table>

                        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:12px;">
                            <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;"><i class="bi bi-file-earmark-text-fill me-1"></i> Decision Summary</div>
                            <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $referral->decision_summary ?: 'The decision summary has not been provided.' }}</div>
                        </div>

                        @if($referral->presidents_council_notes)
                            <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:12px;">
                                <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;"><i class="bi bi-chat-square-quote-fill me-1"></i> Council Notes</div>
                                <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $referral->presidents_council_notes }}</div>
                            </div>
                        @endif

                        @if($referral->appeal_filed)
                            <div class="prism-note mb-3">
                                <i class="bi bi-arrow-counterclockwise"></i>
                                <div>Your appeal has been recorded and is awaiting reconsideration review.</div>
                            </div>
                            @if($referral->appeal_notes)
                                @php $appealNotesParts = $referral->appealNotesParts(); @endphp
                                <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
                                    <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;"><i class="bi bi-pencil-square me-1"></i> Appeal Notes</div>
                                    <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $appealNotesParts['original'] }}</div>
                                    @if($appealNotesParts['reconsideration_summary'])
                                        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;">
                                                <span style="font-size:0.7rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;">Reconsideration Outcome</span>
                                                <span style="font-size:0.72rem;color:var(--text-muted);">{{ $appealNotesParts['reconsideration_date'] }}</span>
                                            </div>
                                            <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $appealNotesParts['reconsideration_summary'] }}</div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if($referral->appealAttachments->isNotEmpty())
                                <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-top:12px;">
                                    <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;"><i class="bi bi-paperclip me-1"></i> Supporting Attachments</div>
                                    @php
                                        $appealIconMap = ['pdf' => 'bi-file-earmark-pdf', 'jpg' => 'bi-file-earmark-image', 'jpeg' => 'bi-file-earmark-image', 'png' => 'bi-file-earmark-image', 'doc' => 'bi-file-earmark-word', 'docx' => 'bi-file-earmark-word'];
                                    @endphp
                                    @foreach($referral->appealAttachments as $attachment)
                                        @php $appealIcon = $appealIconMap[$attachment->file_type] ?? 'bi-file-earmark'; @endphp
                                        <div class="student-attachment">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi {{ $appealIcon }}" style="font-size:1.1rem;color:#64748b;"></i>
                                                <div>
                                                    <div class="student-attachment-name">{{ $attachment->file_name }}</div>
                                                    <div class="student-attachment-meta">
                                                        {{ strtoupper($attachment->file_type) }} &middot;
                                                        {{ number_format($attachment->file_size_bytes / 1024, 1) }} KB
                                                    </div>
                                                </div>
                                            </div>
                                            <button type="button"
                                               class="prism-btn prism-btn-outline student-attachment-view"
                                               data-attachment-preview
                                               data-attachment-url="{{ route('student.cases.appeal-attachments.view', [$case, $attachment]) }}"
                                               data-attachment-name="{{ $attachment->file_name }}"
                                               data-attachment-type="{{ $attachment->file_type }}">
                                                <i class="bi bi-eye"></i> View
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        @elseif($referral->isAppealWindowOpen())
                            <div class="prism-note student-appeal-note">
                                <i class="bi bi-info-circle"></i>
                                <div>
                                    If you intend to appeal, submit your appeal before
                                    <strong>{{ $referral->appeal_window_end?->format('F d, Y') }}</strong>.
                                </div>
                            </div>
                            <form method="POST"
                                  id="appealForm"
                                  action="{{ route('student.cases.appeal.store', $case) }}"
                                  enctype="multipart/form-data"
                                  class="mt-3">
                                @csrf
                                <label class="prism-label">
                                    Appeal Grounds / Reason <span class="text-danger">*</span>
                                </label>
                                <textarea name="appeal_notes"
                                          class="prism-input"
                                          rows="5"
                                          minlength="20"
                                          maxlength="5000"
                                          required
                                          placeholder="State the grounds for your appeal or request for reconsideration. Include the specific part of the decision you are appealing and any supporting context.">{{ old('appeal_notes') }}</textarea>
                                @error('appeal_notes')
                                    <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                @enderror
                                <div class="mt-3">
                                    <label class="prism-label">
                                        Supporting Attachments
                                        <span class="text-muted" style="font-weight:400;">(optional)</span>
                                    </label>
                                    <label for="appealAttachments" class="student-appeal-dropzone" id="appealDropzone">
                                        <div class="student-appeal-dropzone-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
                                        <div class="student-appeal-dropzone-text">
                                            <span class="student-appeal-dropzone-link">Click here</span> to upload your files or drag and drop.
                                        </div>
                                        <div class="student-appeal-dropzone-hint">PDF, JPG, PNG, DOC, or DOCX · Up to 3 files, 5 MB each</div>
                                        <div class="student-appeal-dropzone-files">
                                            <i class="bi bi-paperclip"></i>
                                            <span id="appealFileNames"></span>
                                        </div>
                                    </label>
                                    <input id="appealAttachments"
                                           type="file"
                                           name="attachments[]"
                                           class="d-none"
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                           multiple>
                                    @error('attachments')
                                        <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                    @enderror
                                    @error('attachments.*')
                                        <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="student-appeal-actions mt-3">
                                    <button type="button"
                                            class="prism-btn prism-btn-outline student-no-appeal-btn"
                                            data-bs-toggle="modal"
                                            data-bs-target="#noAppealModal">
                                        No Appeal
                                    </button>
                                    <button type="button"
                                            id="submitAppealTrigger"
                                            class="prism-btn prism-btn-primary">
                                        Submit Appeal
                                    </button>
                                </div>
                            </form>
                        @else
                            <div class="prism-note student-appeal-note">
                                <i class="bi bi-info-circle"></i>
                                <div>The appeal window for this decision is no longer open.</div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="prism-card student-incident-card">
                <div class="prism-card-header"><h2 class="prism-card-title">Incident and Allegation</h2></div>
                <div class="prism-card-body">
                    @if($case->caseOffenses->where('status', 'confirmed')->isNotEmpty())
                        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:12px 14px;margin-bottom:16px;">
                            <div style="font-size:0.7rem;font-weight:700;color:#1D4ED8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">Confirmed Offense{{ $case->caseOffenses->where('status', 'confirmed')->count() > 1 ? 's' : '' }}</div>
                            @foreach($case->caseOffenses->where('status', 'confirmed') as $charge)
                                <div style="display:flex;gap:8px;{{ !$loop->last ? 'padding-bottom:8px;margin-bottom:8px;border-bottom:1px solid #BFDBFE;' : '' }}">
                                    <div style="flex:0 0 auto;min-width:16px;font-variant-numeric:tabular-nums;font-weight:700;color:#1E3A8A;">{{ $loop->iteration }}.</div>
                                    <div>
                                        <div style="font-weight:700;color:#1E3A8A;">{{ $charge->offense?->code }} — {{ $charge->offense?->title }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="prism-note student-offense-note mb-3">
                            <i class="bi bi-info-circle"></i>
                            <div>The applicable offense has not yet been confirmed. The case remains unclassified while the report is reviewed.</div>
                        </div>
                    @endif

                    @if($case->incidentReport)
                        <table class="prism-detail-table mb-3 student-incident-meta">
                            {{-- The Notice to Explain cites this number, so the student needs it here to match the two up. --}}
                            <tr><th>Incident Report</th><td><span class="prism-ref-number">{{ $case->incidentReport->report_number }}</span></td></tr>
                            <tr><th>Incident Date</th><td>{{ $case->incidentReport->date_of_incident->format('F j, Y') }}</td></tr>
                            @if($case->incidentReport->time_of_incident)
                                <tr><th>Time</th><td>{{ \Carbon\Carbon::parse($case->incidentReport->time_of_incident)->format('g:i A') }}</td></tr>
                            @endif
                            @if($case->incidentReport->location)
                                <tr><th>Location</th><td>{{ $case->incidentReport->location }}</td></tr>
                            @endif
                        </table>
                        <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Incident Summary</div>
                        <div class="student-incident-summary">{{ trim($case->incidentReport->description) }}</div>
                    @elseif($case->caseOffenses->where('status', 'confirmed')->isNotEmpty())
                        {{-- Only worth saying when the note above is absent; otherwise the card
                             would state twice that there is nothing to show yet. --}}
                        <p class="student-incident-none">No incident report is linked to this case.</p>
                    @endif

                    @if($case->explanation)
                        @php
                            $isNoResponseRecord = $case->explanation->submission_channel === 'no_response';
                        @endphp
                        <div class="student-explanation-section">
                            <div class="student-explanation-heading">
                                <span>{{ $isNoResponseRecord ? 'Discipline Office Record' : 'Your Submitted Explanation' }}</span>
                                @unless($isNoResponseRecord)
                                    <span class="student-explanation-status">Received</span>
                                @endunless
                            </div>
                            <div class="student-explanation-text">{{ trim($case->explanation->explanation_text ?? '') }}</div>
                            <div class="student-explanation-meta">
                                <span>{{ $isNoResponseRecord ? 'Recorded' : 'Submitted' }} {{ $case->explanation->submitted_at->format('M d, Y \a\t g:i A') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- The decision in words for every category. Category 2's corrective
                 measures live in the Notices panel with the rest of the notices. --}}
            @if($case->resolution)
                @php
                    $decision = match ((int) $case->resolution->intervention_category) {
                        1 => [
                            "Category 1 — Probation",
                            "You are placed on conduct probation and referred for counseling. Another major offense during this period may lead to suspension or non-readmission.",
                        ],
                        2 => [
                            "Category 2 — Formative Intervention",
                            "Corrective measures have been assigned. See the Notice of Formative Intervention under Notices for the full details.",
                        ],
                        3 => [
                            "Category 3 — Non-Readmission",
                            "You may complete the current term but will not be permitted to enroll for the following term.",
                        ],
                        4 => [
                            "Category 4 — Exclusion",
                            "Your name has been dropped from the university rolls, effective immediately.",
                        ],
                        5 => [
                            "Category 5 — Expulsion",
                            "You have been permanently removed from the University, effective immediately.",
                        ],
                        default => ["Resolution recorded", "A formal resolution has been recorded for this case."],
                    };
                    $category = \App\Models\OffenseCategory::find((int) $case->resolution->intervention_category);
                    if ($category) {
                        $decision[0] = \App\Models\CaseResolution::interventionLabel($category->number);
                        $decision[1] = $category->description ?: $decision[1];
                    }
                @endphp

                <div class="prism-card">
                    <div class="prism-card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <h2 class="prism-card-title" style="margin:0;">Resolution</h2>
                        @if($case->resolution->resolved_at)
                            <span style="font-size:0.78rem;color:var(--text-tertiary,#9CA3AF);">
                                Recorded {{ $case->resolution->resolved_at->format('M d, Y') }}
                            </span>
                        @endif
                    </div>
                    <div class="prism-card-body">
                        <div style="font-size:0.95rem;font-weight:700;color:#0f172a;margin-bottom:4px;">{{ $decision[0] }}</div>
                        <div style="font-size:0.86rem;line-height:1.6;color:#475569;">{{ $decision[1] }}</div>

                        @if($case->resolution->probation_end_date)
                            <div style="margin-top:14px;font-size:0.84rem;color:#334155;">
                                <strong>Probation ends:</strong>
                                {{ $case->resolution->probation_end_date->format('F d, Y') }}
                            </div>
                        @endif

                        @if($case->resolution->notes)
                            <div style="margin-top:16px;padding-top:14px;border-top:1px solid var(--border);">
                                <div style="color:var(--text-muted);font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;">
                                    Notes from the Discipline Office
                                </div>
                                <div style="font-size:0.86rem;line-height:1.6;color:#334155;">{{ $case->resolution->notes }}</div>
                            </div>
                        @endif

                        {{-- prism-note is a flex row with a gap, so every bare text run
                             becomes its own flex item; the sentence has to be one element
                             or the gap opens up around the inline <strong>. --}}
                        <div class="prism-note" style="margin-top:16px;">
                            <i class="bi bi-info-circle"></i>
                            <span>Any sanctions assigned to you are listed under <strong>My Sanctions</strong>.</span>
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>

@if($referral?->isAppealWindowOpen())
    <div class="modal fade prism-modal" id="submitAppealModal" tabindex="-1"
         aria-labelledby="submitAppealModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                            Appeal Decision
                        </div>
                        <h2 class="modal-title" id="submitAppealModalLabel">Submit Appeal</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                        Submit this appeal for reconsideration? You cannot submit another appeal for this decision.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="appealForm" class="prism-btn prism-btn-primary">Submit Appeal</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade prism-modal" id="noAppealModal" tabindex="-1"
         aria-labelledby="noAppealModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                            Appeal Decision
                        </div>
                        <h2 class="modal-title" id="noAppealModalLabel">Confirm No Appeal</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="prism-note student-appeal-note">
                        <i class="bi bi-info-circle"></i>
                        <div>
                            Confirming means you will not appeal this decision. The UPCC decision will become final immediately, and the case will proceed to resolution.
                        </div>
                    </div>
                    <p style="margin:14px 0 0;color:#64748b;font-size:.78rem;line-height:1.6;">
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('student.cases.appeal.waive', $case) }}">
                        @csrf
                        <button type="submit" class="prism-btn prism-btn-primary">Confirm No Appeal</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@include('partials.attachment-preview-modal')
@push('scripts')
<script>
(() => {
    const appealDropzone = document.getElementById('appealDropzone');
    const appealFileInput = document.getElementById('appealAttachments');
    const appealFileNames = document.getElementById('appealFileNames');

    if (appealDropzone && appealFileInput && appealFileNames) {
        const showSelectedFiles = () => {
            const files = Array.from(appealFileInput.files ?? []);
            appealDropzone.classList.toggle('has-files', files.length > 0);
            appealFileNames.textContent = files.map((file) => file.name).join(', ');
        };

        appealFileInput.addEventListener('change', showSelectedFiles);

        ['dragenter', 'dragover'].forEach((eventName) => {
            appealDropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                appealDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            appealDropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                appealDropzone.classList.remove('is-dragover');
            });
        });

        appealDropzone.addEventListener('drop', (event) => {
            const droppedFiles = event.dataTransfer?.files;
            if (droppedFiles?.length) {
                appealFileInput.files = droppedFiles;
                showSelectedFiles();
            }
        });
    }

    const submitAppealTrigger = document.getElementById('submitAppealTrigger');
    const appealForm = document.getElementById('appealForm');
    const submitAppealModalEl = document.getElementById('submitAppealModal');
    if (submitAppealTrigger && appealForm && submitAppealModalEl) {
        submitAppealTrigger.addEventListener('click', () => {
            if (!appealForm.reportValidity()) return;
            bootstrap.Modal.getOrCreateInstance(submitAppealModalEl).show();
        });
    }

    const statusUrl = @json(route('student.cases.status', $case));
    const initial = {
        student_state: @json($studentState),
        updated_at: @json($case->updated_at?->toISOString()),
        upcc_referral_updated_at: @json($case->upccReferral?->updated_at?->toISOString()),
    };
    if (!window.PrismPoll) return;

    // Skip auto-reload while a field is focused/unsaved so we don't yank the page mid-edit; recheck next poll tick.
    const hasUnsavedInput = () => {
        const active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA'
            || (active.tagName === 'INPUT' && !['submit', 'button', 'checkbox', 'radio', 'hidden'].includes(active.type)))) {
            return true;
        }
        return Array.from(document.querySelectorAll('textarea')).some((el) => el.value.trim() !== '');
    };

    let pendingReload = false;

    const poll = window.PrismPoll.watch(statusUrl, {
        interval: 15000,
        onData: (current) => {
            const changed = current.student_state !== initial.student_state
                || current.updated_at !== initial.updated_at
                || current.upcc_referral_updated_at !== initial.upcc_referral_updated_at;
            if (changed) pendingReload = true;

            if (pendingReload && !hasUnsavedInput()) {
                poll.stop();
                window.location.reload();
            }
        },
    });
})();
</script>
@endpush
@endsection
