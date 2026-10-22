@extends('layouts.staff')

@section('title', 'Officer Dashboard')

@section('css')
<style>
    .officer-dashboard {
        display: grid;
        gap: 22px;
    }

    .prism-content.officer-dashboard > * + * {
        margin-top: 0;
    }

    .officer-dashboard .dashboard-announcements {
        margin-bottom: 0;
    }

    .dashboard-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
    }

    .dashboard-date {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 9px 12px;
        border: 1px solid #dbe3f2;
        border-radius: 9px;
        background: #fff;
    }

    .dashboard-date-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #edf3ff;
        color: #3156c7;
    }

    .dashboard-date-label {
        color: #64748b;
        font-size: .66rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .dashboard-date-value {
        margin-top: 1px;
        color: #0f172a;
        font-size: .78rem;
        font-weight: 700;
    }

    .dashboard-section {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
        border: 1px solid #dfe5f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
    }

    .dashboard-overview-grid {
        display: grid;
        grid-template-columns: minmax(340px, .85fr) minmax(0, 1.35fr);
        gap: 18px;
        align-items: stretch;
    }

    .dashboard-section-head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 17px 18px 14px;
        border-bottom: 1px solid #e5e9f2;
        background: #fff;
    }

    .dashboard-section-title {
        margin: 0;
        color: #0f172a;
        font-size: 1.08rem;
        font-weight: 700;
    }

    .dashboard-section-sub {
        margin: 3px 0 0;
        color: #64748b;
        font-size: .84rem;
    }

    .dashboard-attention-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        grid-auto-rows: 1fr;
        align-content: stretch;
        flex: 1;
        gap: 10px;
        padding: 14px;
        background: #f8fafc;
    }

    .dashboard-attention-card {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        min-width: 0;
        padding: 14px 16px;
        border: 1px solid #e4e9f1;
        border-radius: 10px;
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
    }

    .dashboard-attention-card:hover {
        border-color: #cbd5e1;
        background: #fbfcfe;
        box-shadow: 0 2px 10px rgba(15, 23, 42, .05);
    }

    .dashboard-attention-text { min-width: 0; }

    .dashboard-attention-label {
        display: block;
        color: #0f172a;
        font-size: .82rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .dashboard-attention-note {
        display: block;
        margin-top: 3px;
        color: #64748b;
        font-size: .72rem;
        line-height: 1.4;
    }

    .dashboard-attention-count {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 30px;
        height: 30px;
        padding: 0 8px;
        border-radius: 999px;
        background: #f1f5f9;
        color: #94a3b8;
        font-size: 1rem;
        font-weight: 800;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }

    .dashboard-attention-card.incidents.has-items .dashboard-attention-count { background: #edf3ff; color: #3156c7; }
    .dashboard-attention-card.notices.has-items .dashboard-attention-count { background: #fff3e6; color: #d97706; }
    .dashboard-attention-card.sanctions.has-items .dashboard-attention-count { background: #fdecec; color: #dc2626; }
    .dashboard-attention-card.risk.has-items .dashboard-attention-count { background: #f3e8ff; color: #7c3aed; }

    .program-chart-body { padding: 1rem 1.25rem 1.25rem; flex: 1; }
    .program-chart-legend { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    .program-chart-legend-item { display: inline-flex; align-items: center; gap: .4rem; color: #64748b; font-size: .74rem; font-weight: 700; }
    .program-chart-swatch { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
    .program-chart-swatch.unclassified { background: #94a3b8; }
    .program-chart-swatch.minor { background: #3156c7; }
    .program-chart-swatch.major { background: #dc2626; }
    .program-chart-plot { position: relative; min-height: 220px; padding-left: 30px; }
    .program-chart-gridlines { position: absolute; inset: 0 0 24px 30px; }
    .program-chart-gridline { position: absolute; left: 0; right: 0; border-top: 1px solid #eef1f6; }
    .program-chart-gridline span { position: absolute; left: -30px; top: -7px; width: 25px; color: #94a3b8; font-size: .62rem; font-variant-numeric: tabular-nums; text-align: right; }
    .program-chart-bars { position: absolute; inset: 0 0 0 30px; display: flex; align-items: stretch; gap: 4px; padding-bottom: 24px; border-bottom: 1px solid #e5e7eb; overflow-x: auto; }
    .program-chart-group { flex: 1; min-width: 24px; display: flex; flex-direction: column; align-items: center; border-radius: 6px; outline: none; }
    .program-chart-group:hover,
    .program-chart-group:focus-visible { background: #f8fafc; }
    .program-chart-group:focus-visible { box-shadow: inset 0 0 0 2px #3156c7; }
    .program-chart-group-bars { flex: 1; display: flex; align-items: flex-end; justify-content: center; gap: 2px; width: 100%; }
    .program-chart-bar { width: 26%; max-width: 16px; min-height: 0; border-radius: 3px 3px 0 0; transition: opacity .12s ease; }
    .program-chart-bar.unclassified { background: #94a3b8; }
    .program-chart-bar.minor { background: #3156c7; }
    .program-chart-bar.major { background: #dc2626; }
    .program-chart-group:hover .program-chart-bar,
    .program-chart-group:focus-visible .program-chart-bar { opacity: .82; }
    .program-chart-x-label { margin-top: 6px; max-width: 100%; overflow: hidden; color: #94a3b8; font-size: .62rem; font-weight: 700; white-space: nowrap; text-overflow: ellipsis; }
    .program-chart-tooltip {
        position: fixed;
        z-index: 40;
        padding: .5rem .65rem;
        border-radius: 8px;
        background: #0f172a;
        color: #fff;
        font-size: .76rem;
        line-height: 1.5;
        white-space: nowrap;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .25);
        pointer-events: none;
    }
    .program-chart-tooltip[hidden] { display: none; }
    .program-chart-tooltip-title { margin-bottom: 2px; font-weight: 800; }
    .program-chart-tooltip-row { display: flex; align-items: center; gap: .4rem; }
    .program-chart-tooltip-key { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }
    .program-chart-tooltip-key.unclassified { background: #cbd5e1; }
    .program-chart-tooltip-key.minor { background: #6d8cff; }
    .program-chart-tooltip-key.major { background: #f87171; }
    .program-chart-tooltip-value { font-weight: 800; font-variant-numeric: tabular-nums; }

    .dashboard-recent-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.35fr) minmax(340px, .85fr);
        gap: 18px;
        align-items: stretch;
    }

    .dashboard-panel {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-width: 0;
        border: 1px solid #dfe5f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(15, 23, 42, .035);
    }

    .dashboard-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e5e9f2;
        background: #fff;
    }

    .dashboard-panel-title { margin: 0; color: #0f172a; font-size: 1rem; font-weight: 700; }
    .dashboard-panel-sub { margin: 3px 0 0; color: #8a9ab5; font-size: .77rem; }
    .dashboard-panel-link { flex-shrink: 0; margin-top: 3px; color: #3156c7; font-size: .8rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .dashboard-panel-link:hover { color: #294ab3; }

    .dashboard-record {
        display: grid;
        grid-template-columns: 36px minmax(0, 1fr) auto;
        align-items: center;
        gap: 12px;
        padding: 13px 18px;
        border-bottom: 1px solid #edf0f5;
        color: inherit;
        text-decoration: none;
    }

    .dashboard-record:last-child { border-bottom: 0; }
    .dashboard-record:hover { background: #f8faff; }
    .dashboard-record-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 9px;
        background: #edf3ff;
        color: #3156c7;
        font-size: .85rem;
    }
    .dashboard-panel:last-child .dashboard-record-icon { background: #eef2ff; color: #4f46e5; }
    .dashboard-record-title { display: block; color: #0f172a; font-size: .88rem; font-weight: 700; }
    .dashboard-record-meta { display: block; margin-top: 3px; color: #64748b; font-size: .76rem; line-height: 1.4; }
    .dashboard-empty { display: grid; flex: 1; min-height: 150px; place-items: center; padding: 20px; color: #64748b; font-size: .86rem; text-align: center; }

    @media (max-width: 1199.98px) {
        .dashboard-overview-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 991.98px) {
        .dashboard-recent-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 767.98px) {
        .dashboard-heading { align-items: flex-start; flex-direction: column; }
    }

    @media (max-width: 479.98px) {
        .dashboard-attention-grid { grid-template-columns: 1fr; }
        .dashboard-record { grid-template-columns: 32px minmax(0, 1fr); }
        .dashboard-record > .prism-badge { grid-column: 2; justify-self: start; }
        .dashboard-record-icon { width: 32px; height: 32px; }
    }
</style>
@endsection

@section('content')
@php
    $caseStatusMap = [
        'open' => ['label' => 'Open', 'class' => 'prism-badge-blue'],
        'pending_nte' => ['label' => 'Pending NTE', 'class' => 'prism-badge-orange'],
        'awaiting_explanation' => ['label' => 'Awaiting Explanation', 'class' => 'prism-badge-violet'],
        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-teal'],
        'pending_upcc' => ['label' => 'Pending UPCC', 'class' => 'prism-badge-navy'],
        'pending_resolution' => ['label' => 'Pending Resolution', 'class' => 'prism-badge-amber'],
        'resolved' => ['label' => 'Resolved', 'class' => 'prism-badge-green'],
        'dismissed' => ['label' => 'Dismissed', 'class' => 'prism-badge-gray'],
        'voided' => ['label' => 'Voided', 'class' => 'prism-badge-red'],
    ];
    $incidentStatusMap = [
        'pending_review' => ['label' => 'Pending Review', 'class' => 'prism-badge-orange'],
        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-blue'],
        'converted_to_case' => ['label' => 'Case Created', 'class' => 'prism-badge-green'],
        'dismissed' => ['label' => 'Dismissed', 'class' => 'prism-badge-gray'],
    ];
@endphp
<div class="prism-content officer-dashboard">
    <header class="dashboard-heading">
        <div>
            <h1 class="prism-page-title">Welcome, {{ auth()->user()->first_name }}</h1>
            <p class="prism-page-sub">
                Here&rsquo;s an overview of your priorities and recent case activity.
            </p>
        </div>
        <div class="dashboard-date" aria-label="Today's date">
            <span class="dashboard-date-icon"><i class="bi bi-calendar3"></i></span>
            <span>
                <span class="dashboard-date-label">Today</span>
                <span class="dashboard-date-value">{{ now()->format('F d, Y') }}</span>
            </span>
        </div>
    </header>

    @include('partials.dashboard-announcements')

    <div class="dashboard-overview-grid">
    <section class="dashboard-section">
        <div class="dashboard-section-head">
            <div>
                <h2 class="dashboard-section-title">Needs Attention</h2>
                <p class="dashboard-section-sub">Items that may require action or follow-up.</p>
            </div>
        </div>

        <div class="dashboard-attention-grid" aria-label="Items needing attention">
            <a href="{{ route('officer.incidents.index', ['status' => 'pending_review']) }}" class="dashboard-attention-card incidents {{ $attention['pending_incidents'] > 0 ? 'has-items' : '' }}">
                <span class="dashboard-attention-text">
                    <span class="dashboard-attention-label">Pending Incident Reports</span>
                    <span class="dashboard-attention-note">Waiting for initial review</span>
                </span>
                <strong class="dashboard-attention-count">{{ $attention['pending_incidents'] }}</strong>
            </a>
            <a href="{{ route('officer.cases.index', ['status' => 'pending_nte']) }}" class="dashboard-attention-card notices {{ $attention['overdue_nte'] > 0 ? 'has-items' : '' }}">
                <span class="dashboard-attention-text">
                    <span class="dashboard-attention-label">Overdue NTE Responses</span>
                    <span class="dashboard-attention-note">Past the response deadline</span>
                </span>
                <strong class="dashboard-attention-count">{{ $attention['overdue_nte'] }}</strong>
            </a>
            <a href="{{ route('officer.sanctions.index') }}" class="dashboard-attention-card sanctions {{ $attention['overdue_sanctions'] > 0 ? 'has-items' : '' }}">
                <span class="dashboard-attention-text">
                    <span class="dashboard-attention-label">Overdue Sanctions</span>
                    <span class="dashboard-attention-note">Pending beyond the due date</span>
                </span>
                <strong class="dashboard-attention-count">{{ $attention['overdue_sanctions'] }}</strong>
            </a>
            <a href="{{ route('officer.risk.index', ['follow_up' => 'overdue']) }}" class="dashboard-attention-card risk {{ $attention['overdue_follow_ups'] > 0 ? 'has-items' : '' }}">
                <span class="dashboard-attention-text">
                    <span class="dashboard-attention-label">Overdue Support Follow-ups</span>
                    <span class="dashboard-attention-note">Past the follow-up date</span>
                </span>
                <strong class="dashboard-attention-count">{{ $attention['overdue_follow_ups'] }}</strong>
            </a>
        </div>
    </section>

    <section class="dashboard-section">
        <div class="dashboard-section-head">
            <div>
                <h2 class="dashboard-section-title">Violations in {{ $officerDepartmentLabel }}</h2>
                <p class="dashboard-section-sub">Case volume by program and severity within your {{ $officerDepartmentCount > 1 ? 'departments' : 'department' }}.</p>
            </div>
            <a href="{{ route('officer.reports.monthly-violation') }}" class="dashboard-panel-link">View report</a>
        </div>

        @php
            $programShortLabels = [
                'BS Information Technology' => 'IT',
                'BS Computer Engineering' => 'CPE',
                'BS Architecture' => 'BSArc',
                'BS Accountancy' => 'ACT',
                'BS Medical Technology' => 'BSMT',
                'BS Psychology' => 'BSPsy',
                'BS Civil Engineering' => 'CIV',
                'BA Communications' => 'COM',
                'BSBA Marketing Management' => 'MAR',
                'BS Tourism Management' => 'TOU',
            ];
            $programShortLabel = function (string $program) use ($programShortLabels): string {
                if (isset($programShortLabels[$program])) {
                    return $programShortLabels[$program];
                }
                $program = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $program));
                $words = preg_split('/\s+/', trim(preg_replace('/^(BS|BA|BSBA)\s+/i', '', $program)));
                $initials = collect($words)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');

                return $initials !== '' ? $initials : mb_strtoupper(mb_substr($program, 0, 4));
            };
            $programChartRows = $programViolationCounts
                ->filter(fn (array $p) => $p['total_count'] > 0)
                ->map(function (array $p) use ($programShortLabel) {
                    $p['short'] = $programShortLabel($p['program']);

                    return $p;
                });
        @endphp

        @if($programChartRows->isEmpty())
            <div class="dashboard-empty">No cases in your department yet.</div>
        @else
            <p class="visually-hidden">
                Bar chart of unclassified, minor, and major case counts by academic program: {{ $programChartRows->map(fn (array $p) => "{$p['program']}: {$p['unclassified_count']} unclassified, {$p['minor_count']} minor, {$p['major_count']} major")->implode('; ') }}.
            </p>
            <div class="program-chart-body" id="programChart">
                <div class="program-chart-legend">
                    <span class="program-chart-legend-item"><span class="program-chart-swatch unclassified" aria-hidden="true"></span>Unclassified</span>
                    <span class="program-chart-legend-item"><span class="program-chart-swatch minor" aria-hidden="true"></span>Minor</span>
                    <span class="program-chart-legend-item"><span class="program-chart-swatch major" aria-hidden="true"></span>Major</span>
                </div>
                <div class="program-chart-plot">
                    <div class="program-chart-gridlines" aria-hidden="true">
                        @foreach($programChartTicks as $tick)
                            <div class="program-chart-gridline" style="bottom:{{ $programChartScaleMax > 0 ? ($tick / $programChartScaleMax) * 100 : 0 }}%;">
                                <span>{{ number_format($tick) }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="program-chart-bars">
                        @foreach($programChartRows as $row)
                            @php
                                $unclassifiedPct = $programChartScaleMax > 0 ? ($row['unclassified_count'] / $programChartScaleMax) * 100 : 0;
                                $minorPct = $programChartScaleMax > 0 ? ($row['minor_count'] / $programChartScaleMax) * 100 : 0;
                                $majorPct = $programChartScaleMax > 0 ? ($row['major_count'] / $programChartScaleMax) * 100 : 0;
                            @endphp
                            <div class="program-chart-group"
                                 tabindex="0"
                                 data-program="{{ $row['program'] }}"
                                 data-unclassified="{{ $row['unclassified_count'] }}"
                                 data-minor="{{ $row['minor_count'] }}"
                                 data-major="{{ $row['major_count'] }}"
                                 aria-label="{{ $row['program'] }}: {{ $row['unclassified_count'] }} unclassified, {{ $row['minor_count'] }} minor, {{ $row['major_count'] }} major">
                                <div class="program-chart-group-bars">
                                    <div class="program-chart-bar unclassified" style="height:{{ $unclassifiedPct }}%;"></div>
                                    <div class="program-chart-bar minor" style="height:{{ $minorPct }}%;"></div>
                                    <div class="program-chart-bar major" style="height:{{ $majorPct }}%;"></div>
                                </div>
                                <span class="program-chart-x-label" title="{{ $row['program'] }}">{{ $row['short'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="program-chart-tooltip" id="programChartTooltip" role="tooltip" hidden></div>
        @endif
    </section>
    </div>

    <div class="dashboard-recent-grid">
        <section class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div>
                    <h2 class="dashboard-panel-title">Recently Updated Cases</h2>
                    <p class="dashboard-panel-sub">Latest case activity and status changes</p>
                </div>
                <a href="{{ route('officer.cases.index') }}" class="dashboard-panel-link">View all</a>
            </div>
            @forelse($recentCases as $case)
                <a href="{{ route('officer.cases.show', $case) }}" class="dashboard-record">
                    <span class="dashboard-record-icon"><i class="bi bi-folder2-open"></i></span>
                    <span>
                        <span class="dashboard-record-title">{{ $case->case_number }}</span>
                        <span class="dashboard-record-meta">
                            {{ $case->student?->list_name ?? 'Unknown student' }}
                            &middot; Updated {{ $case->updated_at->diffForHumans() }}
                        </span>
                    </span>
                    @php($caseSt = $caseStatusMap[$case->status] ?? ['label' => ucfirst(str_replace('_', ' ', $case->status)), 'class' => 'prism-badge-gray'])
                    <span class="prism-badge {{ $caseSt['class'] }}">
                        {{ $caseSt['label'] }}
                    </span>
                </a>
            @empty
                <div class="dashboard-empty">No cases have been created yet.</div>
            @endforelse
        </section>

        <section class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div>
                    <h2 class="dashboard-panel-title">Recent Incident Reports</h2>
                    <p class="dashboard-panel-sub">Latest reports submitted for review</p>
                </div>
                <a href="{{ route('officer.incidents.index') }}" class="dashboard-panel-link">View all</a>
            </div>
            @forelse($recentIncidents as $report)
                <a href="{{ route('officer.incidents.show', $report) }}" class="dashboard-record">
                    <span class="dashboard-record-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span>
                        <span class="dashboard-record-title">{{ $report->report_number }}</span>
                        <span class="dashboard-record-meta">
                            {{ $report->student?->list_name ?? $report->reported_by_name ?? 'No student identified' }}
                            &middot; Submitted {{ $report->created_at->diffForHumans() }}
                        </span>
                    </span>
                    @php($incidentSt = $incidentStatusMap[$report->status] ?? ['label' => ucfirst(str_replace('_', ' ', $report->status)), 'class' => 'prism-badge-gray'])
                    <span class="prism-badge {{ $incidentSt['class'] }}">
                        {{ $incidentSt['label'] }}
                    </span>
                </a>
            @empty
                <div class="dashboard-empty">No incident reports have been submitted yet.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const chart = document.getElementById('programChart');
    const tooltip = document.getElementById('programChartTooltip');
    if (!chart || !tooltip) return;

    const groups = chart.querySelectorAll('.program-chart-group');

    const buildTooltip = (group) => {
        tooltip.replaceChildren();

        const titleEl = document.createElement('div');
        titleEl.className = 'program-chart-tooltip-title';
        titleEl.textContent = group.dataset.program;
        tooltip.appendChild(titleEl);

        [['unclassified', 'Unclassified'], ['minor', 'Minor'], ['major', 'Major']].forEach(([key, label]) => {
            const row = document.createElement('div');
            row.className = 'program-chart-tooltip-row';

            const swatch = document.createElement('span');
            swatch.className = `program-chart-tooltip-key ${key}`;
            row.appendChild(swatch);

            const value = document.createElement('span');
            value.className = 'program-chart-tooltip-value';
            value.textContent = group.dataset[key];
            row.appendChild(value);

            const text = document.createElement('span');
            text.textContent = label;
            row.appendChild(text);

            tooltip.appendChild(row);
        });
    };

    const positionTooltip = (group) => {
        const rect = group.getBoundingClientRect();
        const tipRect = tooltip.getBoundingClientRect();
        let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${rect.top - tipRect.height - 10}px`;
    };

    const showTooltip = (group) => {
        buildTooltip(group);
        tooltip.hidden = false;
        positionTooltip(group);
    };

    const hideTooltip = () => {
        tooltip.hidden = true;
    };

    groups.forEach((group) => {
        group.addEventListener('mouseenter', () => showTooltip(group));
        group.addEventListener('mouseleave', hideTooltip);
        group.addEventListener('focus', () => showTooltip(group));
        group.addEventListener('blur', hideTooltip);
    });

    window.addEventListener('scroll', hideTooltip, true);
})();
</script>
@endpush
