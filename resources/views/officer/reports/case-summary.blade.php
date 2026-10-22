@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'Case Summary Report')
@section('css')
<style>
    .case-summary-overview {
        display: flex;
        flex-direction: column;
        min-width: 0;
        overflow: hidden;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .sdo-content .case-summary-overview .prism-filter-bar {
        order: 2;
        min-width: 0;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .case-summary-stats {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        order: 1;
        min-width: 0;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .case-summary-stats > div {
        min-width: 0;
        width: auto;
        padding: 0;
    }

    /* A report is a grid.  Its children must be allowed to shrink, otherwise
       a wide table can use its intrinsic width and push the whole page past
       the mobile viewport instead of scrolling inside its own card. */
    .case-summary-overview,
    .case-summary-results,
    .case-summary-results > [class*="col-"],
    .case-summary-results .prism-card,
    .case-summary-results .table-responsive {
        max-width: 100%;
        min-width: 0;
    }

    .report-page {
        min-width: 0;
        max-width: 100%;
    }

    .report-page > * {
        min-width: 0;
    }

    .sdo-content .case-summary-overview .prism-stat {
        min-height: 96px;
        height: 100%;
        border: 0;
        border-right: 1px solid #e2e8f0;
        border-radius: 0;
        box-shadow: none;
    }

    .case-summary-stats > div:last-child .prism-stat {
        border-right: 0;
    }

    .case-summary-overview .summary-total .prism-stat-value,
    .case-summary-overview .summary-total .prism-stat-desc { color: #1d4ed8; }

    .case-summary-overview .summary-minor .prism-stat-value,
    .case-summary-overview .summary-minor .prism-stat-desc { color: #d97706; }

    .case-summary-overview .summary-major .prism-stat-value,
    .case-summary-overview .summary-major .prism-stat-desc { color: #dc2626; }

    .case-summary-overview .summary-resolved .prism-stat-value,
    .case-summary-overview .summary-resolved .prism-stat-desc { color: #15803d; }

    .case-summary-overview .summary-pending .prism-stat-value,
    .case-summary-overview .summary-pending .prism-stat-desc { color: #c2410c; }

    .case-summary-overview .summary-rate .prism-stat-value,
    .case-summary-overview .summary-rate .prism-stat-desc { color: #0f766e; }

    .case-summary-section-subtitle {
        margin: .2rem 0 0;
        color: var(--text-muted);
        font-size: .76rem;
        font-weight: 400;
    }

    .case-summary-status-name {
        color: var(--text);
        font-weight: 600;
    }

    .case-summary-month-short {
        display: none;
    }

    .case-summary-value-minor {
        color: #d97706;
        font-weight: 700;
    }

    .case-summary-value-major {
        color: #dc2626;
        font-weight: 700;
    }

    .case-summary-value-resolved {
        color: #15803d;
        font-weight: 700;
    }

    .case-summary-results .prism-table tfoot td {
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        font-weight: 700;
    }

    .case-summary-activity-total {
        display: grid;
        grid-template-columns: 40% repeat(3, 20%);
        flex: 0 0 auto;
        border-top: 1px solid #e5e7eb;
        background: #f8fafc;
        font-weight: 700;
    }

    .case-summary-activity-total > span {
        padding: .7rem 1rem;
    }

    .case-summary-activity-total > span:not(:first-child) {
        text-align: center;
    }

    .case-summary-offense-code {
        color: #475569;
        font-size: .8rem;
        font-weight: 700;
    }

    .case-summary-offense-name {
        color: var(--text);
        font-size: .84rem;
        font-weight: 500;
        overflow-wrap: anywhere;
    }

    .case-summary-results > div {
        display: flex;
        min-width: 0;
    }

    .sdo-content .case-summary-results .prism-card {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
    }

    .case-summary-results .prism-table-empty {
        display: grid;
        flex: 1;
        min-height: 90px;
        place-items: center;
    }

    @media (max-width: 991.98px) {
        .case-summary-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .case-summary-stats > div:nth-child(-n+3) .prism-stat {
            border-bottom: 1px solid #e2e8f0;
        }

        .case-summary-stats > div:nth-child(3n) .prism-stat {
            border-right: 0;
        }
    }

    @media (max-width: 575.98px) {
        /* Keep the table and its separate Total footer on the exact same
           40/20/20/20 column grid. */
        .case-summary-activity-scroll .prism-table {
            table-layout: fixed;
        }

        .case-summary-activity-scroll .prism-table th,
        .case-summary-activity-scroll .prism-table td {
            padding-left: .5rem !important;
            padding-right: .5rem !important;
        }

        .case-summary-activity-scroll .prism-table thead th {
            font-size: .65rem;
            letter-spacing: .02em;
        }

        .case-summary-month-full {
            display: none;
        }

        .case-summary-month-short {
            display: inline;
            white-space: nowrap;
        }

        /* A stacked Bootstrap row does not need desktop column gutters.  This
           keeps every card aligned with the KPI/filter card on both sides. */
        .case-summary-results {
            width: 100%;
            margin-inline: 0;
            --bs-gutter-x: 0;
            --bs-gutter-y: 1rem;
        }

        .case-summary-results > [class*="col-"] {
            padding-inline: 0;
        }

        /* Equal-height report cards work in the desktop columns, but shrink
           the donut body on a phone. Let stacked cards use their content
           height instead. */
        .sdo-content .case-summary-results .prism-card {
            height: auto;
        }

        .case-summary-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .case-summary-stats > div .prism-stat {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .case-summary-stats > div:nth-child(2n) .prism-stat {
            border-right: 0;
        }

        .case-summary-stats > div:nth-last-child(-n+2) .prism-stat {
            border-bottom: 0;
        }

        .sdo-content .case-summary-overview .prism-filter-bar {
            padding: 12px;
        }

        .case-summary-results .prism-card-header {
            align-items: flex-start;
            gap: .5rem;
        }

        .case-summary-results .prism-card-header > div {
            min-width: 0;
        }

        .case-summary-results .case-summary-section-subtitle {
            overflow-wrap: anywhere;
        }

        .case-summary-results .prism-card-header .prism-badge {
            flex: 0 0 auto;
        }

        .prism-card:has(#caseStatusDonut) .prism-card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .prism-card:has(#caseStatusDonut) .prism-card-header .prism-badge {
            margin-top: .15rem;
        }

        .case-status-donut-body {
            flex: 0 0 auto;
            gap: 1rem;
            padding: 1rem;
        }

        .case-status-donut-plot {
            width: 160px;
            height: 160px;
        }

        .case-status-donut-legend {
            width: 100%;
            gap: .4rem;
        }

        .case-status-donut-legend-item {
            padding: .3rem .55rem;
        }
    }

    .prism-card:has(#caseStatusDonut) {
        display: flex;
        flex-direction: column;
    }

    .case-status-donut-body {
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        gap: 2rem;
        padding: 1.75rem;
        flex: 1 1 auto;
        min-height: 0;
    }

    .case-status-donut-plot {
        position: relative;
        width: 200px;
        height: 200px;
        flex: 0 0 auto;
    }

    .case-status-donut-svg {
        width: 100%;
        height: 100%;
        overflow: visible;
        filter: drop-shadow(0 10px 18px rgba(15, 23, 42, .12));
    }

    .case-status-donut-svg path,
    .case-status-donut-svg circle {
        cursor: pointer;
        outline: none;
    }

    .case-status-donut-arc {
        stroke: #fff;
        stroke-width: 2.5;
        stroke-linejoin: round;
        transition: opacity .15s ease, transform .15s ease;
        transform-box: fill-box;
        transform-origin: center;
    }

    .case-status-donut-arc:hover,
    .case-status-donut-arc:focus-visible {
        opacity: .88;
        transform: scale(1.03);
    }

    .case-status-donut-hole {
        fill: #fff;
    }

    .case-status-donut-center {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        pointer-events: none;
    }

    .case-status-donut-center-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }

    .case-status-donut-center-label {
        margin-top: 2px;
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .case-status-donut-legend {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        flex: 0 1 auto;
        gap: .5rem;
        min-width: 0;
        max-height: 210px;
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    .case-status-donut-legend-item {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .6rem;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        cursor: default;
        max-width: 100%;
    }

    .case-status-donut-swatch {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .case-status-donut-legend-label {
        color: var(--text);
        font-size: .78rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .case-status-donut-legend-pct {
        color: var(--text-muted);
        font-size: .74rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .case-status-donut-tooltip {
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

    .case-status-donut-tooltip[hidden] {
        display: none;
    }

    .case-status-donut-tooltip-label {
        margin-bottom: 2px;
        font-weight: 800;
    }

    .case-status-donut-tooltip-value {
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    /* Below 576px the donut card is full width but still too narrow for a
       side-by-side legend; between 992-1199.98px it's back to being cramped
       because the lg two-column split gives it only ~42% of the page. Both
       ranges get the same stacked layout so the legend never gets clipped. */
    @media (max-width: 575.98px), (min-width: 992px) and (max-width: 1199.98px) {
        .case-status-donut-body {
            flex-direction: column;
            align-items: center;
        }

        .case-status-donut-legend {
            align-items: center;
            max-height: 150px;
        }

        .case-status-donut-plot {
            margin: 0 auto;
        }
    }

    .case-summary-activity-scroll {
        max-height: 230px;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    .case-summary-activity-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
    }

    /* The desktop report deliberately balances the left and right columns.
       Let table areas fill their card so the totals/result summary sit at the
       bottom rather than leaving an empty footer below them. */
    @media (min-width: 576px) {
        .case-summary-activity-card .case-summary-activity-scroll,
        .case-summary-offense-card > .table-responsive {
            flex: 1 1 auto;
            min-height: 0;
            max-height: none;
            max-width: 100%;
            overflow: auto;
            overscroll-behavior: contain;
        }

        .case-summary-offense-card .case-summary-offense-pagination {
            margin-top: auto;
        }
    }

    .case-summary-offense-pagination {
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
</style>
@endsection
@section('content')
@include('officer.reports._print_header', ['reportTitle' => "Case Summary — {$from} to {$to}"])
@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header d-flex align-items-center
                justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'Case Summary'],
            ]" />
            <h1 class="prism-page-title">Case Summary Report</h1>
            <p class="prism-page-sub">
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'case_summary',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'academic_period_id' => $academicPeriod?->id,
                'program' => $program,
            ],
        ])
    </div>

    <div class="case-summary-overview">
    <div class="prism-filter-bar report-actions">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.case-summary') }}"
              class="row g-2 align-items-end" data-auto-submit>
            @include('officer.reports._academic_period_filter', ['columnClass' => 'col-12 col-md-6 col-xl'])
            @include('officer.reports._program_filter')
            <div class="col-12 col-md-6 col-xl">
                <label for="caseSummaryFrom" class="prism-label">Start Date</label>
                <input id="caseSummaryFrom" type="date" name="from" class="prism-input"
                       value="{{ $from }}">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="caseSummaryTo" class="prism-label">End Date</label>
                <input id="caseSummaryTo" type="date" name="to" class="prism-input"
                       value="{{ $to }}">
            </div>
            <div class="col-12 col-sm-auto">
            </div>
        </form>
    </div>

    <div class="case-summary-stats">
        @php
            $resolutionRate = $total > 0
                ? round((($resolved + $dismissed) / $total) * 100, 1)
                : 0;
        @endphp
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-total">
                <div class="prism-stat-value">{{ $total }}</div>
                <div class="prism-stat-desc">Total Cases</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-minor">
                <div class="prism-stat-value">{{ $minor }}</div>
                <div class="prism-stat-desc">Minor</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-major">
                <div class="prism-stat-value">{{ $major }}</div>
                <div class="prism-stat-desc">Major</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-resolved">
                <div class="prism-stat-value">{{ $resolved }}</div>
                <div class="prism-stat-desc">Resolved</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-pending">
                <div class="prism-stat-value">{{ $pending }}</div>
                <div class="prism-stat-desc">Still Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat summary-rate">
                <div class="prism-stat-value">{{ $resolutionRate }}%</div>
                <div class="prism-stat-desc">Resolution Rate</div>
            </div>
        </div>
    </div>
    </div>

    @php
        $monthlyRows = [];

        foreach ($months as $key => $name) {
            $minorMonth = $minorByMonth[$key] ?? null;
            $majorMonth = $majorByMonth[$key] ?? null;
            $mCount = $minorMonth ? (int) $minorMonth->total : 0;
            $mjCount = $majorMonth ? (int) $majorMonth->total : 0;
            $resolvedCount = ($minorMonth ? (int) $minorMonth->resolved_count : 0)
                + ($majorMonth ? (int) $majorMonth->resolved_count : 0);

            $monthlyRows[] = [
                'name' => $name,
                'minor' => $mCount,
                'major' => $mjCount,
                'resolved' => $resolvedCount,
            ];
        }

        $activeMonthlyRows = collect($monthlyRows)->filter(fn ($row) => $row['minor'] + $row['major'] > 0);
        $activityTotalResolved = collect($monthlyRows)->sum('resolved');
    @endphp

    @php
        $statusChartColors = [
            'open' => '#2563eb',
            'pending_nte' => '#ea580c',
            'awaiting_explanation' => '#7c3aed',
            'under_review' => '#0f766e',
            'pending_upcc' => '#4338ca',
            'pending_resolution' => '#b45309',
            'resolved' => '#16a34a',
            'dismissed' => '#64748b',
            'voided' => '#dc2626',
        ];

        $statusLabels = [
            'open' => 'Open',
            'pending_nte' => 'Pending NTE',
            'awaiting_explanation' => 'Awaiting Explanation',
            'under_review' => 'Under Review',
            'pending_upcc' => 'Pending UPCC',
            'pending_resolution' => 'Pending Resolution',
            'resolved' => 'Resolved',
            'dismissed' => 'Dismissed',
            'voided' => 'Voided',
        ];

        $rawStatusSegments = collect($byStatus)->map(fn ($count, $status) => [
            'label' => $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status)),
            'value' => (int) $count,
            'color' => $statusChartColors[$status] ?? '#64748b',
        ])->values()->all();

        $pieSegments = \App\Support\PieChart::segments($rawStatusSegments, 90, 90, 80);
    @endphp

    <div class="row g-4 case-summary-results">
        <div class="col-12 col-lg-5 d-flex flex-column gap-4">
            <div class="prism-card">
                <div class="prism-card-header">
                    <div>
                        <h2 class="prism-card-title">Cases by Status</h2>
                        <p class="case-summary-section-subtitle">
                            Status distribution of cases within the selected date range
                        </p>
                    </div>
                    @if($total > 0)
                        <span class="prism-badge prism-badge-blue">{{ $total }} total</span>
                    @endif
                </div>
                @if($pieSegments->isEmpty())
                    <div class="prism-table-empty">
                        No cases fall within this date range.
                    </div>
                @else
                    <p class="visually-hidden">
                        Pie chart of case status distribution: {{ $pieSegments->map(fn (array $s) => "{$s['label']}: {$s['value']}")->implode(', ') }}.
                        The same figures are listed beside the chart.
                    </p>
                    <div class="case-status-donut-body" id="caseStatusDonut">
                        <div class="case-status-donut-plot">
                            <svg viewBox="0 0 180 180" class="case-status-donut-svg" role="group" aria-label="Case status distribution donut chart">
                                @foreach($pieSegments as $segment)
                                    @if($segment['path'] === null)
                                        <circle class="case-status-donut-arc" cx="90" cy="90" r="80" fill="{{ $segment['color'] }}"
                                                tabindex="0"
                                                data-label="{{ $segment['label'] }}"
                                                data-value="{{ $segment['value'] }}"
                                                data-pct="{{ $segment['pct'] }}"
                                                aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)"></circle>
                                    @else
                                        <path class="case-status-donut-arc" d="{{ $segment['path'] }}" fill="{{ $segment['color'] }}"
                                              tabindex="0"
                                              data-label="{{ $segment['label'] }}"
                                              data-value="{{ $segment['value'] }}"
                                              data-pct="{{ $segment['pct'] }}"
                                              aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)"></path>
                                    @endif
                                @endforeach
                                <circle class="case-status-donut-hole" cx="90" cy="90" r="46"></circle>
                            </svg>
                            <div class="case-status-donut-center">
                                <div class="case-status-donut-center-value">{{ $total }}</div>
                                <div class="case-status-donut-center-label">Total</div>
                            </div>
                        </div>
                        <div class="case-status-donut-legend">
                            @foreach($pieSegments as $segment)
                                <div class="case-status-donut-legend-item" title="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)">
                                    <span class="case-status-donut-swatch" style="background:{{ $segment['color'] }};" aria-hidden="true"></span>
                                    <span class="case-status-donut-legend-label">{{ $segment['label'] }}</span>
                                    <span class="case-status-donut-legend-pct">{{ $segment['pct'] }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="case-status-donut-tooltip" id="caseStatusDonutTooltip" role="tooltip" hidden></div>
                @endif
            </div>

            <div class="prism-card case-summary-activity-card">
                <div class="prism-card-header">
                    <div>
                        <h2 class="prism-card-title">Recorded Activity</h2>
                        <p class="case-summary-section-subtitle">
                            Minor, major, and resolved cases by month within the selected date range
                        </p>
                    </div>
                </div>
                @if($activeMonthlyRows->isEmpty())
                    <div class="prism-table-empty">
                        No cases fall within this date range.
                    </div>
                @else
                <div class="table-responsive case-summary-activity-scroll">
                    <table class="prism-table" aria-label="Recorded case activity by month">
                        <colgroup>
                            <col style="width:40%;">
                            <col style="width:20%;">
                            <col style="width:20%;">
                            <col style="width:20%;">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-center">Minor</th>
                                <th class="text-center">Major</th>
                                <th class="text-center">Resolved</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeMonthlyRows as $row)
                                <tr>
                                    <td class="case-summary-status-name">
                                        <span class="case-summary-month-full">{{ $row['name'] }}</span>
                                        <span class="case-summary-month-short" aria-label="{{ $row['name'] }}">
                                            {{ \Carbon\Carbon::parse($row['name'])->format('M. Y') }}
                                        </span>
                                    </td>
                                    <td class="text-center case-summary-value-minor">{{ $row['minor'] }}</td>
                                    <td class="text-center case-summary-value-major">{{ $row['major'] }}</td>
                                    <td class="text-center case-summary-value-resolved">{{ $row['resolved'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="case-summary-activity-total" aria-label="Recorded activity totals">
                    <span>Total</span>
                    <span class="case-summary-value-minor">{{ $minor }}</span>
                    <span class="case-summary-value-major">{{ $major }}</span>
                    <span class="case-summary-value-resolved">{{ $activityTotalResolved }}</span>
                </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="prism-card case-summary-offense-card" style="height:100%;">
                <div class="prism-card-header">
                    <div>
                        <h2 class="prism-card-title">Cases by Offense Type</h2>
                        <p class="case-summary-section-subtitle">
                            Confirmed offenses linked to cases within the selected date range
                        </p>
                    </div>
                </div>
                @if($byOffenseType->isEmpty())
                    <div class="prism-table-empty">
                        No confirmed offenses were recorded within this date range.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="prism-table" aria-label="Confirmed offenses grouped by type">
                            <thead>
                                <tr>
                                    <th>Offense Code</th>
                                    <th>Offense Description</th>
                                    <th>Classification</th>
                                    <th class="text-center">Occurrences</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($byOffenseType as $o)
                                    <tr>
                                        <td class="case-summary-offense-code prism-ref-number">
                                            {{ $o->code }}
                                        </td>
                                        <td class="case-summary-offense-name">{{ $o->title }}</td>
                                        <td>
                                            <span class="prism-badge
                                                {{ $o->offense_type === 'minor'
                                                    ? 'prism-badge-blue' : 'prism-badge-red' }}"
                                                  style="font-size:0.65rem;">
                                                {{ ucfirst($o->offense_type) }}
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <strong>{{ $o->total }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="case-summary-offense-pagination prism-pagination"><x-pagination :paginator="$byOffenseType" /></div>
                @endif
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(() => {
    const chart = document.getElementById('caseStatusDonut');
    const tooltip = document.getElementById('caseStatusDonutTooltip');
    if (!chart || !tooltip) return;

    const arcs = chart.querySelectorAll('.case-status-donut-arc');

    const buildTooltip = (arc) => {
        tooltip.replaceChildren();

        const label = document.createElement('div');
        label.className = 'case-status-donut-tooltip-label';
        label.textContent = arc.dataset.label;
        tooltip.appendChild(label);

        const value = document.createElement('div');
        value.className = 'case-status-donut-tooltip-value';
        value.textContent = `${arc.dataset.value} (${arc.dataset.pct}%)`;
        tooltip.appendChild(value);
    };

    const positionTooltip = (event) => {
        const tipRect = tooltip.getBoundingClientRect();
        let left = event.clientX - (tipRect.width / 2);
        left = Math.max(8, Math.min(left, window.innerWidth - tipRect.width - 8));
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${event.clientY - tipRect.height - 14}px`;
    };

    const showTooltip = (arc, event) => {
        buildTooltip(arc);
        tooltip.hidden = false;
        positionTooltip(event);
    };

    const hideTooltip = () => {
        tooltip.hidden = true;
    };

    arcs.forEach((arc) => {
        arc.addEventListener('mouseenter', (event) => showTooltip(arc, event));
        arc.addEventListener('mousemove', (event) => positionTooltip(event));
        arc.addEventListener('mouseleave', hideTooltip);
        arc.addEventListener('focus', (event) => {
            const rect = arc.getBoundingClientRect();
            showTooltip(arc, { clientX: rect.left + rect.width / 2, clientY: rect.top + rect.height / 2 });
        });
        arc.addEventListener('blur', hideTooltip);
    });

    window.addEventListener('scroll', hideTooltip, true);
})();
</script>
@endpush
