@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'UPCC Case Report')
@section('css')
<style>
    .upcc-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin-bottom: 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .sdo-content .upcc-overview .prism-filter-bar {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .upcc-summary {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        order: 1;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .upcc-summary > div {
        width: auto;
        padding: 0;
    }

    .sdo-content .upcc-overview .prism-stat {
        height: 100%;
        min-height: 96px;
        border: 0;
        border-right: 1px solid #e2e8f0;
        border-radius: 0;
        box-shadow: none;
    }

    .upcc-summary > div:last-child .prism-stat {
        border-right: 0;
    }

    .upcc-overview .upcc-total .prism-stat-value,
    .upcc-overview .upcc-total .prism-stat-desc { color: #1d4ed8; }

    .upcc-overview .upcc-concluded .prism-stat-value,
    .upcc-overview .upcc-concluded .prism-stat-desc { color: #15803d; }

    .upcc-overview .upcc-appeals .prism-stat-value,
    .upcc-overview .upcc-appeals .prism-stat-desc { color: #dc2626; }

    .upcc-overview .upcc-hearings .prism-stat-value,
    .upcc-overview .upcc-hearings .prism-stat-desc { color: #0369a1; }

    .upcc-overview .upcc-summary-proceedings .prism-stat-value,
    .upcc-overview .upcc-summary-proceedings .prism-stat-desc { color: #7e22ce; }

    @media (max-width: 991.98px) {
        .upcc-summary {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .upcc-summary > div:nth-child(-n+3) .prism-stat {
            border-bottom: 1px solid #e2e8f0;
        }

        .upcc-summary > div:nth-child(3n) .prism-stat {
            border-right: 0;
        }
    }

    @media (max-width: 575.98px) {
        .upcc-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .upcc-summary > div .prism-stat {
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }

        .upcc-summary > div:nth-child(2n) .prism-stat {
            border-right: 0;
        }

        .upcc-summary > div:last-child .prism-stat {
            border-right: 0;
            border-bottom: 0;
        }
    }
</style>
@endsection
@section('content')
@include('officer.reports._print_header', ['reportTitle' => "UPCC Case Report — {$from} to {$to}"])
@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header upcc-report-header d-flex align-items-center
                justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'UPCC Cases'],
            ]" />
            <h1 class="prism-page-title">UPCC Case Report</h1>
            <p class="prism-page-sub">
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                — {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'upcc_case_report',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'academic_period_id' => $academicPeriod?->id,
                'program' => $program,
            ],
        ])
    </div>

    <div class="upcc-overview">
    <div class="prism-filter-bar report-actions">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.upcc-cases') }}"
              class="row g-2 align-items-end" data-auto-submit>
            @include('officer.reports._academic_period_filter', ['columnClass' => 'col-12 col-md-6 col-xl'])
            @include('officer.reports._program_filter')
            <div class="col-12 col-md-6 col-xl">
                <label for="upccFrom" class="prism-label">Start Date</label>
                <input id="upccFrom" type="date" name="from" class="prism-input" value="{{ $from }}">
            </div>
            <div class="col-12 col-md-6 col-xl">
                <label for="upccTo" class="prism-label">End Date</label>
                <input id="upccTo" type="date" name="to" class="prism-input" value="{{ $to }}">
            </div>
            <div class="col-12 col-sm-auto">
            </div>
        </form>
    </div>

    <div class="upcc-summary">
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat upcc-total">
                <div class="prism-stat-value">{{ $total }}</div>
                <div class="prism-stat-desc">Total Referrals</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat upcc-concluded">
                <div class="prism-stat-value">{{ $concluded }}</div>
                <div class="prism-stat-desc">Concluded</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat upcc-appeals">
                <div class="prism-stat-value">{{ $appealsFiled }}</div>
                <div class="prism-stat-desc">Appeals Filed</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat upcc-hearings">
                <div class="prism-stat-value">{{ $fullHearing }}</div>
                <div class="prism-stat-desc">Full Hearings</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="prism-stat upcc-summary-proceedings">
                <div class="prism-stat-value">{{ $summary }}</div>
                <div class="prism-stat-desc">Summary Proceedings</div>
            </div>
        </div>
    </div>
    </div>

    <div class="prism-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">UPCC Proceedings</h2>
        </div>
        @if($referrals->isEmpty())
            <div class="prism-table-empty">
                No UPCC referrals in this period.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Case No.</th>
                            <th>Student</th>
                            <th>Offense</th>
                            <th>Type</th>
                            <th>Conference Date</th>
                            <th>Status</th>
                            <th>Appeal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($referrals as $r)
                            <tr>
                                <td class="prism-ref-number" style="font-size:0.82rem;">
                                    {{ $r->case?->case_number }}
                                </td>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $r->case?->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $r->case?->student?->studentProfile?->student_number }}
                                    </div>
                                </td>
                                <td style="font-size:0.8rem;">
                                    @if($r->case?->confirmedOffense)
                                        <span class="prism-ref-number" style="font-weight:700;">
                                            {{ $r->case->confirmedOffense->code }}
                                        </span>
                                    @else
                                        <span class="text-muted-cell">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $typeBadge = $r->proceedings_type === 'summary' ? 'prism-badge-violet' : 'prism-badge-navy';
                                    @endphp
                                    <span class="prism-badge {{ $typeBadge }}"
                                          style="font-size:0.65rem;">
                                        {{ $r->proceedings_type === 'summary'
                                            ? 'Summary' : 'Full Hearing' }}
                                    </span>
                                </td>
                                <td class="prism-cell-sub">
                                    @if($r->scheduled_conference_at)
                                        {{ \Carbon\Carbon::parse($r->scheduled_conference_at)->format('M d, Y') }}
                                    @else
                                        <span class="text-muted-cell">Not scheduled</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $statusBadges = [
                                            'scheduled' => 'prism-badge-blue',
                                            'in_progress' => 'prism-badge-amber',
                                            'concluded' => 'prism-badge-green',
                                            'reconsideration_filed' => 'prism-badge-red',
                                        ];
                                        $statusBadge = $statusBadges[$r->status] ?? 'prism-badge-gray';
                                    @endphp
                                    <span class="prism-badge {{ $statusBadge }}"
                                          style="font-size:0.65rem;">
                                        {{ ucwords(str_replace('_',' ',$r->status)) }}
                                    </span>
                                </td>
                                <td>
                                    @if($r->appeal_filed)
                                        <span class="prism-badge prism-badge-red"
                                              style="font-size:0.65rem;">Filed</span>
                                    @else
                                        <span class="text-muted-cell">—</span>
                                    @endif
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
