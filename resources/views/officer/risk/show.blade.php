@extends('layouts.staff')
@section('title', 'Student Risk Profile — ' . $student->full_name)
@section('css')
<style>
    /* Same base card design as the coordinator risk profile page
       (coordinator/risk/show): plain prism-card styling from shared.css, no
       bordered/tinted-header override -- only the blue-header swap below is
       officer-specific, layered on top of that shared look. */
    .risk-profile-workspace { display: grid; gap: 16px; overflow-x: hidden; }

    .risk-profile-topbar {
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .risk-profile-grid {
        display: grid;
        grid-template-columns: minmax(280px, 340px) minmax(0, 1fr);
        gap: 16px;
        align-items: start;
    }

    .risk-profile-sidebar,
    .risk-profile-main {
        min-width: 0;
        display: grid;
        gap: 16px;
    }

    .risk-profile-workspace .risk-section-header-blue {
        background: #3156C7;
        border-bottom-color: #3156C7;
    }

    .risk-profile-workspace .risk-section-header-blue .prism-card-title,
    .risk-profile-workspace .risk-section-header-blue p {
        color: #FFFFFF !important;
    }

    .risk-profile-workspace .risk-section-header-blue p {
        opacity: .82;
    }

    /* The support card is last in the DOM so the markup still reads top-to-bottom;
       grid order floats it to the front of the column, desktop and stacked alike. */
    .risk-profile-main > .risk-section-lead {
        order: -1;
    }

    /* Past a handful of entries the list scrolls inside the card rather than
       stretching the page - the record stays complete, the page stays usable. */
    .support-action-scroll {
        max-height: 620px;
        overflow-y: auto;
        padding-right: 4px;
    }

    .risk-guidance {
        display: flex;
        flex-direction: column;
        gap: 12px;
        padding: 14px 15px 15px;
        margin-bottom: 18px;
        border: 1px solid #BFDBFE;
        border-radius: 10px;
        background: #F8FBFF;
        color: #1E3A8A;
        font-size: 0.78rem;
        line-height: 1.5;
    }

    .risk-guidance-heading {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 7px;
        color: #1E40AF;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .risk-guidance-heading i {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: #E8F0FF;
        font-size: .88rem;
    }

    .risk-guidance ul {
        display: grid;
        gap: 8px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .risk-guidance li {
        position: relative;
        padding-left: 15px;
        color: #1F2937;
    }

    .risk-guidance li::before {
        content: '';
        position: absolute;
        top: .52em;
        left: 1px;
        width: 5px;
        height: 5px;
        border-radius: 50%;
        background: #3156C7;
    }

    /* Per-action editor and the closed-action archive: both keep the card list short
       until the officer actually needs the detail. */
    .support-action-editor > summary,
    .support-action-history > summary {
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--primary, #2F4FBF);
        list-style: none;
        user-select: none;
    }
    .support-action-editor > summary::-webkit-details-marker,
    .support-action-history > summary::-webkit-details-marker { display: none; }
    .support-action-editor > summary i,
    .support-action-history > summary i {
        font-size: 0.72rem;
        transition: transform 0.15s ease;
    }
    .support-action-editor[open] > summary i,
    .support-action-history[open] > summary i { transform: rotate(90deg); }
    .support-action-editor > summary:hover,
    .support-action-history > summary:hover { text-decoration: underline; }
    .support-action-history {
        margin-top: 20px;
        padding-top: 18px;
        border-top: 1px solid var(--border);
    }
    .support-action-history > summary { color: var(--text-muted); }

    .support-action-needed-badge {
        display: inline-flex;
        align-items: center;
        margin-left: 8px;
        padding: 2px 9px;
        border: 1px solid #FCD9A6;
        border-radius: 999px;
        background: #FFF8EE;
        color: #7C4A03;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: .01em;
        vertical-align: middle;
    }

    /* Each recorded action collapses to a one-line summary once there's more than one -
       otherwise the card body would stack full plan text and update-status forms. */
    .support-action-item {
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px 14px;
    }

    .support-action-item-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }
    .support-action-item-summary::-webkit-details-marker { display: none; }

    .support-action-item-title {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .support-action-item-caret {
        font-size: 0.72rem;
        color: var(--text-muted);
        transition: transform 0.15s ease;
    }
    .support-action-item[open] .support-action-item-caret {
        transform: rotate(90deg);
    }

    .support-action-item-body {
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
    }

    .risk-empty-state {
        padding: 18px;
        text-align: center;
        color: #64748B;
        font-size: 0.84rem;
    }

    .risk-score-card {
        background: #FFFFFF;
    }

    .risk-score-value {
        color: var(--risk-band-color);
        font-size: 3.35rem;
        font-weight: 800;
        line-height: 1;
    }

    .risk-band-pill {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 6px 13px;
        border: 1px solid #B9C7F6;
        border-radius: 999px;
        background: #EEF3FF;
        color: #1E3A8A;
        font-size: 0.82rem;
        font-weight: 800;
    }

    .risk-band-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        background: #3156C7;
    }

    .risk-band-pill-low {
        border-color: #BBF7D0;
        background: #F0FDF4;
        color: #166534;
    }

    .risk-band-pill-low .risk-band-dot {
        background: #16A34A;
    }

    .risk-band-pill-moderate {
        border-color: #FED7AA;
        background: #FFF7ED;
        color: #C2410C;
    }

    .risk-band-pill-moderate .risk-band-dot {
        background: #EA580C;
    }

    .risk-band-pill-high {
        border-color: #FDBA74;
        background: #FFF7ED;
        color: #9A3412;
    }

    .risk-band-pill-high .risk-band-dot {
        background: #F97316;
    }

    .risk-band-pill-critical {
        border-color: #FECACA;
        background: #FEF2F2;
        color: #B91C1C;
    }

    .risk-band-pill-critical .risk-band-dot {
        background: #DC2626;
    }

    .risk-score-band-low {
        --risk-band-color: #16A34A;
    }

    .risk-score-band-moderate {
        --risk-band-color: #EA580C;
    }

    .risk-score-band-high {
        --risk-band-color: #F97316;
    }

    .risk-score-band-critical {
        --risk-band-color: #DC2626;
    }

    .risk-score-meter {
        position: relative;
        height: 8px;
        margin-top: 16px;
        border-radius: 999px;
        background: #E2E8F0;
        overflow: hidden;
    }

    .risk-score-meter-fill {
        height: 100%;
        border-radius: inherit;
        background: var(--risk-band-color);
        transition: width .5s;
    }

    .risk-score-scale,
    .risk-score-scale-labels {
        display: flex;
        justify-content: space-between;
        color: #64748B;
        padding: 0 2px;
    }

    .risk-score-scale {
        margin-top: 5px;
        font-size: 0.68rem;
    }

    .risk-score-scale-labels {
        margin-top: 1px;
        font-size: 0.65rem;
    }

    .risk-interpretation {
        margin-top: 12px;
        padding: 11px 13px;
        border: 1px solid #D6E0FF;
        border-radius: 8px;
        background: #F7FAFF;
        color: #334155;
        font-size: 0.78rem;
        line-height: 1.45;
        text-align: left;
    }

    /* Escalating bands escalate the box: neutral for low/moderate, amber for high,
       red for critical, so urgency is visible without reading the sentence. */
    .risk-interpretation.is-high {
        border-color: #FCD9A6;
        background: #FFF8EE;
        color: #7C4A03;
        font-weight: 600;
    }

    .risk-interpretation.is-critical {
        border-color: #F5B5B5;
        background: #FEF2F2;
        color: #991B1B;
        font-weight: 700;
    }

    .risk-interpretation i {
        margin-right: 4px;
    }

    .risk-interpretation.is-critical i {
        animation: risk-urgent-pulse 1.8s ease-in-out 3;
    }

    @keyframes risk-urgent-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .35; }
    }

    @media (prefers-reduced-motion: reduce) {
        .risk-interpretation.is-critical i { animation: none; }
    }

    .risk-score-caption {
        margin: 12px 0 0;
        color: #475569;
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .risk-factor-contribution {
        color: #0F172A;
        font-size: 0.875rem;
        font-weight: 800;
    }

    .risk-factor-meta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 7px;
        margin-top: 7px;
        color: #64748B;
        font-size: 0.75rem;
    }

    .risk-factor-status {
        display: inline;
        color: #0F172A;
        font-weight: 800;
    }

    .risk-factor-status-muted {
        color: #94A3B8;
    }

    .risk-factor-detail {
        color: #64748B;
    }

    .risk-factor-bar {
        height: 5px;
        border-radius: 999px;
        background: #E2E8F0;
    }

    .risk-factor-fill {
        height: 5px;
        border-radius: inherit;
        background: #3156C7;
        transition: width .4s;
    }

    /* A factor at its cap can contribute nothing further. A full bar already says
       that, and the rows are sorted by contribution, so the cap is called out once
       in words rather than restated in colour on the label, number and bar. */
    .risk-factor-chip {
        padding: 1px 7px;
        border-radius: 999px;
        background: #E2E8F0;
        color: #475569;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: .01em;
    }
    .risk-total-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-top: 16px;
        padding: 14px 16px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: #F8FAFC;
    }

    .risk-total-summary-label {
        color: #0F172A;
        font-size: 0.875rem;
        font-weight: 700;
        line-height: 1.35;
    }

    .risk-total-summary-value {
        color: #0F172A;
        font-size: 1.25rem;
        font-weight: 800;
        line-height: 1.25;
        text-align: right;
        white-space: nowrap;
    }

    .score-history-list {
        max-height: 360px;
        overflow-y: auto;
        padding-right: 6px;
        display: grid;
        gap: 10px;
    }

    .score-timeline-toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 0;
        border: 0;
        background: transparent;
        color: inherit;
        text-align: left;
    }

    .score-timeline-toggle:focus-visible {
        outline: 3px solid rgba(49, 86, 199, .24);
        outline-offset: 4px;
        border-radius: 6px;
    }

    .score-timeline-toggle-meta {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        color: #64748B;
        font-size: 0.78rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .score-timeline-action {
        color: #3156C7;
        font-size: 0.78rem;
        font-weight: 800;
    }

    .score-timeline-toggle .bi-chevron-down {
        color: #3156C7;
        transition: transform .18s ease;
    }

    .score-timeline-toggle:not(.collapsed) .bi-chevron-down {
        transform: rotate(180deg);
    }

    .score-timeline-toggle .score-timeline-show,
    .score-timeline-toggle.collapsed .score-timeline-hide {
        display: none;
    }

    .score-timeline-toggle.collapsed .score-timeline-show,
    .score-timeline-toggle .score-timeline-hide {
        display: inline;
    }

    .score-history-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(190px, auto);
        gap: 16px;
        align-items: start;
        padding: 13px 14px;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        background: #FFFFFF;
        font-size: 0.84rem;
    }

    .score-history-main {
        min-width: 0;
    }

    .score-history-title {
        color: #0F172A;
        font-weight: 700;
    }

    .score-history-event {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .score-history-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border: 1px solid #D6E0FF;
        border-radius: 999px;
        background: #F7FAFF;
        color: #3156C7;
        font-size: 0.78rem;
        flex: 0 0 auto;
    }

    .score-history-meta,
    .score-history-reason {
        color: #64748B;
        font-size: 0.75rem;
        line-height: 1.45;
    }

    .score-history-reason {
        margin-top: 6px;
    }

    .score-history-result {
        display: flex;
        align-items: center;
        justify-content: flex-end;
    }

    .score-history-summary {
        padding: 9px 11px;
        border: 1px solid #E2E8F0;
        border-radius: 8px;
        background: #F8FAFC;
        color: #475569;
        font-size: 0.78rem;
        font-weight: 700;
        line-height: 1.45;
        text-align: right;
        white-space: nowrap;
    }

    .score-history-current {
        color: #0F172A;
        font-weight: 800;
    }

    .score-history-band-text {
        color: #3156C7;
        font-weight: 800;
    }

    @media (min-width: 1200px) {
        .risk-profile-sidebar {
            position: sticky;
            top: 16px;
        }
    }

    @media (max-width: 992px) {
        .risk-profile-grid {
            grid-template-columns: 1fr;
        }

        .risk-profile-sidebar {
            position: static;
        }
    }

    @media (max-width: 576px) {
        .risk-profile-topbar {
            display: grid;
        }

        .score-history-row {
            grid-template-columns: 1fr;
        }

        .score-history-result {
            justify-content: stretch;
        }

        .score-history-summary {
            width: 100%;
            text-align: left;
            white-space: normal;
        }

        .score-timeline-toggle {
            align-items: flex-start;
        }

        .score-timeline-toggle-meta {
            margin-top: 2px;
        }

        .risk-total-summary {
            align-items: flex-start;
            flex-direction: column;
        }

        .risk-total-summary-value {
            text-align: left;
            white-space: normal;
        }
    }
