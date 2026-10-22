@extends('layouts.coordinator')
@section('title', 'Coordinator Dashboard')
@section('css')
<style>
    .coordinator-dashboard .dashboard-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
    }

    .coordinator-dashboard .dashboard-date {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
        padding: 9px 12px;
        border: 1px solid #dbe3f2;
        border-radius: 9px;
        background: #fff;
        white-space: nowrap;
    }

    .coordinator-dashboard .dashboard-date-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #edf3ff;
        color: #3156c7;
        font-size: 14px;
    }

    .coordinator-dashboard .dashboard-date-label {
        color: #64748b;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .coordinator-dashboard .dashboard-date-value {
        margin-left: 4px;
        color: #0f172a;
        font-size: 12px;
        font-weight: 700;
    }

    .coordinator-dashboard { display: grid; gap: 20px; }
    .coordinator-dashboard .dashboard-heading { margin-bottom: 2px; }
    .coordinator-dashboard .dashboard-announcements { margin-bottom: 0; }
    .coordinator-dashboard .dashboard-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }
    .coordinator-dashboard .dashboard-stat {
        position: relative;
        min-height: 104px;
        padding: 17px 18px;
        overflow: hidden;
        border: 1px solid #e1e7f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
    }
    .dashboard-stat-icon {
        position: absolute;
        top: 16px;
        right: 16px;
        display: grid;
        place-items: center;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        color: var(--stat-color);
        background: var(--stat-soft);
    }
    .dashboard-stat-value { color: #0f172a; font-size: 1.55rem; font-weight: 750; line-height: 1; }
    .dashboard-stat-label { margin-top: 9px; color: #64748b; font-size: .78rem; font-weight: 600; }
    .dashboard-stat-note { margin-top: 3px; color: #94a3b8; font-size: .68rem; }
    .dashboard-stat.blue { --stat-color: #3156c7; --stat-soft: #edf3ff; }
    .dashboard-stat.amber { --stat-color: #c2410c; --stat-soft: #fff1e8; }
    .dashboard-stat.violet { --stat-color: #7c3aed; --stat-soft: #f3e8ff; }
    .dashboard-stat.red { --stat-color: #dc2626; --stat-soft: #fef2f2; }

    .dashboard-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(280px, .75fr);
        gap: 16px;
        align-items: stretch;
    }
    .dashboard-panel {
        overflow: hidden;
        border: 1px solid #e1e7f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
    }
    .dashboard-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        min-height: 62px;
        padding: 14px 18px;
        border-bottom: 1px solid #edf1f6;
    }
    .dashboard-panel-title { margin: 0; color: #172033; font-size: .95rem; font-weight: 700; }
    .dashboard-panel-sub { margin-top: 3px; color: #94a3b8; font-size: .72rem; }
    .dashboard-link { color: #3156c7; font-size: .74rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .dashboard-link:hover { color: #24439e; }

    .program-chart-body { padding: 1rem 1.25rem 1.25rem; }
    .program-chart-legend { display: flex; align-items: center; gap: 1rem; margin-bottom: .75rem; }
    .program-chart-legend-item { display: inline-flex; align-items: center; gap: .4rem; color: #64748b; font-size: .74rem; font-weight: 700; }
    .program-chart-swatch { width: 9px; height: 9px; border-radius: 3px; flex-shrink: 0; }
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
    .program-chart-bar { width: 30%; max-width: 18px; min-height: 0; border-radius: 3px 3px 0 0; transition: opacity .12s ease; }
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
    .program-chart-tooltip-key.minor { background: #6d8cff; }
    .program-chart-tooltip-key.major { background: #f87171; }
    .program-chart-tooltip-value { font-weight: 800; font-variant-numeric: tabular-nums; }

    .dashboard-hearings { padding: 6px 16px 10px; }
    .dashboard-hearing { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; align-items: center; gap: 11px; padding: 11px 0; border-bottom: 1px solid #f0f3f7; }
    .dashboard-hearing:last-child { border-bottom: 0; }
    .dashboard-hearing-date { padding: 6px 3px; border-radius: 8px; background: #edf3ff; color: #3156c7; text-align: center; }
    .dashboard-hearing-day { display: block; font-size: .9rem; font-weight: 800; line-height: 1; }
    .dashboard-hearing-month { display: block; margin-top: 3px; font-size: .58rem; font-weight: 700; text-transform: uppercase; }
    .dashboard-hearing-case { color: #1e293b; font-size: .76rem; font-weight: 700; }
    .dashboard-hearing-meta { margin-top: 3px; overflow: hidden; color: #64748b; font-size: .66rem; text-overflow: ellipsis; white-space: nowrap; }
    .dashboard-hearing-time { color: #475569; font-size: .68rem; font-weight: 700; white-space: nowrap; }
    .dashboard-empty { padding: 28px 18px; color: #94a3b8; font-size: .78rem; text-align: center; }

    .dashboard-table-wrap { overflow-x: auto; }
    .dashboard-table { width: 100%; border-collapse: collapse; }
    .dashboard-table th { padding: 10px 18px; color: #8492aa; background: #fafbfc; font-size: .65rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .dashboard-table td { padding: 12px 18px; border-top: 1px solid #f0f3f7; color: #475569; font-size: .75rem; vertical-align: middle; }
    .dashboard-case-number { color: #1e293b; font-weight: 700; }
    .dashboard-student-meta { margin-top: 2px; color: #94a3b8; font-size: .66rem; }
    .dashboard-risk { font-size: .67rem; }

    @media (max-width: 1100px) {
        .coordinator-dashboard .dashboard-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 575.98px) {
        .coordinator-dashboard .dashboard-heading {
            align-items: flex-start;
            flex-direction: column;
            gap: 14px;
        }
        .coordinator-dashboard .dashboard-stats { grid-template-columns: 1fr; }
        .dashboard-date { width: auto; }
    }
</style>
@endsection
@section('content')
<div class="prism-content coordinator-dashboard">
    <header class="dashboard-heading">
        <div>
        <h1 class="prism-page-title">Welcome, {{ auth()->user()->first_name }}</h1>
        <p class="prism-page-sub">
            Review priority cases, monitor risk trends, and stay on top of recent activity.
        </p>
        </div>

        <div class="dashboard-date" aria-label="Today's date">
            <span class="dashboard-date-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
            <span>
                <span class="dashboard-date-label">Today</span>
                <span class="dashboard-date-value">{{ now()->format('F j, Y') }}</span>
            </span>
        </div>
    </header>

    @include('partials.dashboard-announcements')

    <section class="dashboard-stats" aria-label="Coordinator workload summary">
        <div class="dashboard-stat blue">
            <span class="dashboard-stat-icon"><i class="bi bi-folder2-open"></i></span>
            <div class="dashboard-stat-value">{{ number_format($stats['active_cases']) }}</div>
            <div class="dashboard-stat-label">Active UPCC cases</div>
            <div class="dashboard-stat-note">Cases currently under coordinator review</div>
        </div>
        <div class="dashboard-stat amber">
            <span class="dashboard-stat-icon"><i class="bi bi-calendar2-plus"></i></span>
            <div class="dashboard-stat-value">{{ number_format($stats['awaiting_schedule']) }}</div>
            <div class="dashboard-stat-label">Awaiting schedule</div>
            <div class="dashboard-stat-note">Cases without a conference date</div>
        </div>
        <div class="dashboard-stat violet">
            <span class="dashboard-stat-icon"><i class="bi bi-calendar-event"></i></span>
            <div class="dashboard-stat-value">{{ number_format($stats['upcoming_hearings']) }}</div>
            <div class="dashboard-stat-label">Hearings this week</div>
            <div class="dashboard-stat-note">Scheduled within the next 7 days</div>
        </div>
        <div class="dashboard-stat red">
            <span class="dashboard-stat-icon"><i class="bi bi-exclamation-diamond"></i></span>
            <div class="dashboard-stat-value">{{ number_format($stats['high_risk_cases']) }}</div>
            <div class="dashboard-stat-label">High-risk cases</div>
            <div class="dashboard-stat-note">High or critical student risk band</div>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div><h2 class="dashboard-panel-title">Violations By Academic Program</h2><div class="dashboard-panel-sub">Case volume by program and severity</div></div>
                <a class="dashboard-link" href="{{ route('coordinator.reports.monthly-violation') }}">View report</a>
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
                <div class="dashboard-empty">No program-level violation data available yet.</div>
            @else
                <p class="visually-hidden">
                    Bar chart of minor and major case counts by academic program: {{ $programChartRows->map(fn (array $p) => "{$p['program']}: {$p['minor_count']} minor, {$p['major_count']} major")->implode('; ') }}.
                </p>
                <div class="program-chart-body" id="programChart">
                    <div class="program-chart-legend">
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
                                    $minorPct = $programChartScaleMax > 0 ? ($row['minor_count'] / $programChartScaleMax) * 100 : 0;
                                    $majorPct = $programChartScaleMax > 0 ? ($row['major_count'] / $programChartScaleMax) * 100 : 0;
                                @endphp
                                <div class="program-chart-group"
                                     tabindex="0"
                                     data-program="{{ $row['program'] }}"
                                     data-minor="{{ $row['minor_count'] }}"
                                     data-major="{{ $row['major_count'] }}"
                                     aria-label="{{ $row['program'] }}: {{ $row['minor_count'] }} minor, {{ $row['major_count'] }} major">
                                    <div class="program-chart-group-bars">
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

        <section class="dashboard-panel">
            <div class="dashboard-panel-head">
                <div><h2 class="dashboard-panel-title">Upcoming Hearings</h2><div class="dashboard-panel-sub">Next scheduled UPCC conferences</div></div>
                <a class="dashboard-link" href="{{ route('coordinator.cases.index') }}">View all</a>
            </div>
            @forelse($upcomingHearings as $hearing)
                @if($loop->first)<div class="dashboard-hearings">@endif
                <div class="dashboard-hearing">
                    <div class="dashboard-hearing-date"><span class="dashboard-hearing-day">{{ $hearing->scheduled_conference_at->format('d') }}</span><span class="dashboard-hearing-month">{{ $hearing->scheduled_conference_at->format('M') }}</span></div>
                    <div style="min-width:0;"><div class="dashboard-hearing-case">{{ $hearing->case?->case_number ?? 'Case' }}</div><div class="dashboard-hearing-meta">{{ $hearing->case?->student?->list_name ?? 'Unknown student' }} · {{ $hearing->conference_location ?? 'Location pending' }}</div></div>
                    <div class="dashboard-hearing-time">{{ $hearing->scheduled_conference_at->format('g:i A') }}</div>
                </div>
                @if($loop->last)</div>@endif
            @empty
                <div class="dashboard-empty">No upcoming hearings scheduled.</div>
            @endforelse
        </section>
    </div>

    <section class="dashboard-panel">
        <div class="dashboard-panel-head">
            <div><h2 class="dashboard-panel-title">Recently Forwarded Cases</h2><div class="dashboard-panel-sub">Latest cases received for coordinator action</div></div>
            <a class="dashboard-link" href="{{ route('coordinator.cases.index') }}">View all cases</a>
        </div>
        @if($recentCases->isEmpty())
            <div class="dashboard-empty">No cases have been forwarded to the coordinator yet.</div>
        @else
            <div class="dashboard-table-wrap"><table class="dashboard-table"><thead><tr><th>Case</th><th>Student</th><th>Stage</th><th>Risk</th><th>Forwarded</th><th></th></tr></thead><tbody>
            @foreach($recentCases as $case)
                @php
                    $referral = $case->upccReferral;
                    $upccLabel = match(true) {
                        $case->status === 'pending_resolution'
                            => ['Pending Resolution', 'prism-badge-green'],
                        ! $referral
                            => ['Not Scheduled',      'prism-badge-orange'],
                        $referral->status === 'scheduled'
                            => ['Scheduled',          'prism-badge-blue'],
                        $referral->status === 'in_progress'
                            => ['In Progress',        'prism-badge-violet'],
                        $referral->status === 'concluded'
                            && ! $referral->decision_distributed_at
                            => ['Awaiting Distribution', 'prism-badge-orange'],
                        $referral->status === 'concluded'
                            && $referral->decision_distributed_at
                            && ! $referral->decision_final_at
                            => ['Appeal Window',      'prism-badge-orange'],
                        $referral->status === 'reconsideration_filed'
                            => ['Appeal Filed',       'prism-badge-red'],
                        default
                            => [ucwords(str_replace('_', ' ', (string) $referral->status)), 'prism-badge-gray'],
                    };
                    $risk = $case->student?->riskScore?->risk_band;
                @endphp
                <tr>
                    <td><div class="dashboard-case-number prism-ref-number">{{ $case->case_number }}</div><div class="dashboard-student-meta">{{ $case->confirmedOffense?->title ?? 'Offense pending' }}</div></td>
                    <td><strong style="color:#334155;">{{ $case->student?->list_name ?? 'Unknown student' }}</strong><div class="dashboard-student-meta prism-ref-number">{{ $case->student?->studentProfile?->student_number ?? 'No student number' }}</div></td>
                    <td><span class="prism-badge {{ $upccLabel[1] }}">{{ $upccLabel[0] }}</span></td>
                    <td><span class="dashboard-risk prism-badge {{ in_array($risk, ['high', 'critical']) ? 'prism-badge-red' : ($risk === 'moderate' ? 'prism-badge-orange' : 'prism-badge-gray') }}">{{ $risk ? ucfirst($risk) : 'Unassessed' }}</span></td>
                    <td>{{ $case->forwarded_at?->diffForHumans() ?? '—' }}</td>
                    <td><a class="dashboard-link" href="{{ route('coordinator.cases.show', $case) }}">View</a></td>
                </tr>
            @endforeach
            </tbody></table></div>
        @endif
    </section>
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

        [['minor', 'Minor'], ['major', 'Major']].forEach(([key, label]) => {
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
