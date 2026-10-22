@extends('layouts.staff')
@section('title', 'Sanction Details')
@section('css')
<style>
    .sanction-overview-copy { margin: 0; color: #475569; font-size: .88rem; line-height: 1.65; }
    .sanction-overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0; margin-top: 18px; padding-top: 16px; border-top: 1px solid #edf1f6; }
    .sanction-overview-item { min-width: 0; padding: 0 16px; }
    .sanction-overview-item + .sanction-overview-item { border-left: 1px solid #e5eaf2; }
    .sanction-overview-label { display: block; margin-bottom: 4px; color: #94a3b8; font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .sanction-overview-value { color: #172033; font-size: .82rem; font-weight: 650; }
    /* Same disclosure design as "Supporting Information" on the case detail page. */
    .case-supporting-disclosure { overflow: hidden; border: 1px solid #DDE3EE; border-radius: 8px; background: #FFFFFF; }
    .case-supporting-disclosure>summary { background: #FAFBFE; display: flex; align-items: center; justify-content: space-between; gap: 16px; min-height: 48px; padding: 13px 16px; color: #0F172A; font-size: .98rem; font-weight: 700; cursor: pointer; list-style: none; }
    .case-supporting-disclosure>summary::-webkit-details-marker { display: none; }
    .case-supporting-disclosure>summary:focus { outline: none; }
    .case-supporting-disclosure>summary:focus-visible { outline: 2px solid #3156C7; outline-offset: 2px; }
    .case-supporting-toggle { display: inline-flex; align-items: center; gap: 6px; flex: 0 0 auto; color: #3156C7; font-size: .75rem; font-weight: 700; }
    .case-supporting-hide { display: none; }
    .case-supporting-toggle i { font-size: .7rem; transition: transform .15s ease; }
    .case-supporting-disclosure[open]>summary .case-supporting-show { display: none; }
    .case-supporting-disclosure[open]>summary .case-supporting-hide { display: inline; }
    .case-supporting-disclosure[open]>summary .case-supporting-toggle i { transform: rotate(180deg); }
    .case-supporting-disclosure[open]>summary { border-bottom: 1px solid #E5E9F2; }
    .case-supporting-content { display: grid; gap: 14px; padding: 14px; background: #FFFFFF; }
    .sanction-timeline { display: grid; gap: 0; }
    .sanction-timeline-item { position: relative; display: grid; grid-template-columns: 12px minmax(0, 1fr); column-gap: 12px; padding-bottom: 16px; }
    .sanction-timeline-item:last-child { padding-bottom: 0; }
    .sanction-timeline-item:not(:last-child)::before { position: absolute; top: 12px; bottom: 0; left: 5px; width: 1px; background: #dbe3f2; content: ''; }
    .sanction-timeline-dot { position: relative; z-index: 1; width: 11px; height: 11px; margin-top: 3px; border: 2px solid #3156c7; border-radius: 50%; background: #3156c7; box-shadow: 0 0 0 3px #edf3ff; }
    .sanction-timeline-title { color: #172033; font-size: .82rem; font-weight: 700; }
    .sanction-timeline-date { margin-top: 2px; color: #94a3b8; font-size: .7rem; }
    .tracking-form-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; }
    .monitoring-actions { display:flex; justify-content:flex-end; margin-top:14px; }
    .monitoring-form-title, .monitoring-cases-title { margin:0 0 10px; color:#172033; font-size:.9rem; font-weight:700; }
    .tracking-record { display:grid; grid-template-columns:110px minmax(0,1fr) auto; gap:14px; align-items:start; padding:13px 0; border-bottom:1px solid var(--border); }
    .tracking-record:last-child { border-bottom:0; }
    .tracking-record .prism-ref-number { font-size:.82rem; }
    .monitoring-period { padding:12px 14px; border:1px solid #bae6fd; border-radius:8px; background:#f0f9ff; color:#0c4a6e; font-size:.8rem; line-height:1.5; }
    .monitoring-cases-head { display:flex; align-items:center; gap:8px; margin:0 0 8px; }
    .monitoring-cases-head .monitoring-cases-title { margin:0; }
    .tracking-scroll { max-height:17rem; overflow-y:auto; padding-right:8px; }
    /* Same pill pattern as the coordinator's UPCC attendance/outcome choices. */
    .attendance-options { display: grid; grid-template-columns: 1fr; gap: 12px; }
    .attendance-option {
        display: flex; align-items: flex-start; gap: 10px; padding: 13px 15px;
        border: 2px solid var(--border); border-radius: var(--radius-sm);
        background: #fff; cursor: pointer; transition: border-color .15s, background-color .15s, box-shadow .15s;
    }
    .attendance-option:hover:not(.is-disabled) { border-color: #a9bde8; background: #fbfcfe; }
    .attendance-option.is-checked, .attendance-option:has(input:checked) {
        border-color: var(--blue); background: #f4f7fe; box-shadow: 0 0 0 3px rgba(49, 86, 199, .09);
    }
    .attendance-option.is-disabled { opacity: .55; cursor: not-allowed; }
    .attendance-option input { margin-top: 3px; flex-shrink: 0; }
    .attendance-option-title { display: flex; align-items: center; gap: 7px; font-weight: 700; font-size: .875rem; color: #1e293b; }
    .attendance-option-title i { color: #3156c7; font-size: .95rem; }
    .attendance-option-hint { display: block; margin-top: 3px; font-size: .78rem; line-height: 1.5; color: var(--text-secondary); }
    @media (min-width: 480px) {
        .attendance-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 575.98px) { .sanction-overview-grid, .tracking-form-grid { grid-template-columns: 1fr; row-gap: 16px; } .sanction-overview-item:nth-child(odd) { padding-left: 0; border-left: 0; } .tracking-record { grid-template-columns:1fr; gap:5px; } }
    /* Same "Next Action" box as the case page, and same fix: this reacts to its
       own container's width via a container query, not the viewport - a
       viewport media query here would be exactly as wrong as it was there,
       since this card's actual width depends on which layout column it lands
       in, not on the page's width. */
    .sanction-detail-col { container-type: inline-size; }
    .resolution-next-action {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
        padding: 13px 14px;
        border: 1px solid #cbd9f5;
        border-radius: 8px;
        background: #f5f8ff;
    }
    .resolution-next-action-title { margin-bottom: 2px; color: #1e3a8a; font-size: .8rem; font-weight: 700; }
    .resolution-next-action-copy { color: #52627a; font-size: .76rem; line-height: 1.5; }
    .resolution-next-action .prism-btn { align-self: flex-end; white-space: nowrap; }
    @container (min-width: 520px) {
        .resolution-next-action {
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }
        .resolution-next-action .prism-btn { align-self: auto; }
    }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        {{-- The case is already one click away via the Related Case card in the
             sidebar - inserting it here too implied the officer had passed through
             the case to get here, which usually isn't true: sanctions are reached
             from the flat Sanctions list, not nested under a case in this nav. --}}
        <x-breadcrumbs :items="[
            ['label' => 'Sanctions', 'url' => route('officer.sanctions.index')],
            ['label' => $typeLabel],
        ]" />
        <h1 class="prism-page-title">Sanction Details</h1>
        <p class="prism-page-sub">
            {{ $typeLabel }}
            &middot; {{ $sanction->created_at->format('F d, Y') }}
        </p>
    </div>

    {{-- session('success')/'error'/'info' surface as a toast via layouts.staff's
         partials.flash include. A rejected [data-ajax-toast-form] submit (see
         live-ux.js) never navigates at all, so it never reaches this render --
         the toast for that comes from PrismToast.show() instead. The plain-HTML
         fallback (fetch itself failing) still lands here with session('error') set,
         so that path gets the same toast too, just via a page reload. --}}

    <div class="row g-4">
        <div class="col-lg-8 sanction-detail-col">

            @php
                $overviewType = $typeLabel;
                $overviewSummary = match ($sanction->sanction_type) {
                    'non_readmission' => 'The student may complete the current term but is not permitted to enroll for the following term.',
                    'university_service' => 'The student has been assigned university service as part of the disciplinary intervention.',
                    'counseling_referral' => 'The student has been referred to the Guidance Services Office for counseling coordination.',
                    default => 'This sanction was issued as part of the student’s disciplinary intervention.',
                };
                $overviewStatusMap = [
                    'pending' => ['Pending', 'orange'],
                    'in_progress' => ['In Progress', 'blue'],
                    'completed' => ['Completed', 'green'],
                    'non_compliant' => ['Non-Compliant', 'red'],
                    'waived' => ['Waived', 'muted'],
                ];
                [$overviewStatusLabel, $overviewStatusClass] = $overviewStatusMap[$sanction->status] ?? [ucfirst($sanction->status), 'muted'];
                if (in_array($sanction->status, ['pending', 'in_progress'], true) && $sanction->isOverdue()) {
                    [$overviewStatusLabel, $overviewStatusClass] = ['Overdue', 'red'];
                }
            @endphp
            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <span class="prism-card-title">Decision Overview</span>
                </div>
                <div class="prism-card-body">
                    <p class="sanction-overview-copy">{{ $overviewSummary }}</p>
                    <div class="sanction-overview-grid">
                        <div class="sanction-overview-item"><span class="sanction-overview-label">Decision</span><span class="sanction-overview-value">{{ $overviewType }}</span></div>
                        <div class="sanction-overview-item"><span class="sanction-overview-label">Issued</span><span class="sanction-overview-value">{{ $sanction->created_at->format('F d, Y') }}</span></div>
                        @if($sanction->due_date)
                            <div class="sanction-overview-item"><span class="sanction-overview-label">Due date</span><span class="sanction-overview-value">{{ $sanction->due_date->format('F d, Y') }}</span></div>
                        @endif
                        <div class="sanction-overview-item"><span class="sanction-overview-label">Current status</span><span class="sanction-overview-value"><span class="prism-badge prism-badge-{{ $overviewStatusClass }}">{{ $overviewStatusLabel }}</span></span></div>
                    </div>
                </div>
            </div>

            @if($sanction->sanction_type === 'university_service' && ! $sanction->usAssignment)
                @php
                    $usResolution = $sanction->resolution;
                    $usCase = $usResolution?->case;
                    $usNfiIssued = $usCase?->notices
                        ->where('notice_type', 'notice_of_formative_intervention')
                        ->where('is_voided', false)
                        ->isNotEmpty() ?? false;
                @endphp
                @if($usResolution?->us_hours_assigned && $usNfiIssued)
                    <div class="resolution-next-action mb-4">
                        <div>
                            <div class="resolution-next-action-title">Next Action: Create The University Service Assignment</div>
                            <div class="resolution-next-action-copy">
                                Assign {{ \App\Models\UsAssignment::formatDuration($usResolution->us_hours_assigned) }} of service to
                                generate the student’s QR code and begin tracking.
                            </div>
                        </div>
                        <a href="{{ route('officer.us.create', [$usResolution, 'sanction' => $sanction->id]) }}" class="prism-btn prism-btn-primary prism-btn-sm">
                            Create Assignment
                        </a>
                    </div>
                @elseif(! $usNfiIssued)
                    <div class="prism-note mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        The Notice of Formative Intervention must be issued on the related case before this assignment can be created.
                    </div>
                @endif
            @endif

            @if($sanction->sanction_type === 'university_service' && $sanction->usAssignment)
                @php $us = $sanction->usAssignment; @endphp
                <div class="prism-card mb-4" id="usAssignmentCard">
                    <div class="prism-card-header prism-card-header--blue d-flex align-items-center justify-content-between">
                        <span class="prism-card-title" style="color:#FFFFFF;">University Service Assignment</span>
                        <a href="{{ route('officer.us.show', $us) }}" class="prism-btn prism-btn-blue-outline prism-btn-sm"
                            style="min-height:32px;padding:6px 14px;">
                            Open Workspace
                        </a>
                    </div>
                    <div class="prism-card-body">
                        @php
                            $pct = $us->completionPercentage();
                            $barColor = match($us->status) {
                                'completed'     => 'var(--green, #16a34a)',
                                'non_compliant' => '#dc2626',
                                default         => 'var(--blue)',
                            };
                            $usStatusMap = [
                                'pending'       => ['Pending',       'prism-badge-orange'],
                                'in_progress'   => ['In Progress',   'prism-badge-blue'],
                                'completed'     => ['Completed',     'prism-badge-green'],
                                'non_compliant' => ['Non-Compliant', 'prism-badge-red'],
                            ];
                            [$usLabel, $usBadge] = $usStatusMap[$us->status] ?? [ucfirst($us->status), 'prism-badge-gray'];
                            $isUsOverdue = in_array($us->status, ['pending', 'in_progress'], true) && $us->isOverdue();
                            if ($isUsOverdue) {
                                [$usLabel, $usBadge] = ['Overdue', 'prism-badge-red'];
                            }
                        @endphp

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:0.82rem;">
                                <span class="text-muted">Hours Progress</span>
                                <span style="font-weight:600;">{{ $us->renderedDuration() }} of {{ $us->requiredDuration() }}</span>
                            </div>
                            <div style="background:var(--border);border-radius:99px;height:8px;">
                                <div style="height:8px;border-radius:99px;width:{{ $pct }}%;background:{{ $barColor }};transition:width .3s;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1" style="font-size:0.78rem;color:var(--text-muted);">
                                <span>{{ $pct }}% complete</span>
                                <span>{{ $us->remainingDuration() }} remaining</span>
                            </div>
                        </div>

                        <dl class="row g-2 mb-0" style="font-size:0.88rem;">
                            <dt class="col-sm-4 text-muted">Status</dt>
                            <dd class="col-sm-8">
                                <span class="prism-badge {{ $usBadge }}">{{ $usLabel }}</span>
                            </dd>

                            <dt class="col-sm-4 text-muted">Office</dt>
                            <dd class="col-sm-8">{{ $us->office_name ?? '—' }}</dd>

                            <dt class="col-sm-4 text-muted">Supervising Contact</dt>
                            <dd class="col-sm-8">{{ $us->supervisor_display_name }}</dd>

                            <dt class="col-sm-4 text-muted">Start Date</dt>
                            <dd class="col-sm-8">{{ $us->start_date?->format('M d, Y') ?? '—' }}</dd>

                            <dt class="col-sm-4 text-muted">Target Completion</dt>
                            <dd class="col-sm-8">{{ $us->target_completion_date?->format('M d, Y') ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            @endif

            @if($sanction->sanction_type === 'probation')
                @php
                    $probationCaseCount = $monitoringCases->count();
                    $probationSlipCount = $probationSlips->count();
                    $probationBreaches = $probationCaseCount + $probationSlipCount;
                    $probationClosed = in_array($sanction->status, ['completed', 'waived'], true);
                    $probationScrolls = $probationBreaches > 4;
                @endphp
                <div class="prism-card mb-4">
                    <div class="prism-card-header prism-card-header--blue"><span class="prism-card-title" style="color:#fff;">Probation Period</span></div>
                    <div class="prism-card-body">
                        @if($sanction->monitoring_review_flagged_at)
                            <div class="prism-alert prism-alert-warning mb-3">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div>
                                    The probation period ended with
                                    <strong>{{ $probationBreaches }} {{ Str::plural('infringement', $probationBreaches) }}</strong>
                                    on this student&rsquo;s record, listed below. Section VI.E requires a
                                    decision between <strong>suspension</strong> and <strong>non-readmission</strong>
                                    for the next term. Record it on the case.
                                </div>
                            </div>
                        @elseif($probationBreaches > 0 && ! $probationClosed)
                            <div class="prism-alert prism-alert-warning mb-3">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <div>
                                    <strong>{{ $probationBreaches }} {{ Str::plural('infringement', $probationBreaches) }}</strong>
                                    {{ $probationBreaches === 1 ? 'was' : 'were' }} recorded while this period is running.
                                    It will be held for your decision instead of closing on its own.
                                </div>
                            </div>
                        @endif

                        <div class="monitoring-period mb-3">
                            Probation runs from {{ $sanction->created_at->format('M d, Y') }}
                            @if($sanction->due_date)
                                to {{ $sanction->due_date->format('M d, Y') }}, covering
                                {{ $sanction->resolution?->probation_terms ?? 3 }} academic
                                {{ Str::plural('term', $sanction->resolution?->probation_terms ?? 3) }}.
                            @endif
                            Any further major offense or violation slip during the period is a breach
                            under Section VI.E.
                        </div>

                        <div class="monitoring-cases-head">
                            <h3 class="monitoring-cases-title">Infringements during the Probation Period</h3>
                            @if($probationBreaches > 0)
                                <span class="prism-badge {{ $probationClosed ? 'prism-badge-gray' : 'prism-badge-orange' }}">{{ $probationBreaches }}</span>
                            @endif
                        </div>
                        <div class="{{ $probationScrolls ? 'tracking-scroll' : '' }}">
                            @forelse($monitoringCases as $probationCase)
                                <div class="tracking-record">
                                    <div><div style="font-size:.8rem;font-weight:700;">{{ $probationCase->created_at->format('M d, Y') }}</div><div style="color:var(--text-muted);font-size:.7rem;">Case opened</div></div>
                                    <div><a class="prism-ref-link prism-ref-number" href="{{ route('officer.cases.show', $probationCase) }}">{{ $probationCase->case_number }}</a><div style="margin-top:3px;color:var(--text-muted);font-size:.72rem;">{{ $probationCase->confirmedOffense?->title ?? 'Offense pending confirmation' }}</div></div>
                                    <span class="prism-badge prism-badge-{{ $probationCase->status === 'resolved' ? 'green' : 'blue' }}">{{ ucwords(str_replace('_', ' ', $probationCase->status)) }}</span>
                                </div>
                            @empty
                            @endforelse

                            @foreach($probationSlips as $slip)
                                <div class="tracking-record">
                                    <div><div style="font-size:.8rem;font-weight:700;">{{ $slip->created_at->format('M d, Y') }}</div><div style="color:var(--text-muted);font-size:.7rem;">Violation slip</div></div>
                                    <div><span class="prism-ref-number">{{ $slip->report_number }}</span><div style="margin-top:3px;color:var(--text-muted);font-size:.72rem;">{{ Str::limit($slip->description, 80) }}</div></div>
                                    <span class="prism-badge prism-badge-gray">Minor</span>
                                </div>
                            @endforeach

                            @if($probationBreaches === 0)
                                <div class="prism-table-empty" style="padding:20px 8px;">No further infringements were recorded during this probation period.</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if($sanction->sanction_type === 'counseling_referral')
                @php $ref = $sanction->counselingReferral; @endphp
                <div class="prism-card mb-4" id="counselingReferralCard">
                    <div class="prism-card-header prism-card-header--blue">
                        <span class="prism-card-title" style="color:#fff;">Counseling Referral</span>
                    </div>
                    <div class="prism-card-body">
                        @if(! $ref)
                            <div class="prism-alert prism-alert-danger mb-0">
                                <i class="bi bi-exclamation-octagon-fill"></i>
                                <div>
                                    This sanction is a counseling referral, but its referral record is missing.
                                    The Guidance Office cannot pick it up in this state &mdash; report it to a system
                                    administrator so the record can be restored.
                                </div>
                            </div>
                        @else
                            @php
                                $refStatusMap = [
                                    'pending'    => ['Awaiting Schedule',    'prism-badge-orange'],
                                    'completed'  => ['Counseling Concluded', 'prism-badge-green'],
                                    'incomplete' => ['Returned Incomplete',  'prism-badge-red'],
                                ];
                                [$refStatusLabel, $refStatusBadge] = $refStatusMap[$ref->status]
                                    ?? [ucwords(str_replace('_', ' ', (string) $ref->status)), 'prism-badge-gray'];
                                $refUrgency = \App\Models\CounselingReferral::URGENCY_LEVELS[$ref->urgency] ?? null;
                                $refStart = $ref->referred_at ?? $sanction->created_at;
                                $daysSinceReferred = $refStart ? (int) $refStart->copy()->startOfDay()->diffInDays(now()->startOfDay()) : null;
                                // Calendar-day approximation of the urgency guidance in CounselingReferral::URGENCY_LEVELS.
                                $urgencyWindow = ['immediate' => 3, 'priority' => 14][$ref->urgency] ?? null;
                                $awaitingSchedule = $ref->status === 'pending';
                                $scheduleOverdue = $awaitingSchedule
                                    && $urgencyWindow !== null
                                    && $daysSinceReferred !== null
                                    && $daysSinceReferred > $urgencyWindow;
                            @endphp

                            @if($ref->status === 'incomplete')
                                @php
                                    $canReRefer = ! app(\App\Services\GuidanceReferralService::class)
                                        ->hasPendingReferral($sanction->case_resolution_id);
                                    $successorSanction = $sanction->successorReferralSanction();
                                @endphp
                                <div class="prism-alert prism-alert-warning mb-3">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <div>
                                        The Guidance Office returned this referral as <strong>incomplete</strong>,
                                        so the sanction is now Non-Compliant and has been logged against the
                                        student's risk profile.
                                        @if($ref->incomplete_reason)
                                            {{ Str::limit($ref->incomplete_reason, 140) }}
                                        @endif
                                    </div>
                                </div>
                                @if($activeRegistrationHold)
                                    <div class="prism-card mb-3" style="border:1px solid #dc2626;">
                                        <div class="prism-card-body">
                                            <div class="d-flex align-items-start gap-3">
                                                <i class="bi bi-exclamation-triangle-fill"
                                                   style="color:#dc2626;font-size:1.3rem;flex-shrink:0;"></i>
                                                <div class="flex-grow-1">
                                                    <p style="font-weight:700;margin-bottom:4px;">
                                                        Registration Hold Active
                                                    </p>
                                                    <p style="font-size:0.84rem;margin-bottom:0;">
                                                        {{ $activeRegistrationHold->flag_reason }}
                                                        Coordinate with the Registrar; see the Registration Hold report to lift it.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                @if($successorSanction)
                                    <div class="prism-alert prism-alert-info mb-3">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <div>
                                            This case was re-referred to Guidance on
                                            {{ $successorSanction->created_at->format('F d, Y') }}.
                                            This page still shows the earlier, closed referral.
                                            <a href="{{ route('officer.sanctions.show', $successorSanction) }}">View the new referral &rarr;</a>
                                        </div>
                                    </div>
                                @endif
                            @elseif($scheduleOverdue)
                                <div class="prism-alert prism-alert-warning mb-3">
                                    <i class="bi bi-exclamation-circle-fill"></i>
                                    <div>
                                        This <strong>{{ $refUrgency['label'] ?? $ref->urgency }}</strong> referral has been
                                        awaiting scheduling for <strong>{{ $daysSinceReferred }} {{ Str::plural('day', $daysSinceReferred) }}</strong>,
                                        past the {{ $urgencyWindow }}-day guidance. Check in with the Guidance Office if it stays unscheduled.
                                    </div>
                                </div>
                            @else
                                <div class="monitoring-period mb-3">
                                    The Guidance Office schedules and runs the counseling sessions. No action is
                                    needed from you unless the referral is returned as incomplete &mdash; concluding
                                    it there marks this sanction complete automatically.
                                </div>
                            @endif

                            <dl class="row g-2 mb-0" style="font-size:0.88rem;">
                                <dt class="col-sm-4 text-muted">Referral Status</dt>
                                <dd class="col-sm-8">
                                    <span class="prism-badge {{ $refStatusBadge }}">{{ $refStatusLabel }}</span>
                                    @if($awaitingSchedule && $daysSinceReferred !== null)
                                        <div style="margin-top:4px;margin-left:10px;color:var(--text-muted);font-size:.78rem;">Referred {{ $daysSinceReferred === 0 ? 'today' : $daysSinceReferred.' '.Str::plural('day', $daysSinceReferred).' ago' }}</div>
                                    @endif
                                </dd>

                                @if($refUrgency)
                                    <dt class="col-sm-4 text-muted">Urgency</dt>
                                    <dd class="col-sm-8 d-flex align-items-center flex-wrap" style="gap:8px;">
                                        <span class="prism-badge {{ $refUrgency['badge'] }}">{{ $refUrgency['label'] }}</span>
                                        <span style="color:var(--text-muted);font-size:.78rem;">{{ $refUrgency['turnaround'] }}</span>
                                    </dd>
                                @endif

                                <dt class="col-sm-4 text-muted">Guidance Officer</dt>
                                <dd class="col-sm-8">
                                    @if($ref->guidanceOfficer)
                                        {{ $ref->guidanceOfficer->list_name }}
                                    @else
                                        <span style="display:inline-block;margin-left:10px;color:#dc2626;">No active Guidance Officer covers this student's department yet.</span>
                                    @endif
                                </dd>

                                @if($ref->acknowledged_at)
                                    <dt class="col-sm-4 text-muted">Acknowledged</dt>
                                    <dd class="col-sm-8">{{ $ref->acknowledged_at->format('F d, Y') }}</dd>
                                @endif

                                @if($ref->status === 'completed' && $ref->completed_at)
                                    <dt class="col-sm-4 text-muted">Concluded</dt>
                                    <dd class="col-sm-8">{{ $ref->completed_at->format('F d, Y') }}</dd>
                                @endif
                            </dl>

                            @if($ref->referral_reason)
                                <div style="margin-top:14px;">
                                    <span style="display:block;margin-bottom:6px;color:#6c757d;font-size:.88rem;font-weight:700;">Reason for Referral</span>
                                    <div style="padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;color:#334155;font-size:.85rem;line-height:1.7;white-space:pre-wrap;overflow-wrap:anywhere;word-break:break-word;max-height:240px;overflow-y:auto;">{{ $ref->referral_reason }}</div>
                                </div>
                            @endif

                            @if($ref->status === 'incomplete' && $canReRefer)
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="button" class="prism-btn prism-btn-primary prism-btn-sm" data-bs-toggle="modal" data-bs-target="#reReferModal">
                                        Re-refer to Guidance
                                    </button>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                @if($ref && $ref->status === 'incomplete' && $canReRefer)
                    <div class="modal fade prism-modal" id="reReferModal" tabindex="-1" aria-labelledby="reReferModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h2 class="modal-title" id="reReferModalLabel">Re-refer to Guidance</h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form method="POST" action="{{ route('officer.sanctions.counseling-referral.re-refer', $sanction) }}">
                                    @csrf
                                    <div class="modal-body">
                                        <p class="mb-3" style="color:#64748b;font-size:.8rem;line-height:1.55;">
                                            This opens a new, pending counseling referral for the Guidance Office to schedule,
                                            giving the student another chance at the requirement.
                                        </p>
                                        <label class="prism-label" for="reReferReason">Reason <span class="text-danger">*</span></label>
                                        <textarea id="reReferReason" name="reason" class="prism-input" rows="4" required minlength="20" maxlength="1000" placeholder="State why this student is being re-referred."></textarea>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="prism-btn prism-btn-primary">Re-refer</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            @if($sanction->sanction_type === 'discipline_education')
                @php $scheduledAttendance = $sanction->disciplineEducationAttendances->firstWhere('status', 'scheduled'); @endphp
                <div class="prism-card mb-4">
                    <div class="prism-card-header prism-card-header--blue"><span class="prism-card-title" style="color:#fff;">Attendance Monitoring</span></div>
                    <div class="prism-card-body">
                        <div class="monitoring-period mb-3">
                            @if($scheduledAttendance)
                                Scheduled for <strong>{{ $scheduledAttendance->session_at->format('F d, Y \a\t g:i A') }}</strong>. The sanction remains Pending until the session begins. Record Present or Absent afterward; if no attendance is recorded by the end of the scheduled day, it is marked Non-Compliant automatically.
                            @elseif($sanction->status === 'non_compliant')
                                The student did not attend the last scheduled session. Schedule a new session below to give the student another chance, or leave it as Non-Compliant.
                            @else
                                No pending Discipline Education attendance is available. Program dates are set when the intervention is selected during case resolution.
                            @endif
                        </div>
                        @if(! $scheduledAttendance && $sanction->status === 'non_compliant')
                            <form method="POST" action="{{ route('officer.sanctions.discipline-education.reschedule', $sanction) }}" class="mb-4 pb-4 border-bottom">
                                @csrf
                                <div class="tracking-form-grid mb-3">
                                    <div>
                                        <label class="prism-label" for="reschedule_session_at">New Session Date &amp; Time <span class="text-danger">*</span></label>
                                        <input type="datetime-local" id="reschedule_session_at" name="session_at" class="prism-input" required
                                               min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}">
                                    </div>
                                </div>
                                <label class="prism-label" for="reschedule_reason">Reason for Rescheduling <span class="text-danger">*</span></label>
                                <textarea id="reschedule_reason" name="reschedule_reason" class="prism-input" rows="3"
                                          minlength="20" maxlength="1000"
                                          placeholder="Why is this student getting another session — e.g. an excused absence, a scheduling conflict on the office's end."
                                          required></textarea>
                                <div class="d-flex justify-content-end mt-3">
                                    <button class="prism-btn prism-btn-primary" style="min-width:160px;" type="submit">Reschedule</button>
                                </div>
                            </form>
                        @endif
                        @if($scheduledAttendance)
                            @php $attendanceNotYetDue = $scheduledAttendance->session_at->isFuture(); @endphp
                            <form method="POST" action="{{ route('officer.sanctions.discipline-education.attendance', $sanction) }}" class="mb-4 pb-4 border-bottom">
                                @csrf
                                <label class="prism-label">Attendance result</label>
                                <div class="attendance-options">
                                    @foreach([
                                        ['present', 'Present', 'bi-person-check', 'Completes the sanction.'],
                                        ['absent', 'Absent', 'bi-person-x', 'Marks it non-compliant.'],
                                    ] as [$value, $label, $icon, $hint])
                                        @php $checked = old('attendance_status') === $value; @endphp
                                        <label class="attendance-option {{ $checked ? 'is-checked' : '' }} {{ $attendanceNotYetDue ? 'is-disabled' : '' }}">
                                            <input type="radio" name="attendance_status" value="{{ $value }}"
                                                   {{ $checked ? 'checked' : '' }} required @disabled($attendanceNotYetDue)>
                                            <span class="attendance-option-body">
                                                <span class="attendance-option-title"><i class="bi {{ $icon }}"></i>{{ $label }}</span>
                                                <span class="attendance-option-hint">{{ $hint }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="d-flex justify-content-end mt-3">
                                    <button class="prism-btn prism-btn-primary" style="min-width:160px;" type="submit" {{ $attendanceNotYetDue ? 'disabled' : '' }}>Record</button>
                                </div>
                            </form>
                        @endif

                        @forelse($sanction->disciplineEducationAttendances as $attendance)
                            @php $attendanceBadge = ['scheduled' => 'prism-badge-blue', 'present' => 'prism-badge-green', 'absent' => 'prism-badge-red'][$attendance->status] ?? 'prism-badge-gray'; @endphp
                            <div class="tracking-record">
                                <div><div style="font-size:.8rem;font-weight:700;">{{ $attendance->session_at->format('M d, Y') }}</div><div style="color:var(--text-muted);font-size:.7rem;">{{ $attendance->session_at->format('g:i A') }}</div></div>
                                <div>
                                    <div style="color:var(--text-muted);font-size:.78rem;font-weight:400;">{{ $attendance->status === 'scheduled' ? 'Awaiting attendance' : 'Attendance recorded' }}</div>
                                    <div style="margin-top:3px;color:var(--text-muted);font-size:.68rem;">
                                        @if($attendance->status !== 'scheduled')
                                            Recorded by {{ $attendance->recordedBy?->list_name ?? 'Discipline Officer' }}
                                        @elseif($attendance->notes)
                                            Rescheduled by {{ $attendance->recordedBy?->list_name ?? 'Discipline Officer' }}
                                        @else
                                            Scheduled during case resolution
                                        @endif
                                    </div>
                                </div>
                                <span class="prism-badge {{ $attendanceBadge }}">{{ ucfirst($attendance->status) }}</span>
                            </div>
                        @empty
                            <div class="prism-table-empty" style="padding:20px 8px;">No Discipline Education sessions have been recorded.</div>
                        @endforelse
                    </div>
                </div>
            @endif

            @if($sanction->sanction_type === 'evaluation_monitoring')
                <div class="prism-card mb-4">
                    <div class="prism-card-header prism-card-header--blue"><span class="prism-card-title" style="color:#fff;">Evaluation &amp; Monitoring</span></div>
                    <div class="prism-card-body">
                        @php
                            $monitoringCaseCount = $monitoringCases->count();
                            // "Decided" means the officer has already closed the period, so the
                            // in-period notice would be describing a window that is over.
                            $monitoringPeriodDecided = in_array($sanction->status, ['completed', 'waived'], true);
                            $monitoringCaseScrolls = $monitoringCaseCount > 4;
                            // The period manages itself (auto-closes clean, flags itself when a
                            // case needs a human judgement) - the manual form is a fallback for
                            // overriding that, not the default action, so it only opens itself
                            // when there's actually something to review.
                            $monitoringNeedsReview = (bool) $sanction->monitoring_review_flagged_at
                                || ($monitoringCaseCount > 0 && ! $monitoringPeriodDecided);
                        @endphp
                        @if($sanction->monitoring_review_flagged_at)
                            <div class="prism-alert prism-alert-warning mb-3">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <div>New cases on this student's record are holding the period open. Record your assessment below to close it.</div>
                            </div>
                        @elseif($monitoringCaseCount > 0 && ! $monitoringPeriodDecided)
                            {{-- The period only reports itself on the end date, so without this a case
                                 opened mid-period sat unremarked in the list below until then. --}}
                            <div class="prism-alert prism-alert-warning mb-3">
                                <i class="bi bi-exclamation-circle-fill"></i>
                                <div>
                                    <strong>{{ $monitoringCaseCount }} new {{ Str::plural('case', $monitoringCaseCount) }}</strong>
                                    {{ $monitoringCaseCount === 1 ? 'was' : 'were' }} opened this period &mdash; it won't auto-close. Extend the end date or record your assessment below.
                                </div>
                            </div>
                        @endif

                        <div class="monitoring-period mb-3">
                            @if($sanction->due_date)
                                Runs {{ $sanction->created_at->format('M d, Y') }} to {{ $sanction->due_date->format('M d, Y') }}.
                                Auto-closes as Satisfactory unless a new case appears &mdash; then it waits for you to close it with your own assessment instead.
                            @else
                                Runs from {{ $sanction->created_at->format('M d, Y') }} to the end date below.
                            @endif
                        </div>
                        <details class="case-supporting-disclosure mb-4" @if($monitoringNeedsReview) open @endif>
                            <summary>
                                <span>{{ $monitoringNeedsReview ? 'Record your assessment' : 'Adjust Period' }}</span>
                                <span class="case-supporting-toggle" aria-hidden="true">
                                    <span class="case-supporting-show">Show</span>
                                    <span class="case-supporting-hide">Hide</span>
                                    <i class="bi bi-chevron-down"></i>
                                </span>
                            </summary>
                            <form method="POST" action="{{ route('officer.sanctions.evaluation-monitoring.update', $sanction) }}" class="case-supporting-content" data-ajax-toast-form>
                                @csrf @method('PATCH')
                                {{-- The period runs and closes on its own; this form extends it
                                     or overrides the outcome. --}}
                                <div class="tracking-form-grid">
                                    <div><label class="prism-label" for="due_date">Monitoring End Date <span class="text-danger">*</span></label><input class="prism-input w-100" id="due_date" name="due_date" type="date" min="{{ $sanction->created_at->toDateString() }}" value="{{ old('due_date', $sanction->due_date?->toDateString()) }}" required></div>
                                    <div><label class="prism-label" for="monitoring_status">Status <span class="text-danger">*</span></label><select class="prism-select w-100" id="monitoring_status" name="status" required><option value="in_progress" {{ old('status', $sanction->status) === 'in_progress' ? 'selected' : '' }}>In Progress</option><option value="completed" {{ old('status', $sanction->status) === 'completed' ? 'selected' : '' }}>Completed</option></select></div>
                                </div>
                                {{-- Required only on completion: the finding is what closes the period.
                                     Pre-selected toward Needs Further Intervention when a case is why this
                                     needs review at all - still the officer's own call to submit or change,
                                     just without an empty dropdown for the obvious case. --}}
                                <div>
                                    <label class="prism-label" for="assessment_outcome">Assessment <span class="text-danger">*</span></label>
                                    <select class="prism-select w-100" id="assessment_outcome" name="assessment_outcome">
                                        <option value="">Select assessment</option>
                                        @foreach(\App\Models\Sanction::ASSESSMENT_OUTCOMES as $value => $label)
                                            @php
                                                $defaultOutcome = $monitoringNeedsReview ? 'needs_further_intervention' : null;
                                            @endphp
                                            <option value="{{ $value }}" {{ old('assessment_outcome', $sanction->assessment_outcome ?? $defaultOutcome) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <p class="prism-hint mb-0">Required when closing the period. &ldquo;Needs Further Intervention&rdquo; records the finding for the student&rsquo;s record and raises a counseling referral.</p>
                                </div>
                                <div><label class="prism-label" for="monitoring_notes">Officer Notes <span class="prism-label-opt">(optional)</span></label><textarea class="prism-input w-100" id="monitoring_notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $sanction->notes) }}</textarea></div>
                                <div class="monitoring-actions" style="margin:0;"><button class="prism-btn prism-btn-primary" type="submit">Update Period</button></div>
                            </form>
                        </details>

                        <div class="monitoring-cases-head">
                            <h3 class="monitoring-cases-title">Cases during the Intervention Period</h3>
                            @if($monitoringCaseCount > 0)
                                <span class="prism-badge {{ $monitoringPeriodDecided ? 'prism-badge-gray' : 'prism-badge-orange' }}">{{ $monitoringCaseCount }}</span>
                            @endif
                        </div>
                        {{-- Past the visible limit the list scrolls in place, so a student with a long
                             record cannot push the rest of the page out of reach. --}}
                        <div class="{{ $monitoringCaseScrolls ? 'tracking-scroll' : '' }}">
                            @forelse($monitoringCases as $case)
                                <div class="tracking-record">
                                    <div><div style="font-size:.8rem;font-weight:700;">{{ $case->created_at->format('M d, Y') }}</div><div style="color:var(--text-muted);font-size:.7rem;">Opened</div></div>
                                    <div><a class="prism-ref-link prism-ref-number" href="{{ route('officer.cases.show', $case) }}">{{ $case->case_number }}</a><div style="margin-top:3px;color:var(--text-muted);font-size:.72rem;">{{ $case->confirmedOffense?->title ?? 'Offense pending confirmation' }}</div></div>
                                    <span class="prism-badge prism-badge-{{ $case->status === 'resolved' ? 'green' : 'blue' }}">{{ ucwords(str_replace('_', ' ', $case->status)) }}</span>
                                </div>
                            @empty
                                <div class="prism-table-empty" style="padding:20px 8px;">No additional cases were opened during this monitoring period.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            <div class="prism-card mb-4 sanction-fill-card">
                <div class="prism-card-header"><span class="prism-card-title">Activity</span></div>
                <div class="prism-card-body">
                    <div class="sanction-timeline">
                        <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Sanction issued</div><div class="sanction-timeline-date">{{ $sanction->created_at->format('F d, Y \a\t g:i A') }}</div></div></div>
                        @if($sanction->sanction_type === 'counseling_referral' && $sanction->counselingReferral)
                            @php $timelineRef = $sanction->counselingReferral; @endphp
                            @if($timelineRef->acknowledged_at)
                                <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Acknowledged by Guidance Office</div><div class="sanction-timeline-date">{{ $timelineRef->acknowledged_at->format('F d, Y \a\t g:i A') }}</div></div></div>
                            @endif
                            @if($timelineRef->status === 'completed' && $timelineRef->completed_at)
                                <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Counseling concluded</div><div class="sanction-timeline-date">{{ $timelineRef->completed_at->format('F d, Y \a\t g:i A') }}</div></div></div>
                            @elseif($timelineRef->status === 'incomplete')
                                <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Returned incomplete by Guidance Office</div><div class="sanction-timeline-date">{{ $timelineRef->updated_at->format('F d, Y \a\t g:i A') }}</div></div></div>
                            @endif
                        @elseif($sanction->completed_at)
                            <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Sanction completed</div><div class="sanction-timeline-date">{{ $sanction->completed_at->format('F d, Y \a\t g:i A') }}</div></div></div>
                        @elseif($sanction->due_date)
                            <div class="sanction-timeline-item"><span class="sanction-timeline-dot"></span><div><div class="sanction-timeline-title">Due date</div><div class="sanction-timeline-date">{{ $sanction->due_date->format('F d, Y') }}</div></div></div>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        <div class="col-lg-4 sanction-detail-col">

            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <span class="prism-card-title">Student</span>
                </div>
                <div class="prism-card-body">
                    <dl class="row g-2 mb-0" style="font-size:0.88rem;">
                        <dt class="col-sm-5 text-muted">Name</dt>
                        <dd class="col-sm-7">{{ $sanction->student?->full_name ?? '—' }}</dd>

                        @if($sanction->student?->studentProfile)
                            <dt class="col-sm-5 text-muted">Student No.</dt>
                            <dd class="col-sm-7 prism-ref-number">{{ $sanction->student->studentProfile->student_number ?? '—' }}</dd>

                            <dt class="col-sm-5 text-muted">Program</dt>
                            <dd class="col-sm-7">{{ $sanction->student->studentProfile->program ?? '—' }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            @php
                // The university_service "notes" field is never a real verification
                // note - CaseService sets it at creation to a boilerplate restatement
                // of the required hours (e.g. "16 hours required."), which is exactly
                // what the Next Action box (before an assignment exists) and the
                // Hours Progress bar (after) already show. Showing it here too, in a
                // card literally titled Completion & Verification, read as if that
                // boilerplate were a real note about how the sanction was completed.
                $showSanctionNotes = $sanction->notes && $sanction->sanction_type !== 'university_service';
            @endphp
            @if($sanction->completed_at || $sanction->verifiedBy || $showSanctionNotes)
                <div class="prism-card mb-4" id="sanctionInfoCard">
                    <div class="prism-card-header">
                        <span class="prism-card-title">Completion &amp; Verification</span>
                    </div>
                    <div class="prism-card-body">
                        <dl class="row g-2 mb-0" style="font-size:0.88rem;">
                            @if($sanction->completed_at)
                                <dt class="col-sm-5 text-muted">Completed At</dt>
                                <dd class="col-sm-7">{{ $sanction->completed_at->format('F d, Y \a\t g:i A') }}</dd>
                            @endif

                            @if($sanction->verifiedBy)
                                <dt class="col-sm-5 text-muted">{{ $sanction->sanction_type === 'counseling_referral' ? 'Concluded By' : 'Verified By' }}</dt>
                                <dd class="col-sm-7">{{ $sanction->verifiedBy->list_name }}</dd>
                            @endif

                            @if($showSanctionNotes)
                                <dt class="col-sm-5 text-muted">Notes</dt>
                                <dd class="col-sm-7">{{ $sanction->notes }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            @endif

            @if($sanction->resolution?->case)
                @php $case = $sanction->resolution->case; @endphp
                <div class="prism-card mb-4" id="relatedCaseCard">
                    <div class="prism-card-header">
                        <span class="prism-card-title">Related Case</span>
                    </div>
                    <div class="prism-card-body">
                        <dl class="row g-2 mb-0" style="font-size:0.88rem;">
                            <dt class="col-sm-5 text-muted">Case No.</dt>
                            <dd class="col-sm-7">
                                <a href="{{ route('officer.cases.show', $case) }}" class="prism-ref-link prism-ref-number">
                                    {{ $case->case_number }}
                                </a>
                            </dd>

                            <dt class="col-sm-5 text-muted">Status</dt>
                            <dd class="col-sm-7">{{ ucwords(str_replace('_', ' ', $case->status)) }}</dd>
                        </dl>
                    </div>
                </div>
            @endif

            @if($relatedSanctions->isNotEmpty())
                <div class="prism-card mb-4" id="relatedSanctionsCard">
                    <div class="prism-card-header">
                        <span class="prism-card-title">Related Sanctions</span>
                    </div>
                    <div class="prism-card-body">
                        @foreach($relatedSanctions as $related)
                            @php
                                $relatedLabel = $typeLabels[$related->sanction_type]
                                    ?? ucwords(str_replace('_', ' ', $related->sanction_type));
                                $relatedRef = $related->sanction_type === 'counseling_referral' ? $related->counselingReferral : null;
                            @endphp
                            <div style="padding:10px 0;{{ $loop->first ? '' : 'border-top:1px solid var(--border);' }}">
                                <a href="{{ route('officer.sanctions.show', $related) }}" class="prism-ref-link" style="font-size:.85rem;font-weight:600;">{{ $relatedLabel }}</a>
                                <div style="margin-top:3px;color:var(--text-muted);font-size:.75rem;">
                                    Issued {{ $related->created_at->format('M d, Y') }}
                                    @if($relatedRef)
                                        &middot;
                                        @if($relatedRef->guidanceOfficer)
                                            {{ $relatedRef->guidanceOfficer->list_name }}
                                        @else
                                            No Guidance Officer covers this department
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var usCard = document.getElementById('usAssignmentCard');
        var infoCard = document.getElementById('sanctionInfoCard');
        var relatedCard = document.getElementById('relatedCaseCard');
        if (!usCard || !infoCard) return;

        var syncHeight = function () {
            infoCard.style.minHeight = '';
            infoCard.style.maxHeight = '';
            infoCard.style.overflowY = '';
            if (relatedCard) {
                relatedCard.style.minHeight = '';
                relatedCard.style.maxHeight = '';
                relatedCard.style.overflowY = '';
            }
            if (window.innerWidth < 992) return;

            infoCard.style.minHeight = (infoCard.offsetHeight + 4) + 'px';

            if (!relatedCard) return;

            var gap = parseFloat(getComputedStyle(infoCard).marginBottom) || 0;
            var available = usCard.offsetHeight - gap - infoCard.offsetHeight;
            if (relatedCard.offsetHeight > available && available > 0) {
                relatedCard.style.maxHeight = (available + 2) + 'px';
                relatedCard.style.overflowY = 'auto';
            } else {
                relatedCard.style.minHeight = (relatedCard.offsetHeight + 4) + 'px';
            }
        };

        syncHeight();
        window.addEventListener('resize', syncHeight);
    });
</script>
@endsection