</style>
@endsection

@section('content')
<div class="prism-content risk-profile-workspace">

    <div class="risk-profile-topbar">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Risk Dashboard', 'url' => route('officer.risk.index')],
                ['label' => $student->full_name],
            ]" />
            <h1 class="prism-page-title">{{ $student->full_name }}</h1>
            <p class="prism-page-sub">
                <span class="prism-ref-number">{{ $student->studentProfile?->student_number }}</span>
                &middot; {{ $student->studentProfile?->program }}
                &middot; {{ $student->studentProfile?->year_level }}
            </p>
            <p style="font-size:0.875rem;color:var(--text-secondary);margin-top:4px;">
                Transparent rule-based risk indicators update automatically as case and compliance records change.
            </p>
        </div>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>Please review the support-action fields.</strong>
                <ul class="mb-0 mt-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @if(! $score)
        <div class="prism-note">
            No rule-based risk score has been assessed for this student yet.
            A score is generated automatically when a case is opened and refreshed when risk factors change.
        </div>
    @else

        @php
            $bandLabels = [
                'critical' => 'Critical',
                'high'     => 'High',
                'moderate' => 'Moderate',
                'low'      => 'Low',
            ];
            $bLabel = $bandLabels[$score->risk_band] ?? 'Unrated';

            // On an elevated assessment that already has recorded follow-up, the support
            // work is the point of the page - it leads, and carries the blue header.
            $promoteSupport = in_array($score->risk_band, ['high', 'critical'], true)
                && $interventions->isNotEmpty();

            // Elevated but nothing recorded yet: too early to claim the page's lead spot
            // (no follow-up exists to lead with), but the officer still needs a nudge
            // that the record-action form below is now open and waiting.
            $needsAction = in_array($score->risk_band, ['high', 'critical'], true)
                && ! $promoteSupport;
        @endphp

        <div class="risk-profile-grid">

            <aside class="risk-profile-sidebar">

                <div class="prism-card risk-score-card risk-score-band-{{ $score->risk_band }}">
                    <div class="prism-card-body" style="text-align:center;padding:28px;">
                        <div class="risk-score-value">
                            {{ $score->composite_score }}
                        </div>
                        <div style="font-size:0.82rem;color:var(--text-muted);margin:4px 0 12px;">
                            Risk-Informed Composite Score
                        </div>
                        <span class="risk-band-pill risk-band-pill-{{ $score->risk_band }}">
                            <span class="risk-band-dot" aria-hidden="true"></span>
                            {{ $bLabel }} Risk
                        </span>
                        <div class="risk-score-meter">
                            <div class="risk-score-meter-fill"
                                 style="width:{{ $score->composite_score }}%;"></div>
                        </div>

                        <div class="risk-score-scale">
                            <span>0</span>
                            <span>26</span>
                            <span>51</span>
                            <span>76</span>
                            <span>100</span>
                        </div>
                        <div class="risk-score-scale-labels">
                            <span>Low</span>
                            <span>Moderate</span>
                            <span>High</span>
                            <span>Critical</span>
                        </div>

                        <p style="font-size:0.75rem;color:var(--text-muted);margin-top:12px;">
                            Last Assessed {{ $score->computed_at->format('M d, Y \a\t g:i A') }}
                        </p>
                        <p class="risk-score-caption">
                            A review signal, not a decision.
                        </p>

                        <div class="risk-interpretation {{ in_array($score->risk_band, ['high', 'critical'], true) ? 'is-'.$score->risk_band : '' }}">
                            @php
                                $interpretations = [
                                    'low'      => 'Low risk — routine monitoring.',
                                    'moderate' => 'Moderate risk — worth a review.',
                                    'high'     => 'High risk — support advisable.',
                                    'critical' => 'Critical risk — review immediately.',
                                ];
                            @endphp
                            @php
                                $interpretationIcon = match ($score->risk_band) {
                                    'critical' => 'bi-exclamation-octagon-fill',
                                    'high' => 'bi-exclamation-triangle-fill',
                                    default => 'bi-lightbulb',
                                };
                            @endphp
                            <i class="bi {{ $interpretationIcon }}" aria-hidden="true"></i>
                            {{ $interpretations[$score->risk_band] }}
                        </div>
                    </div>
                </div>

                <div class="risk-guidance">
                    <div class="risk-guidance-heading">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <ul>
                        <li>The score prompts review. It decides nothing.</li>
                        <li>A support action is not guilt and not a sanction.</li>
                        <li>
                            @if(in_array($score->risk_band, ['high', 'critical'], true))
                                Open actions must be closed or cancelled.
                            @else
                                New actions need a High or Critical assessment. Open ones must still be closed.
                            @endif
                        </li>
                    </ul>
                </div>

            </aside>

            <div class="risk-profile-main">
                <div class="prism-card">
                    <div class="prism-card-header {{ $promoteSupport ? '' : 'risk-section-header-blue' }}">
                        @if($promoteSupport)
                            {{-- Support work leads the page, so the indicators that justified
                                 it fold away until the officer wants to check them. --}}
                            <button class="score-timeline-toggle collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#riskIndicatorsCollapse"
                                    aria-expanded="false"
                                    aria-controls="riskIndicatorsCollapse">
                                <span>
                                    <span class="prism-card-title d-block">Risk Indicators</span>
                                    <span style="font-size:0.75rem;color:var(--text-muted);margin:3px 0 0;display:block;">
                                        Current factors contributing to this assessment
                                    </span>
                                </span>
                                <span class="score-timeline-toggle-meta">
                                    <span class="score-timeline-action">
                                        <span class="score-timeline-show">Show Details</span>
                                        <span class="score-timeline-hide">Hide Details</span>
                                    </span>
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </span>
                            </button>
                        @else
                            <div>
                                <h2 class="prism-card-title">Risk Indicators</h2>
                                <p style="font-size:0.75rem;color:var(--text-muted);margin:3px 0 0;">
                                    Current factors contributing to this assessment
                                </p>
                            </div>
                        @endif
                    </div>
                    <div id="riskIndicatorsCollapse" class="{{ $promoteSupport ? 'collapse' : '' }}">
                    <div class="prism-card-body">

                        @php
                            $factors = collect($score->contributing_factors)
                                ->sortByDesc('contribution');
                        @endphp

                        @foreach($factors as $code => $factor)
                            @php
                                $maxContrib = (float) $factor['weight'] * 100;
                                $pct        = $maxContrib > 0
                                    ? ($factor['contribution'] / $maxContrib) * 100
                                    : 0;
                                $isActive   = $factor['contribution'] > 0;
                                $isBoolean  = $factor['factor_type'] === 'boolean';
                                $cap        = (int) ($factor['cap'] ?? 0);
                                $raw        = (int) ($factor['raw_value'] ?? 0);
                                // Only counted factors can sit at a cap in a way worth naming:
                                // a triggered boolean is at its maximum by definition, and
                                // saying so on every one of them is noise.
                                $atCap      = ! $isBoolean && $cap > 0 && $raw >= $cap;
                            @endphp

                            <div class="risk-factor-row"
                                 style="{{ !$loop->last ? 'margin-bottom:18px;padding-bottom:18px;' : '' }}
                                        opacity:{{ $isActive ? '1' : '.45' }};">

                                <div style="display:flex;justify-content:space-between;
                                            align-items:flex-start;margin-bottom:6px;">
                                    <div>
                                        <div style="font-size:0.875rem;font-weight:600;">
                                            {{ $factor['factor_name'] }}
                                        </div>
                                        <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">
                                            {{ $factor['description'] ?? 'Documented rule-based risk factor.' }}
                                        </div>
                                        <div class="risk-factor-meta">
                                            @if($factor['factor_type'] === 'boolean')
                                                <span class="risk-factor-status {{ $factor['triggered'] ? '' : 'risk-factor-status-muted' }}">
                                                    {{ $factor['triggered'] ? 'Triggered' : 'Not triggered' }}
                                                </span>
                                                <span class="risk-factor-detail">&middot; Weight {{ $factor['weight'] * 100 }}%</span>
                                            @else
                                                <span class="risk-factor-status {{ $raw > 0 ? '' : 'risk-factor-status-muted' }}">
                                                    {{ $factor['raw_value'] }} recorded
                                                </span>
                                                <span class="risk-factor-detail">&middot; Max. {{ $factor['cap'] }}</span>
                                                <span class="risk-factor-detail">&middot; Weight {{ $factor['weight'] * 100 }}%</span>
                                            @endif
                                            @if($atCap && $isActive)
                                                <span class="risk-factor-chip">At cap</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0;margin-left:12px;">
                                        <span class="risk-factor-contribution">
                                            +{{ $factor['contribution'] }}
                                        </span>
                                        <div style="font-size:0.7rem;color:var(--text-muted);">
                                            / {{ number_format($maxContrib, 1) }} max
                                        </div>
                                    </div>
                                </div>

                                <div class="risk-factor-bar"
                                     title="{{ number_format($pct, 0) }}% of this factor's available weight">
                                    <div class="risk-factor-fill"
                                         style="width:{{ $pct }}%;"></div>
                                </div>

                            </div>
                        @endforeach

                        <div class="risk-total-summary">
                            <span class="risk-total-summary-label">
                                Risk-Informed Composite Score
                            </span>
                            <span class="risk-total-summary-value">
                                {{ $score->composite_score }}
                            </span>
                        </div>

                    </div>
                    </div>
                </div>

                @if($snapshots->isNotEmpty())
                    <div class="prism-card">
                        <div class="prism-card-header">
                            <button class="score-timeline-toggle collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#scoreTimelineCollapse"
                                    aria-expanded="false"
                                    aria-controls="scoreTimelineCollapse">
                                <span>
                                    <span class="prism-card-title d-block">Score Timeline</span>
                                    <span style="font-size:0.75rem;color:var(--text-muted);margin:3px 0 0;display:block;">
                                        Events that recalculated this student's risk score
                                    </span>
                                </span>
                                <span class="score-timeline-toggle-meta">
                                    <span class="score-timeline-action">
                                        <span class="score-timeline-show">Show Details</span>
                                        <span class="score-timeline-hide">Hide Details</span>
                                    </span>
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </span>
                            </button>
                        </div>
                        <div id="scoreTimelineCollapse" class="collapse">
                        <div class="prism-card-body">
                            <div class="score-history-list">
                                @foreach($snapshots as $snap)
                                    @php
                                        $changes = collect($snap->factor_changes ?? []);
                                        $topChange = $changes
                                            ->sortByDesc(fn ($change) => abs((float) ($change['delta'] ?? 0)))
                                            ->first();
                                    @endphp
                                    <div class="score-history-row">
                                        <div class="score-history-main">
                                            <div class="score-history-event">
                                                <span class="score-history-icon" aria-hidden="true">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </span>
                                                <div>
                                                    <div class="score-history-title">
                                                        {{ $snap->snapshot_type === 'term' ? $snap->term : $snap->triggerLabel() }}
                                                    </div>
                                                    <div class="score-history-meta">
                                                        {{ $snap->created_at->format('M d, Y \a\t g:i A') }}
                                                        @if($snap->triggeredBy)
                                                            &middot; {{ $snap->triggeredBy->list_name }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            @if($topChange)
                                                <div class="score-history-reason">
                                                    Main reason: {{ $topChange['factor_name'] }}
                                                </div>
                                            @else
                                                <div class="score-history-reason">
                                                    Recalculated with no major factor change.
                                                </div>
                                            @endif
                                        </div>
                                        <div class="score-history-result">
                                            <div class="score-history-summary">
                                                Resulting Band:
                                                <span class="score-history-band-text">{{ ucfirst($snap->risk_band) }} Risk</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        </div>
                    </div>
                @endif

                @php
                    $canRecordAction = in_array($score->risk_band, ['high', 'critical'], true);
                    $openActions = $interventions->filter(fn ($action) => ! $action->isClosed())->values();
                    $closedActions = $interventions->filter(fn ($action) => $action->isClosed())->values();
                    // Once there's more than one action to show, each gets its own summary
                    // line so the card doesn't turn into a wall of plans and status forms.
                    $collapseActions = $interventions->count() > 1;
                @endphp

                <div class="prism-card {{ $promoteSupport ? 'risk-section-lead' : '' }}" id="support-actions">
                    <div class="prism-card-header {{ $promoteSupport ? 'risk-section-header-blue' : '' }}">
                        <div>
                            <h2 class="prism-card-title">
                                Proactive Support Actions
                                @if($needsAction)
                                    <span class="support-action-needed-badge">Action Needed</span>
                                @endif
                            </h2>
                            <p style="font-size:0.75rem;color:var(--text-muted);margin:3px 0 0;">
                                Human-reviewed follow-up based on documented risk indicators
                            </p>
                        </div>
                        @if($canRecordAction)
                            <button type="button" class="prism-btn prism-btn-outline prism-btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#recordSupportActionModal">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i> Record Action
                            </button>
                        @endif
                    </div>
                    <div class="prism-card-body">

                        <div class="{{ $openActions->count() > 4 ? 'support-action-scroll' : '' }}">
                        @forelse($openActions as $intervention)
                            @include('officer.risk._support-action', [
                                'intervention' => $intervention,
                                'isLast' => $loop->last && $closedActions->isEmpty(),
                                'collapsible' => $collapseActions,
                            ])
                        @empty
                            <div class="risk-empty-state">
                                @if($closedActions->isEmpty())
                                    No proactive support actions have been recorded for this student.
                                @else
                                    No open support actions. Closed actions are kept in the history below.
                                @endif
                            </div>
                        @endforelse
                        </div>

                        @if($closedActions->isNotEmpty())
                            <details class="support-action-history">
                                <summary>
                                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                    Closed actions ({{ $closedActions->count() }})
                                </summary>
                                <div class="{{ $closedActions->count() > 4 ? 'support-action-scroll' : '' }}" style="margin-top:18px;">
                                    @foreach($closedActions as $intervention)
                                        @include('officer.risk._support-action', [
                                            'intervention' => $intervention,
                                            'isLast' => $loop->last,
                                            'collapsible' => $collapseActions,
                                        ])
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </div>
            </div>

        </div>

        @if($canRecordAction)
            <div class="modal fade" id="recordSupportActionModal" tabindex="-1" data-bs-backdrop="static"
                 aria-labelledby="recordSupportActionModalTitle" aria-hidden="true"
                 data-auto-open="{{ old('form') === 'record-support-action' ? 'true' : 'false' }}">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('officer.risk.interventions.store', $student) }}">
                            @csrf
                            <input type="hidden" name="form" value="record-support-action">
                            <div class="modal-header">
                                <h5 class="modal-title" id="recordSupportActionModalTitle">Record Support Action</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12 col-md-6">
                                        <label class="prism-label">Action Type <span class="text-danger">*</span></label>
                                        <select name="action_type" class="prism-select" required>
                                            <option value="">Select an action</option>
                                            @foreach($actionTypes as $value => $label)
                                                <option value="{{ $value }}" {{ old('action_type') === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="prism-label">Follow-up Date <span class="text-danger">*</span></label>
                                        <input type="date" name="follow_up_on" class="prism-input"
                                               value="{{ old('follow_up_on', today()->addDays(7)->format('Y-m-d')) }}"
                                               min="{{ today()->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="prism-label">Planned Action <span class="text-danger">*</span></label>
                                        <textarea name="action_plan" class="prism-input" rows="3" minlength="20" maxlength="5000"
                                                  placeholder="Describe the planned review, support, responsible steps, and expected follow-up."
                                                  required>{{ old('action_plan') }}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="prism-btn prism-btn-primary">Record Action</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            @push('scripts')
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const modal = document.getElementById('recordSupportActionModal');
                        if (modal && modal.dataset.autoOpen === 'true') {
                            bootstrap.Modal.getOrCreateInstance(modal).show();
                        }
                    });
                </script>
            @endpush
        @endif

    @endif

</div>
@endsection
