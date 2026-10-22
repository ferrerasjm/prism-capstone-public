@extends('layouts.coordinator')
@section('title', $student->full_name . ' — Risk Profile')

@section('css')
<style>
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

    @media (max-width: 991.98px) {
        .risk-profile-grid { grid-template-columns: 1fr; }
    }

    .risk-profile-sidebar { display: grid; gap: 16px; }

    .risk-score-card { text-align: center; }
    .risk-score-value { font-size: 2.6rem; font-weight: 800; line-height: 1; color: #0f172a; }
    .risk-band-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-top: 10px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.74rem;
        font-weight: 700;
    }
    .risk-band-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .risk-band-critical { color: #b91c1c; background: #fef2f2; }
    .risk-band-high     { color: #c2410c; background: #fff7ed; }
    .risk-band-moderate { color: #b45309; background: #fffbeb; }
    .risk-band-low      { color: #15803d; background: #f0fdf4; }

    .risk-score-meter {
        height: 8px;
        margin: 14px 0 6px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .risk-score-meter-fill { height: 100%; border-radius: 999px; background: #3156c7; }
    .risk-score-scale,
    .risk-score-scale-labels {
        display: flex;
        justify-content: space-between;
        font-size: 0.62rem;
        color: #94a3b8;
    }
    .risk-score-scale-labels { margin-top: 2px; font-weight: 700; }

    .risk-meta-line { font-size: 0.75rem; color: #64748b; margin-top: 12px; }

    .risk-guidance {
        padding: 14px 16px;
        border: 1px solid #dbe4f3;
        border-radius: 12px;
        background: #f7f9ff;
        font-size: 0.78rem;
        color: #475569;
        line-height: 1.6;
    }
    .risk-guidance ul { margin: 6px 0 0; padding-left: 18px; }

    .risk-factor-row + .risk-factor-row {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid var(--border);
    }
    .risk-factor-name { font-size: 0.875rem; font-weight: 600; color: #0f172a; }
    .risk-factor-desc { font-size: 0.72rem; color: #64748b; margin-top: 2px; }
    .risk-factor-meta { font-size: 0.7rem; color: #94a3b8; margin-top: 4px; }
    .risk-factor-contribution { font-size: 0.95rem; font-weight: 800; color: #3156c7; }
    .risk-factor-bar {
        height: 6px;
        margin-top: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .risk-factor-fill { height: 100%; border-radius: 999px; background: #3156c7; }

    .risk-total-summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 18px;
        padding-top: 14px;
        border-top: 2px solid var(--border);
        font-weight: 800;
        color: #0f172a;
    }

    /* Collapsible Score Timeline — same toggle pattern as the officer risk
       profile page (officer/risk/show): a header button that flips a chevron and
       swaps "Show Details" / "Hide Details", driven by Bootstrap collapse. */
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
        cursor: pointer;
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
    .score-timeline-action { color: #3156C7; font-size: 0.78rem; font-weight: 800; }
    .score-timeline-toggle .bi-chevron-down { color: #3156C7; transition: transform .18s ease; }
    .score-timeline-toggle:not(.collapsed) .bi-chevron-down { transform: rotate(180deg); }
    .score-timeline-toggle .score-timeline-show,
    .score-timeline-toggle.collapsed .score-timeline-hide { display: none; }
    .score-timeline-toggle.collapsed .score-timeline-show,
    .score-timeline-toggle .score-timeline-hide { display: inline; }
    .score-timeline-body { max-height: 360px; overflow-y: auto; }

    .score-history-row + .score-history-row {
        margin-top: 14px;
        padding-top: 14px;
        border-top: 1px solid var(--border);
    }
    .score-history-title { font-size: 0.85rem; font-weight: 700; color: #0f172a; }
    .score-history-meta { font-size: 0.72rem; color: #94a3b8; margin-top: 2px; }
    .score-history-reason { font-size: 0.76rem; color: #475569; margin-top: 4px; }
    .score-history-band { font-size: 0.74rem; font-weight: 700; color: #334155; margin-top: 4px; }

    .risk-action-row + .risk-action-row {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid var(--border);
    }
    .risk-action-title { font-size: 0.92rem; font-weight: 700; margin: 0; color: #0f172a; }
    .risk-action-plan {
        font-size: 0.82rem;
        color: var(--text-secondary);
        white-space: pre-line;
        overflow-wrap: anywhere;
        margin: 8px 0;
    }
    .risk-action-meta { font-size: 0.72rem; color: #94a3b8; line-height: 1.7; }
    .risk-action-outcome {
        margin-top: 10px;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        background: var(--bg);
        font-size: 0.78rem;
        white-space: pre-line;
        overflow-wrap: anywhere;
    }
    .risk-empty { font-size: 0.82rem; color: #94a3b8; }

    .risk-closed-actions > summary {
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 700;
        color: #3156c7;
        list-style: none;
    }
    .risk-closed-actions > summary::-webkit-details-marker { display: none; }
</style>
@endsection

@section('content')
<div class="prism-content risk-profile-workspace">

    <div class="risk-profile-topbar">
        <x-breadcrumbs :items="[
            ['label' => 'UPCC Risk Dashboard', 'url' => route('coordinator.risk.index')],
            ['label' => $student->full_name],
        ]" />
        <h1 class="prism-page-title">{{ $student->full_name }}</h1>
        <p class="prism-page-sub">
            <span class="prism-ref-number">{{ $student->studentProfile?->student_number }}</span>
            @if($student->studentProfile?->program)
                &middot; {{ $student->studentProfile->program }}
            @endif
            @if($student->studentProfile?->year_level)
                &middot; Year {{ $student->studentProfile->year_level }}
            @endif
        </p>
        <p style="font-size:0.82rem;color:var(--text-secondary);margin-top:4px;">
            Read-only risk oversight. Rule-based indicators update automatically as case and compliance records change.
        </p>
    </div>

    @if(! $score)
        <div class="prism-note">
            No rule-based risk score has been assessed for this student yet. A score is generated automatically
            when a case is opened and refreshed when risk factors change.
        </div>
    @else
        @php
            $bandLabels = ['critical' => 'Critical', 'high' => 'High', 'moderate' => 'Moderate', 'low' => 'Low'];
            $bLabel = $bandLabels[$score->risk_band] ?? 'Unrated';
            $trendLabel = match ($score->trend) {
                'increasing' => 'Increasing',
                'decreasing' => 'Decreasing',
                default => 'Stable',
            };
            $factors = collect($score->contributing_factors)->sortByDesc('contribution');
        @endphp

        <div class="risk-profile-grid">

            <aside class="risk-profile-sidebar">
                <div class="prism-card risk-score-card">
                    <div class="prism-card-body" style="padding:26px;">
                        <div class="risk-score-value">{{ $score->composite_score }}</div>
                        <div style="font-size:0.8rem;color:var(--text-muted);margin-top:4px;">
                            Risk-Informed Composite Score
                        </div>
                        <span class="risk-band-pill risk-band-{{ $score->risk_band }}">
                            <span class="risk-band-dot" aria-hidden="true"></span>
                            {{ $bLabel }} Risk
                        </span>
                        <div class="risk-score-meter">
                            <div class="risk-score-meter-fill" style="width:{{ min(100, (float) $score->composite_score) }}%;"></div>
                        </div>
                        <div class="risk-score-scale">
                            <span>0</span><span>26</span><span>51</span><span>76</span><span>100</span>
                        </div>
                        <div class="risk-score-scale-labels">
                            <span>Low</span><span>Moderate</span><span>High</span><span>Critical</span>
                        </div>
                        <div class="risk-meta-line">Trend: {{ $trendLabel }}</div>
                        <div class="risk-meta-line">
                            Last assessed {{ $score->computed_at?->format('M d, Y \a\t g:i A') ?? '—' }}
                        </div>
                        <p style="font-size:0.72rem;color:var(--text-muted);margin-top:10px;">
                            A review signal, not a decision.
                        </p>
                    </div>
                </div>

                <div class="risk-guidance">
                    <strong>How to read this</strong>
                    <ul>
                        <li>The score prompts review. It decides nothing.</li>
                        <li>Support actions are recorded and managed by the Discipline Office and Guidance.</li>
                        <li>Indicators are rule-based and fully transparent — every point is attributable to a factor below.</li>
                    </ul>
                </div>
            </aside>

            <div class="risk-profile-main" style="display:grid;gap:16px;">

                <div class="prism-card">
                    <div class="prism-card-header">
                        <div>
                            <h2 class="prism-card-title">Risk Indicators</h2>
                            <p style="font-size:0.74rem;color:var(--text-muted);margin:3px 0 0;">
                                Current factors contributing to this assessment
                            </p>
                        </div>
                    </div>
                    <div class="prism-card-body">
                        @forelse($factors as $factor)
                            @php
                                $maxContrib = (float) ($factor['weight'] ?? 0) * 100;
                                $pct = $maxContrib > 0 ? ((float) $factor['contribution'] / $maxContrib) * 100 : 0;
                                $isActive = (float) $factor['contribution'] > 0;
                                $isBoolean = ($factor['factor_type'] ?? null) === 'boolean';
                            @endphp
                            <div class="risk-factor-row" style="opacity:{{ $isActive ? '1' : '.45' }};">
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
                                    <div>
                                        <div class="risk-factor-name">{{ $factor['factor_name'] ?? $factor['factor_key'] ?? 'Risk factor' }}</div>
                                        <div class="risk-factor-desc">{{ $factor['description'] ?? 'Documented rule-based risk factor.' }}</div>
                                        <div class="risk-factor-meta">
                                            @if($isBoolean)
                                                {{ ($factor['triggered'] ?? false) ? 'Triggered' : 'Not triggered' }}
                                            @else
                                                {{ $factor['raw_value'] ?? 0 }} recorded &middot; Max. {{ $factor['cap'] ?? '—' }}
                                            @endif
                                            &middot; Weight {{ (float) ($factor['weight'] ?? 0) * 100 }}%
                                        </div>
                                    </div>
                                    <div style="text-align:right;flex-shrink:0;">
                                        <span class="risk-factor-contribution">+{{ $factor['contribution'] }}</span>
                                        <div style="font-size:0.68rem;color:var(--text-muted);">/ {{ number_format($maxContrib, 1) }} max</div>
                                    </div>
                                </div>
                                <div class="risk-factor-bar">
                                    <div class="risk-factor-fill" style="width:{{ max(0, min(100, $pct)) }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <p class="risk-empty">No contributing factors are recorded for this assessment.</p>
                        @endforelse

                        <div class="risk-total-summary">
                            <span>Risk-Informed Composite Score</span>
                            <span>{{ $score->composite_score }}</span>
                        </div>
                    </div>
                </div>

                @if($snapshots->isNotEmpty())
                    <div class="prism-card">
                        <div class="prism-card-header">
                            <button class="score-timeline-toggle collapsed"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#coordScoreTimeline"
                                    aria-expanded="false"
                                    aria-controls="coordScoreTimeline">
                                <span>
                                    <span class="prism-card-title d-block">Score Timeline</span>
                                    <span style="font-size:0.74rem;color:var(--text-muted);margin:3px 0 0;display:block;">
                                        {{ $snapshots->count() }}
                                        {{ \Illuminate\Support\Str::plural('event', $snapshots->count()) }}
                                        that recalculated this student's risk score
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
                        <div id="coordScoreTimeline" class="collapse">
                        <div class="prism-card-body score-timeline-body">
                            @foreach($snapshots as $snap)
                                @php
                                    $changes = collect($snap->factor_changes ?? []);
                                    $topChange = $changes
                                        ->sortByDesc(fn ($change) => abs((float) ($change['delta'] ?? 0)))
                                        ->first();
                                @endphp
                                <div class="score-history-row">
                                    <div class="score-history-title">
                                        {{ $snap->snapshot_type === 'term' ? $snap->term : $snap->triggerLabel() }}
                                    </div>
                                    <div class="score-history-meta">
                                        {{ $snap->created_at->format('M d, Y \a\t g:i A') }}
                                        @if($snap->triggeredBy)
                                            &middot; {{ $snap->triggeredBy->list_name }}
                                        @endif
                                    </div>
                                    <div class="score-history-reason">
                                        @if($topChange)
                                            Main reason: {{ $topChange['factor_name'] ?? 'factor change' }}
                                        @else
                                            Recalculated with no major factor change.
                                        @endif
                                    </div>
                                    <div class="score-history-band">
                                        Resulting band: {{ ucfirst($snap->risk_band) }} Risk
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        </div>
                    </div>
                @endif

                <div class="prism-card">
                    <div class="prism-card-header">
                        <div>
                            <h2 class="prism-card-title">Support Actions</h2>
                            <p style="font-size:0.74rem;color:var(--text-muted);margin:3px 0 0;">
                                Recorded by the Discipline Office and Guidance — read-only here
                            </p>
                        </div>
                    </div>
                    <div class="prism-card-body">
                        @php
                            $openActions = $interventions->reject(fn ($a) => $a->isClosed())->values();
                            $closedActions = $interventions->filter(fn ($a) => $a->isClosed())->values();
                        @endphp

                        @forelse($openActions as $intervention)
                            @include('coordinator.risk._support-action', ['intervention' => $intervention])
                        @empty
                            <p class="risk-empty">
                                @if($closedActions->isEmpty())
                                    No proactive support actions have been recorded for this student.
                                @else
                                    No open support actions. Closed actions are kept in the history below.
                                @endif
                            </p>
                        @endforelse

                        @if($closedActions->isNotEmpty())
                            <details class="risk-closed-actions" style="margin-top:16px;">
                                <summary>Closed actions ({{ $closedActions->count() }})</summary>
                                <div style="margin-top:16px;">
                                    @foreach($closedActions as $intervention)
                                        @include('coordinator.risk._support-action', ['intervention' => $intervention])
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    @endif

</div>
@endsection
