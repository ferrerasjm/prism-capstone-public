@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'US Compliance Report')
@section('css')
<style>
    .duration-value {
        font-weight: 800;
    }

    .duration-unit {
        font-weight: 500 !important;
        color: var(--text-muted);
    }

    .duration-unit-plain {
        font-weight: inherit;
        color: inherit;
    }

    .us-compliance-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .sdo-content .us-compliance-overview .prism-filter-bar {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .us-compliance-stats {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        order: 1;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .us-compliance-stats > div {
        width: auto;
        padding: 0;
    }

    .sdo-content .us-compliance-overview .us-compliance-stats .prism-stat {
        height: 100%;
        min-height: 96px;
        border: 0;
        border-right: 1px solid #e2e8f0;
        border-radius: 0;
        box-shadow: none;
    }

    .us-compliance-stats > div:last-child .prism-stat {
        border-right: 0;
    }

    .us-compliance-overview .us-summary-total .prism-stat-value,
    .us-compliance-overview .us-summary-total .prism-stat-desc { color: #1d4ed8; }

    .us-compliance-overview .us-summary-completed .prism-stat-value,
    .us-compliance-overview .us-summary-completed .prism-stat-desc { color: #15803d; }

    .us-compliance-overview .us-summary-progress .prism-stat-value,
    .us-compliance-overview .us-summary-progress .prism-stat-desc { color: #4f46e5; }

    .us-compliance-overview .us-summary-pending .prism-stat-value,
    .us-compliance-overview .us-summary-pending .prism-stat-desc { color: #d97706; }

    .us-compliance-overview .us-summary-noncompliant .prism-stat-value,
    .us-compliance-overview .us-summary-noncompliant .prism-stat-desc { color: #b91c1c; }

    .sdo-content .us-compliance-overview .prism-card.us-compliance-hours {
        order: 3;
        margin: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    .sdo-content .us-compliance-overview .us-compliance-hours .prism-card-body {
        padding: 0;
    }

    .us-compliance-hours .row {
        --bs-gutter-x: 0;
        margin: 0;
    }

    .us-compliance-hours .col-6 {
        padding: 1.35rem 1rem;
    }

    .us-compliance-hours .col-6 + .col-6 {
        border-left: 1px solid #e2e8f0;
    }

    .compliance-donut-subtitle {
        margin: .2rem 0 0;
        color: var(--text-muted);
        font-size: .76rem;
        font-weight: 400;
    }

    .prism-card:has(#complianceDonut) {
        display: flex;
        flex-direction: column;
    }

    .compliance-donut-body {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 1.25rem;
        padding: 1.5rem;
        flex: 1 1 auto;
        min-height: 0;
    }

    .compliance-donut-plot {
        position: relative;
        width: min(100%, 280px);
        aspect-ratio: 1 / 1;
        flex: 1 1 auto;
        min-height: 0;
    }

    .compliance-donut-svg {
        width: 100%;
        height: 100%;
        overflow: visible;
        filter: drop-shadow(0 10px 18px rgba(15, 23, 42, .12));
    }

    .compliance-donut-arc {
        cursor: pointer;
        outline: none;
        stroke: #fff;
        stroke-width: 2.5;
        stroke-linejoin: round;
        transition: opacity .15s ease, transform .15s ease;
        transform-box: fill-box;
        transform-origin: center;
    }

    .compliance-donut-arc:hover,
    .compliance-donut-arc:focus-visible {
        opacity: .88;
        transform: scale(1.03);
    }

    .compliance-donut-hole {
        fill: #fff;
    }

    .compliance-donut-center {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        pointer-events: none;
    }

    .compliance-donut-center-value {
        font-size: 1.5rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.1;
    }

    .compliance-donut-center-label {
        margin-top: 2px;
        font-size: .66rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        color: var(--text-muted);
    }

    .compliance-donut-legend {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        flex: 0 0 auto;
        gap: .5rem;
    }

    .compliance-donut-legend-item {
        display: flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .6rem;
        border-radius: 999px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        cursor: default;
    }

    .compliance-donut-swatch {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .compliance-donut-legend-label {
        color: var(--text);
        font-size: .74rem;
        font-weight: 600;
    }

    .compliance-donut-legend-pct {
        color: var(--text-muted);
        font-size: .72rem;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }

    .compliance-donut-tooltip {
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

    .compliance-donut-tooltip[hidden] {
        display: none;
    }

    .compliance-donut-tooltip-label {
        margin-bottom: 2px;
        font-weight: 800;
    }

    .compliance-donut-tooltip-value {
        font-weight: 800;
        font-variant-numeric: tabular-nums;
    }

    @media (max-width: 575.98px) {
        .compliance-donut-body {
            flex-direction: column;
            align-items: stretch;
        }

        .compliance-donut-plot {
            margin: 0 auto;
        }
    }

    .us-assignment-supervisor {
        margin-top: 2px;
        color: #334155;
        font-size: .8rem;
    }

    .us-assignment-label {
        margin-right: 4px;
        color: #94a3b8;
        font-size: .67rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
    }

    @media (max-width: 991.98px) {
        .us-compliance-stats {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .us-compliance-stats > div:nth-child(-n+3) .prism-stat {
            border-bottom: 1px solid #e2e8f0;
        }

        .us-compliance-stats > div:nth-child(3n) .prism-stat {
            border-right: 0;
        }
    }

    @media (max-width: 575.98px) {
        .us-compliance-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .us-compliance-stats > div .prism-stat {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .us-compliance-stats > div:nth-child(2n) .prism-stat {
            border-right: 0;
        }

        .us-compliance-stats > div:last-child .prism-stat {
            border-right: 0;
            border-bottom: 0;
        }
    }

    .us-assignments-scroll {
        max-height: 530px;
        overflow-y: auto;
    }

    .us-assignments-scroll .prism-table th,
    .us-assignments-scroll .prism-table td {
        white-space: nowrap;
    }

    .us-assignments-scroll thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #fff;
    }

    .us-assignments-pagination {
        padding: 12px 16px;
        border-top: 1px solid #e2e8f0;
    }
</style>
@endsection
@section('content')
@include('officer.reports._print_header', ['reportTitle' => "US Compliance — {$from} to {$to}"])
@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header us-compliance-header d-flex align-items-center
                justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'US Compliance'],
            ]" />
            <h1 class="prism-page-title">US Compliance Report</h1>
            <p class="prism-page-sub">
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'us_compliance',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'academic_period_id' => $academicPeriod?->id,
                'program' => $program,
            ],
        ])
    </div>

    <div class="us-compliance-overview">
    <div class="prism-filter-bar report-actions">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.us-compliance') }}"
              class="row g-2 align-items-end" data-auto-submit>
            @include('officer.reports._academic_period_filter', ['columnClass' => 'col-12 col-md-6 col-xl'])
            @include('officer.reports._program_filter')
            <div class="col-12 col-md-6 col-xl">
                <label for="complianceFrom" class="prism-label">Start Date</label>
                <input id="complianceFrom" type="date" name="from" class="prism-input" value="{{ $from }}">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="complianceTo" class="prism-label">End Date</label>
                <input id="complianceTo" type="date" name="to" class="prism-input" value="{{ $to }}">
            </div>
            <div class="col-12 col-sm-auto">
            </div>
        </form>
    </div>

    <div class="us-compliance-stats">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat us-summary-total">
                <div class="prism-stat-value">{{ $total }}</div>
                <div class="prism-stat-desc">Total Assignments</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat us-summary-completed">
                <div class="prism-stat-value">{{ $completed }}</div>
                <div class="prism-stat-desc">Completed</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat us-summary-progress">
                <div class="prism-stat-value">{{ $inProgress }}</div>
                <div class="prism-stat-desc">In Progress</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat us-summary-pending">
                <div class="prism-stat-value">{{ $pending }}</div>
                <div class="prism-stat-desc">Pending</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat us-summary-noncompliant">
                <div class="prism-stat-value">{{ $nonCompliant }}</div>
                <div class="prism-stat-desc">Non-Compliant</div>
            </div>
        </div>
    </div>

    <div class="prism-card us-compliance-hours">
        <div class="prism-card-body">
            <div class="row g-3 text-center">
                <div class="col-6">
                    <div style="font-size:1.75rem;font-weight:800;color:#0f172a;">
                        <x-duration :value="\App\Models\UsAssignment::formatDuration($totalHoursRequired)" />
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);">
                        Total Hours Required
                    </div>
                </div>
                <div class="col-6">
                    <div style="font-size:1.75rem;font-weight:800;color:#0f172a;">
                        <x-duration :value="\App\Models\UsAssignment::formatDuration($totalHoursRendered)" />
                    </div>
                    <div style="font-size:0.78rem;color:var(--text-muted);">
                        Total Hours Rendered
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    @php
        $rawDonutSegments = [
            ['label' => 'Completed', 'value' => $completed, 'color' => '#15803d'],
            ['label' => 'In Progress', 'value' => $inProgress, 'color' => '#4f46e5'],
            ['label' => 'Pending', 'value' => $pending, 'color' => '#d97706'],
            ['label' => 'Non-Compliant', 'value' => $nonCompliant, 'color' => '#b91c1c'],
        ];

        $donutSegments = \App\Support\PieChart::segments($rawDonutSegments, 90, 90, 80);
    @endphp

    <div class="row g-4">
    <div class="col-12 col-lg-4">
    <div class="prism-card" style="height:100%;">
        <div class="prism-card-header">
            <div>
                <h2 class="prism-card-title">Compliance Breakdown</h2>
                <p class="compliance-donut-subtitle">
                    Assignment status distribution within the selected date range
                </p>
            </div>
            @if($total > 0)
                <span class="prism-badge prism-badge-green">{{ $completionRate }}% completed</span>
            @endif
        </div>
        @if($total === 0)
            <div class="prism-table-empty">
                No US assignments in this period.
            </div>
        @else
            <p class="visually-hidden">
                Pie chart of assignment status: {{ $completed }} completed, {{ $inProgress }} in progress,
                {{ $pending }} pending, {{ $nonCompliant }} non-compliant. The same figures are listed beside the chart.
            </p>
            <div class="compliance-donut-body" id="complianceDonut">
                <div class="compliance-donut-plot">
                    <svg viewBox="0 0 180 180" class="compliance-donut-svg" role="group" aria-label="US compliance status donut chart">
                        @foreach($donutSegments as $segment)
                            @if($segment['path'] === null)
                                <circle class="compliance-donut-arc" cx="90" cy="90" r="80" fill="{{ $segment['color'] }}"
                                        tabindex="0"
                                        data-label="{{ $segment['label'] }}"
                                        data-value="{{ $segment['value'] }}"
                                        data-pct="{{ $segment['pct'] }}"
                                        aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)"></circle>
                            @else
                                <path class="compliance-donut-arc" d="{{ $segment['path'] }}" fill="{{ $segment['color'] }}"
                                      tabindex="0"
                                      data-label="{{ $segment['label'] }}"
                                      data-value="{{ $segment['value'] }}"
                                      data-pct="{{ $segment['pct'] }}"
                                      aria-label="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)"></path>
                            @endif
                        @endforeach
                        <circle class="compliance-donut-hole" cx="90" cy="90" r="46"></circle>
                    </svg>
                    <div class="compliance-donut-center">
                        <div class="compliance-donut-center-value">{{ $completionRate }}%</div>
                        <div class="compliance-donut-center-label">Completed</div>
                    </div>
                </div>
                <div class="compliance-donut-legend">
                    @foreach($donutSegments as $segment)
                        <div class="compliance-donut-legend-item" title="{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['pct'] }}%)">
                            <span class="compliance-donut-swatch" style="background:{{ $segment['color'] }};" aria-hidden="true"></span>
                            <span class="compliance-donut-legend-label">{{ $segment['label'] }}</span>
                            <span class="compliance-donut-legend-pct">{{ $segment['pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="compliance-donut-tooltip" id="complianceDonutTooltip" role="tooltip" hidden></div>
        @endif
    </div>
    </div>

    <div class="col-12 col-lg-8">
    <div class="prism-card" style="height:100%;">
        <div class="prism-card-header">
            <h2 class="prism-card-title">All Assignments</h2>
        </div>
        @if($assignments->isEmpty())
            <div class="prism-table-empty">
                No US assignments in this period.
            </div>
        @else
            <div class="table-responsive us-assignments-scroll">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Office</th>
                            <th class="text-center">Required</th>
                            <th class="text-center">Rendered</th>
                            <th>Target Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($assignments as $a)
                            <tr style="{{ $a->status === 'non_compliant'
                                ? 'background:#fef2f2;' : '' }}">
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $a->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $a->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">{{ $a->office_name }}</div>
                                    <div class="us-assignment-supervisor">
                                        <span class="us-assignment-label">Supervisor</span>{{ $a->supervisor_display_name }}
                                    </div>
                                </td>
                                <td class="text-center"><x-duration :value="$a->requiredDuration()" short /></td>
                                <td class="text-center">
                                    <strong><x-duration :value="$a->renderedDuration()" short /></strong>
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $a->target_completion_date->format('M d, Y') }}
                                </td>
                                <td>
                                    @php
                                        $sb = ['pending'=>'prism-badge-orange','in_progress'=>'prism-badge-blue','completed'=>'prism-badge-green','non_compliant'=>'prism-badge-red'];
                                        $statusLabel = ucwords(str_replace('_', ' ', $a->status));
                                        $statusBadge = $sb[$a->status] ?? 'prism-badge-gray';
                                        if (in_array($a->status, ['pending', 'in_progress'], true) && $a->isOverdue()) {
                                            $statusLabel = 'Overdue';
                                            $statusBadge = 'prism-badge-red';
                                        }
                                    @endphp
                                    <span class="prism-badge {{ $statusBadge }}"
                                          style="font-size:0.68rem;">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="us-assignments-pagination prism-pagination"><x-pagination :paginator="$assignments" /></div>
        @endif
    </div>
    </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(() => {
    const chart = document.getElementById('complianceDonut');
    const tooltip = document.getElementById('complianceDonutTooltip');
    if (!chart || !tooltip) return;

    const arcs = chart.querySelectorAll('.compliance-donut-arc');

    const buildTooltip = (arc) => {
        tooltip.replaceChildren();

        const label = document.createElement('div');
        label.className = 'compliance-donut-tooltip-label';
        label.textContent = arc.dataset.label;
        tooltip.appendChild(label);

        const value = document.createElement('div');
        value.className = 'compliance-donut-tooltip-value';
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
