@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
    $statusLabels = [
        'all' => 'All escalation flags',
        'pending' => 'Pending review',
        'applied' => 'MAJOR-19 applied',
        'retained' => 'Retained / justified',
    ];
@endphp
@section('title', 'Escalation Report')
@section('css')
<style>
    .escalation-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .sdo-content .escalation-overview .prism-filter-bar {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .escalation-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        order: 1;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .escalation-summary > div {
        width: auto;
        padding: 0;
    }

    .sdo-content .escalation-overview .prism-stat {
        height: 100%;
        min-height: 96px;
        border: 0;
        border-right: 1px solid #e2e8f0;
        border-radius: 0;
        box-shadow: none;
    }

    .escalation-summary > div:last-child .prism-stat {
        border-right: 0;
    }

    .escalation-overview .escalation-total .prism-stat-value,
    .escalation-overview .escalation-total .prism-stat-desc { color: #1d4ed8; }

    .escalation-overview .escalation-pending .prism-stat-value,
    .escalation-overview .escalation-pending .prism-stat-desc { color: #d97706; }

    .escalation-overview .escalation-applied .prism-stat-value,
    .escalation-overview .escalation-applied .prism-stat-desc { color: #dc2626; }

    .escalation-overview .escalation-retained .prism-stat-value,
    .escalation-overview .escalation-retained .prism-stat-desc { color: #15803d; }

    .escalation-guidance {
        display: flex;
        align-items: flex-start;
        gap: .55rem;
        line-height: 1.55;
    }

    .escalation-guidance i {
        flex: 0 0 auto;
        margin-top: .15rem;
    }

    .escalation-empty {
        min-height: 110px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
    }

    @media (max-width: 767.98px) {
        .escalation-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .escalation-summary > div:nth-child(-n+2) .prism-stat {
            border-bottom: 1px solid #e2e8f0;
        }

        .escalation-summary > div:nth-child(2n) .prism-stat {
            border-right: 0;
        }
    }
</style>
@endsection
@section('content')
@include('officer.reports._print_header', ['reportTitle' => "Escalation / Habitual Offense Report — {$from} to {$to}"])
@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header d-flex align-items-center justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'Escalation Report'],
            ]" />
            <h1 class="prism-page-title">Escalation / Habitual Offense Report</h1>
            <p class="prism-page-sub">
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
                &middot; {{ $statusLabels[$status] ?? 'All escalation flags' }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'escalation_report',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'status' => $status,
                'academic_period_id' => $academicPeriod?->id,
                'program' => $program,
            ],
        ])
    </div>

    <div class="prism-note escalation-guidance mb-4">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div>
            <strong>About this report:</strong>
            Cases appear here after triggering PRISM's habitual-disregard escalation rules. Use this report to review pending decisions and monitor repeated minor-offense patterns.
        </div>
    </div>

    <div class="escalation-overview">
    <div class="prism-filter-bar report-actions">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.escalations') }}"
              class="row g-2 align-items-end" data-auto-submit>
            @include('officer.reports._academic_period_filter', [
                'columnClass' => 'col-12 col-md-6 col-xl',
            ])
            @include('officer.reports._program_filter')
            <div class="col-12 col-md-6 col-xl">
                <label for="escalationFrom" class="prism-label">Start Date</label>
                <input id="escalationFrom" type="date" name="from" class="prism-input" value="{{ $from }}">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="escalationTo" class="prism-label">End Date</label>
                <input id="escalationTo" type="date" name="to" class="prism-input" value="{{ $to }}">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="escalationStatus" class="prism-label">Status</label>
                <select id="escalationStatus" name="status" class="prism-select">
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-auto">
            </div>
        </form>
    </div>

    <div class="escalation-summary">
        <div class="col-6 col-md-3">
            <div class="prism-stat escalation-total">
                <div class="prism-stat-value">{{ $total }}</div>
                <div class="prism-stat-desc">Flagged Cases</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="prism-stat escalation-pending">
                <div class="prism-stat-value">{{ $pending }}</div>
                <div class="prism-stat-desc">Pending Review</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="prism-stat escalation-applied">
                <div class="prism-stat-value">{{ $applied }}</div>
                <div class="prism-stat-desc">MAJOR-19 Applied</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="prism-stat escalation-retained">
                <div class="prism-stat-value">{{ $retained }}</div>
                <div class="prism-stat-desc">Retained / Justified</div>
            </div>
        </div>
    </div>
    </div>

    <div class="prism-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">
                Escalation Records
            </h2>
        </div>

        @if($records->isEmpty())
            <div class="prism-table-empty escalation-empty">
                No escalation records found for the selected filters.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Case</th>
                            <th>Student</th>
                            <th>Triggering Offenses</th>
                            <th>Counters</th>
                            <th>Status</th>
                            <th>Officer Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($records as $record)
                            <tr>
                                <td>
                                    <div class="prism-cell-primary prism-ref-number">{{ $record['case_number'] }}</div>
                                    <div class="prism-cell-sub">
                                        Flagged {{ $record['flagged_at']->format('M d, Y') }}
                                    </div>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">{{ $record['student'] }}</div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $record['student_number'] }} &middot; {{ $record['program'] }}
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size:0.82rem;line-height:1.5;">{{ $record['offenses'] }}</div>
                                    <div class="prism-cell-sub mt-1">{{ $record['reason'] }}</div>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">
                                        Same type: {{ $record['same_type_count'] }} / {{ $record['same_type_threshold'] }}
                                    </div>
                                    <div class="prism-cell-sub">
                                        Different types: {{ $record['different_type_count'] }} / {{ $record['different_type_threshold'] }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match ($record['status_key']) {
                                            'pending' => 'prism-badge-orange',
                                            'applied' => 'prism-badge-red',
                                            default => 'prism-badge-blue',
                                        };
                                    @endphp
                                    <span class="prism-badge {{ $badgeClass }}">{{ $record['status_label'] }}</span>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">{{ $record['assigned_officer'] }}</div>
                                    <div class="prism-cell-sub">
                                        @if($record['acknowledged_at'])
                                            {{ $record['acknowledged_by'] }} &middot; {{ $record['acknowledged_at']->format('M d, Y') }}
                                        @else
                                            Awaiting officer decision
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
