@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'Monthly Violation Summary')
@section('css')
<style>
    .monthly-report-page {
        display: grid;
        gap: 1rem;
    }

    .monthly-report-page .prism-page-header,
    .monthly-report-page .prism-filter-bar {
        margin-bottom: 0;
    }

    .monthly-filter-form {
        display: flex;
        align-items: end;
        gap: .875rem;
        flex-wrap: wrap;
    }

    .monthly-filter-form .field {
        min-width: 180px;
    }

    .monthly-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .monthly-report-page .monthly-overview .prism-filter-bar {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .monthly-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        order: 1;
        border: 0;
        border-bottom: 1px solid #e2e8f0;
        border-radius: 0;
    }

    .monthly-summary-item {
        padding: .95rem 1rem;
        border-right: 1px solid #e2e8f0;
        background: #fff;
    }

    .monthly-summary-item:last-child {
        border-right: 0;
    }

    .monthly-summary-label {
        display: block;
        margin-bottom: .35rem;
        font-size: .75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .02em;
    }

    .monthly-summary-value {
        display: block;
        color: var(--text);
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1;
    }

    .monthly-summary-note {
        display: block;
        margin-top: .45rem;
        color: var(--text-muted);
        font-size: .82rem;
    }

    .monthly-summary-item.minor .monthly-summary-label { color: #1d4ed8; }
    .monthly-summary-item.major .monthly-summary-label { color: #dc2626; }
    .monthly-summary-item.total .monthly-summary-label { color: #c2410c; }
    .monthly-summary-item.escalation .monthly-summary-label { color: #7c3aed; }

    .monthly-report-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(320px, 420px);
        gap: 1rem;
        align-items: stretch;
    }

    .monthly-report-grid.single {
        grid-template-columns: minmax(0, 1fr);
    }

    .monthly-panel {
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }

    .monthly-panel-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        padding: .95rem 1rem;
        border-bottom: 1px solid var(--border);
        background: #fbfcfe;
    }

    .monthly-panel-title {
        margin: 0;
        color: var(--text);
        font-size: .98rem;
        font-weight: 800;
    }

    .monthly-panel-subtitle {
        display: block;
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 500;
    }

    .monthly-lowest-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        padding: 1rem;
    }

    .monthly-lowest-card {
        padding: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .monthly-lowest-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .75rem;
    }

    .monthly-lowest-card-title {
        margin: 0;
        color: var(--text);
        font-size: .9rem;
        font-weight: 800;
    }

    .monthly-lowest-list {
        display: grid;
        gap: .5rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .monthly-lowest-list li {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        color: #334155;
        font-size: .84rem;
        line-height: 1.45;
    }

    .monthly-lowest-list li > span:first-of-type {
        flex: 1;
        min-width: 0;
    }

    .monthly-lowest-count {
        flex: 0 0 auto !important;
        color: #475569;
        font-size: .76rem;
        font-weight: 700;
        white-space: nowrap;
    }

    .monthly-lowest-list i {
        margin-top: .15rem;
        color: #16a34a;
        font-size: .75rem;
    }

    .monthly-breakdown-body {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        align-items: stretch;
        gap: 1.25rem;
        padding: 1rem;
        flex: 1;
        min-height: 0;
    }

    .monthly-chart {
        display: flex;
        flex-direction: column;
        min-width: 0;
        height: 100%;
    }

    .monthly-chart-legend {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin-bottom: .85rem;
    }

    .monthly-chart-legend-item {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        color: var(--text-muted);
        font-size: .78rem;
        font-weight: 700;
    }

    .monthly-chart-swatch {
        width: 10px;
        height: 10px;
        border-radius: 3px;
        flex-shrink: 0;
    }

    .monthly-chart-swatch.minor { background: #3156c7; }
    .monthly-chart-swatch.major { background: #dc2626; }

    .monthly-chart-plot {
        position: relative;
        flex: 1;
        min-height: 460px;
        padding-left: 34px;
    }

    .monthly-chart-gridlines {
        position: absolute;
        inset: 0 0 26px 34px;
    }

    .monthly-chart-gridline {
        position: absolute;
        left: 0;
        right: 0;
        border-top: 1px solid #eef1f6;
    }

    .monthly-chart-gridline span {
        position: absolute;
        left: -34px;
        top: -7px;
        width: 28px;
        color: var(--text-muted);
        font-size: .68rem;
        font-variant-numeric: tabular-nums;
        text-align: right;
    }

    .monthly-chart-bars {
        position: absolute;
        inset: 0 0 0 34px;
        display: flex;
        align-items: stretch;
        gap: 4px;
        padding-bottom: 26px;
        border-bottom: 1px solid #e5e7eb;
        overflow-x: auto;
    }

    .monthly-chart-group {
        flex: 1;
        min-width: 26px;
        display: flex;
        flex-direction: column;
        align-items: center;
        border-radius: 6px;
        outline: none;
    }

    .monthly-chart-group:hover,
    .monthly-chart-group:focus-visible {
        background: #f8fafc;
    }

    .monthly-chart-group:focus-visible {
        box-shadow: inset 0 0 0 2px #3156c7;
    }

    .monthly-chart-group-bars {
        flex: 1;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        gap: 2px;
        width: 100%;
    }

    .monthly-chart-bar {
        width: 30%;
        max-width: 22px;
        min-height: 0;
        border-radius: 4px 4px 0 0;
        transition: opacity .12s ease;
    }

    .monthly-chart-bar.minor { background: #3156c7; }
    .monthly-chart-bar.major { background: #dc2626; }

    .monthly-chart-group:hover .monthly-chart-bar,
    .monthly-chart-group:focus-visible .monthly-chart-bar {
        opacity: .82;
    }

    .monthly-chart-x-label {
        margin-top: 6px;
        max-width: 100%;
        overflow: hidden;
        color: var(--text-muted);
        font-size: .68rem;
        font-weight: 700;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .monthly-chart-tooltip {
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

    .monthly-chart-tooltip[hidden] { display: none; }

    .monthly-chart-tooltip-month {
        margin-bottom: 2px;
        font-weight: 800;
    }

    .monthly-chart-tooltip-row {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .monthly-chart-tooltip-key {
        width: 8px;
        height: 8px;
        border-radius: 2px;
        flex-shrink: 0;
    }

    .monthly-chart-tooltip-key.minor { background: #6d8cff; }
    .monthly-chart-tooltip-key.major { background: #f87171; }

    .monthly-chart-tooltip-value {
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    .monthly-empty-state {
        padding: 2rem 1rem;
        color: var(--text-muted);
        text-align: center;
        font-size: .9rem;
    }

    .monthly-offense-empty {
        display: grid;
        place-items: center;
        min-height: 190px;
        padding: 2rem 1.25rem;
        color: var(--text-muted);
        text-align: center;
    }

    .monthly-offense-empty i {
        display: block;
        margin-bottom: .65rem;
        color: #94a3b8;
        font-size: 1.5rem;
    }

    .monthly-offense-empty strong {
        display: block;
        margin-bottom: .25rem;
        color: var(--text);
        font-size: .9rem;
    }

    .monthly-offense-empty span {
        font-size: .8rem;
    }

    .monthly-muted {
        color: var(--text-muted);
    }

    .monthly-offense-scroll {
        max-height: 560px;
        overflow-y: auto;
    }

    .monthly-offense-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
    }

    @media (max-width: 991.98px) {
        .monthly-summary,
        .monthly-report-grid {
            grid-template-columns: 1fr;
        }

        .monthly-summary-item {
            border-right: 0;
            border-bottom: 1px solid var(--border);
        }

        .monthly-summary-item:last-child {
            border-bottom: 0;
        }
    }

    @media (max-width: 767.98px) {
        .monthly-lowest-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 479.98px) {
        .monthly-breakdown-body {
            padding: .75rem;
        }
    }
</style>
@endsection
@section('content')
@php
    $reportPeriodLabel = $academicPeriod?->label ?? (string) $year;
    $exportFilters = $academicPeriod
        ? ['academic_period_id' => $academicPeriod->id]
        : ['year' => $year];
@endphp
@include('officer.reports._print_header', ['reportTitle' => "Monthly Violation Summary - {$reportPeriodLabel}"])
@include('officer.reports._generated_layout')
@php
    $totalMinor = collect($minorByMonth)->sum('total');
    $totalMajor = collect($majorByMonth)->sum('total');
    $totalCases = $totalMinor + $totalMajor;

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
        ->sortByDesc('total_count')
        ->values()
        ->map(function (array $p) use ($programShortLabel) {
            $p['short'] = $programShortLabel($p['program']);

            return $p;
        });

    $chartPeakValue = $programChartRows
        ->flatMap(fn (array $p) => [$p['minor_count'], $p['major_count']])
        ->max() ?: 0;
    $chartScaleMax = $chartPeakValue > 0 ? (int) (ceil($chartPeakValue / 4) * 4) : 4;
    $chartTicks = [];
    for ($t = 4; $t >= 0; $t--) {
        $chartTicks[] = (int) round($chartScaleMax * $t / 4);
    }
@endphp
<div class="prism-content report-page monthly-report-page">

    <div class="prism-page-header d-flex align-items-center justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'Monthly Violation Summary'],
            ]" />
            <h1 class="prism-page-title">Monthly Violation Summary</h1>
            <p class="prism-page-sub">
                {{ $academicPeriod ? 'Academic Period:' : 'Calendar Year:' }}
                {{ $reportPeriodLabel }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'monthly_violation_summary',
            'filters' => $exportFilters,
        ])
    </div>

    <div class="monthly-overview">
        <div class="prism-filter-bar report-actions">
            <form method="GET"
                  action="{{ route($reportsRoutePrefix.'.reports.monthly-violation') }}"
                  class="monthly-filter-form" data-auto-submit>
                <div class="field" style="min-width:280px;">
                    <label class="prism-label">Academic Term</label>
                    <select name="academic_period_id" class="prism-select">
                        @foreach($academicPeriods as $period)
                            <option value="{{ $period->id }}"
                                {{ $academicPeriod?->id === $period->id ? 'selected' : '' }}>
                                {{ $period->label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <div class="monthly-summary">
            <div class="monthly-summary-item minor">
                <span class="monthly-summary-label">Minor cases</span>
                <span class="monthly-summary-value">{{ $totalMinor }}</span>
                <span class="monthly-summary-note">{{ $reportPeriodLabel }}</span>
            </div>
            <div class="monthly-summary-item major">
                <span class="monthly-summary-label">Major cases</span>
                <span class="monthly-summary-value">{{ $totalMajor }}</span>
                <span class="monthly-summary-note">{{ $reportPeriodLabel }}</span>
            </div>
            <div class="monthly-summary-item total">
                <span class="monthly-summary-label">Total cases</span>
                <span class="monthly-summary-value">{{ $totalCases }}</span>
                <span class="monthly-summary-note">{{ $reportPeriodLabel }}</span>
            </div>
            <div class="monthly-summary-item escalation">
                <span class="monthly-summary-label">Escalations flagged</span>
                <span class="monthly-summary-value">{{ $escalations }}</span>
                <span class="monthly-summary-note">{{ $reportPeriodLabel }}</span>
            </div>
        </div>
    </div>

    <section class="monthly-panel">
        <div class="monthly-panel-head">
            <div>
                <h2 class="monthly-panel-title">Programs with the Lowest Violation Counts</h2>
                <span class="monthly-panel-subtitle">
                    Based on non-voided cases in {{ $reportPeriodLabel }}; ties are ordered alphabetically
                </span>
            </div>
        </div>
        @if($programViolationCounts->isEmpty())
            <div class="monthly-empty-state">
                No student programs are available for comparison.
            </div>
        @else
            <div class="monthly-lowest-grid">
                <article class="monthly-lowest-card">
                    <div class="monthly-lowest-card-head">
                        <h3 class="monthly-lowest-card-title">Lowest Minor Violations</h3>
                    </div>
                    @if($hasMinorViolations)
                        <ul class="monthly-lowest-list">
                            @foreach($lowestMinorPrograms as $program)
                                <li>
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                    <span>{{ $program['program'] }}</span>
                                    <span class="monthly-lowest-count">
                                        {{ $program['minor_count'] }}
                                        {{ \Illuminate\Support\Str::plural('case', $program['minor_count']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="monthly-muted mb-0" style="font-size:.84rem;">
                            No minor violations were recorded for any represented program in {{ $reportPeriodLabel }}.
                        </p>
                    @endif
                </article>

                <article class="monthly-lowest-card">
                    <div class="monthly-lowest-card-head">
                        <h3 class="monthly-lowest-card-title">Lowest Major Violations</h3>
                    </div>
                    @if($hasMajorViolations)
                        <ul class="monthly-lowest-list">
                            @foreach($lowestMajorPrograms as $program)
                                <li>
                                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                                    <span>{{ $program['program'] }}</span>
                                    <span class="monthly-lowest-count">
                                        {{ $program['major_count'] }}
                                        {{ \Illuminate\Support\Str::plural('case', $program['major_count']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="monthly-muted mb-0" style="font-size:.84rem;">
                            No major violations were recorded for any represented program in {{ $reportPeriodLabel }}.
                        </p>
                    @endif
                </article>
            </div>
        @endif
    </section>

    <div class="monthly-report-grid">
        <section class="monthly-panel">
            <div class="monthly-panel-head">
                <div>
                    <h2 class="monthly-panel-title">Violations by Academic Program</h2>
                    <span class="monthly-panel-subtitle">Case volume by program and severity</span>
                </div>
                <span class="prism-badge prism-badge-blue">{{ $totalCases }} total</span>
            </div>
            <div class="monthly-breakdown-body">
                <div class="monthly-chart" id="monthlyChart">
                    <div class="monthly-chart-legend">
                        <span class="monthly-chart-legend-item">
                            <span class="monthly-chart-swatch minor" aria-hidden="true"></span>Minor
                        </span>
                        <span class="monthly-chart-legend-item">
                            <span class="monthly-chart-swatch major" aria-hidden="true"></span>Major
                        </span>
                    </div>
                    <p class="visually-hidden">
                        Bar chart of minor and major case counts by academic program for {{ $reportPeriodLabel }}.
                        The same figures are listed in the Programs with the Lowest Violation Counts panel above.
                    </p>
                    @if($programChartRows->isEmpty())
                        <div class="monthly-empty-state">
                            No program-level violation data is available for {{ $reportPeriodLabel }}.
                        </div>
                    @else
                        <div class="monthly-chart-plot">
                            <div class="monthly-chart-gridlines" aria-hidden="true">
                                @foreach($chartTicks as $tick)
                                    <div class="monthly-chart-gridline" style="bottom:{{ $chartScaleMax > 0 ? ($tick / $chartScaleMax) * 100 : 0 }}%;">
                                        <span>{{ number_format($tick) }}</span>
                                    </div>
                                @endforeach
                            </div>
                            <div class="monthly-chart-bars">
                                @foreach($programChartRows as $row)
                                    @php
                                        $minorPct = $chartScaleMax > 0 ? ($row['minor_count'] / $chartScaleMax) * 100 : 0;
                                        $majorPct = $chartScaleMax > 0 ? ($row['major_count'] / $chartScaleMax) * 100 : 0;
                                    @endphp
                                    <div class="monthly-chart-group"
                                         tabindex="0"
                                         data-program="{{ $row['program'] }}"
                                         data-minor="{{ $row['minor_count'] }}"
                                         data-major="{{ $row['major_count'] }}"
                                         aria-label="{{ $row['program'] }}: {{ $row['minor_count'] }} minor, {{ $row['major_count'] }} major">
                                        <div class="monthly-chart-group-bars">
                                            <div class="monthly-chart-bar minor" style="height:{{ $minorPct }}%;"></div>
                                            <div class="monthly-chart-bar major" style="height:{{ $majorPct }}%;"></div>
                                        </div>
                                        <span class="monthly-chart-x-label" title="{{ $row['program'] }}">{{ $row['short'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="monthly-chart-tooltip" id="monthlyChartTooltip" role="tooltip" hidden></div>
                    @endif
                </div>
            </div>
        </section>

        <section class="monthly-panel">
                <div class="monthly-panel-head">
                    <div>
                        <h2 class="monthly-panel-title">Top Offense Types</h2>
                        <span class="monthly-panel-subtitle">Most frequently recorded offenses</span>
                    </div>
                </div>
                @if($topOffenses->isEmpty())
                    <div class="monthly-offense-empty">
                        <div>
                            <strong>No offense types recorded</strong>
                            <span>There are no recorded offenses for {{ $reportPeriodLabel }}.</span>
                        </div>
                    </div>
                @else
                <div class="table-responsive monthly-offense-scroll">
                    <table class="prism-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Offense</th>
                                <th>Type</th>
                                <th class="text-center">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topOffenses as $i => $o)
                                <tr>
                                    <td class="monthly-muted" style="font-size:0.82rem;">
                                        {{ $i + 1 }}
                                    </td>
                                    <td>
                                        <div style="font-weight:700;">{{ $o->title }}</div>
                                        <div class="monthly-muted prism-ref-number"
                                             style="font-size:0.78rem;">
                                            {{ $o->code }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="prism-badge
                                            {{ $o->offense_type === 'minor'
                                                ? 'prism-badge-blue' : 'prism-badge-red' }}">
                                            {{ ucfirst($o->offense_type) }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <strong>{{ $o->occurrence_count }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
        </section>
    </div>

</div>
@endsection

@push('scripts')
<script>
(() => {
    const chart = document.getElementById('monthlyChart');
    const tooltip = document.getElementById('monthlyChartTooltip');
    if (!chart || !tooltip) return;

    const groups = chart.querySelectorAll('.monthly-chart-group');

    const buildTooltip = (group) => {
        tooltip.replaceChildren();

        const programEl = document.createElement('div');
        programEl.className = 'monthly-chart-tooltip-month';
        programEl.textContent = group.dataset.program;
        tooltip.appendChild(programEl);

        [['minor', 'Minor'], ['major', 'Major']].forEach(([key, label]) => {
            const row = document.createElement('div');
            row.className = 'monthly-chart-tooltip-row';

            const swatch = document.createElement('span');
            swatch.className = `monthly-chart-tooltip-key ${key}`;
            row.appendChild(swatch);

            const value = document.createElement('span');
            value.className = 'monthly-chart-tooltip-value';
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
        group.addEventListener('mousemove', () => positionTooltip(group));
        group.addEventListener('mouseleave', hideTooltip);
        group.addEventListener('focus', () => showTooltip(group));
        group.addEventListener('blur', hideTooltip);
    });

    window.addEventListener('scroll', hideTooltip, true);
})();
</script>
@endpush
