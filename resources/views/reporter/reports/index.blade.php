@extends('layouts.reporting-user')
@section('title', 'My Reports')
@section('css')
<style>
    .reporter-reports-header { gap: 18px; }
    .reporter-reports-card { overflow: hidden; }
    .reporter-reports-card .prism-card-header { min-height: 62px; padding: 14px 18px; }
    .reporter-complaint-table { table-layout: fixed; width: 100%; }
    .reporter-complaint-table th { height: 42px; padding-top: 0 !important; padding-bottom: 0 !important; vertical-align: middle; }
    .reporter-complaint-table td { height: 66px; vertical-align: middle; }
    .reporter-complaint-table th:nth-child(1) { width: 16%; }
    .reporter-complaint-table th:nth-child(2) { width: 16%; }
    .reporter-complaint-table th:nth-child(3) { width: 24%; }
    .reporter-complaint-table th:nth-child(4) { width: 16%; }
    .reporter-complaint-table th:nth-child(5) { width: 16%; }
    .reporter-complaint-table th:nth-child(6) { width: 12%; text-align: center; }
    .reporter-complaint-table td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .reporter-complaint-table th:last-child, .reporter-complaint-table td:last-child { padding-left: 18px !important; padding-right: 18px !important; }
    .reporter-complaint-table td:last-child { text-align: center; white-space: nowrap; }
    .reporter-empty-state { display: grid; min-height: 66px; place-items: center; padding: 18px; color: #64748b; font-size: .82rem; text-align: center; }
    @media(max-width:991.98px){
        .reporter-complaint-table { table-layout: auto; min-width: 720px; }
        .reporter-complaint-table td { white-space: normal; }
    }
    @media(max-width:767.98px){
        .prism-page-header .reporter-submit-btn { flex: 0 1 auto; width: auto; align-self: flex-start; }
    }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header reporter-reports-header d-flex align-items-start justify-content-between">
        <div>
            <h1 class="prism-page-title">My Complaints</h1>
            <p class="prism-page-sub">Complaints you have submitted to the SDO</p>
        </div>
        <a href="{{ route('reporter.reports.create') }}" class="prism-btn prism-btn-primary reporter-submit-btn">
            <i class="bi bi-plus-lg"></i> Submit Complaint
        </a>
    </div>

    <div class="prism-card reporter-reports-card">
        <div class="prism-card-header prism-card-header--blue"><h2 class="prism-card-title">Submitted Complaints</h2></div>
        @if($reports->isEmpty())
            <div class="reporter-empty-state">You have not submitted any complaints.</div>
        @else
            <div class="table-responsive">
                <table class="prism-table reporter-complaint-table">
                    <thead>
                        <tr>
                            <th>Report No.</th>
                            <th>Incident Date</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reports as $report)
                        <tr>
                            <td><div class="prism-cell-primary prism-ref-number">{{ $report->report_number }}</div></td>
                            <td class="text-muted-cell">{{ \Carbon\Carbon::parse($report->date_of_incident)->format('M d, Y') }}</td>
                            <td class="text-muted-cell">{{ $report->location ?? '—' }}</td>
                            <td>
                                @if($report->status === 'pending_review')
                                    <span class="prism-badge prism-badge-orange">Pending Review</span>
                                @elseif($report->status === 'under_review')
                                    <span class="prism-badge prism-badge-blue">Under Review</span>
                                @elseif($report->status === 'converted_to_case')
                                    <span class="prism-badge prism-badge-green">Processed</span>
                                @else
                                    <span class="prism-badge prism-badge-gray">Closed</span>
                                @endif
                            </td>
                            <td class="text-muted-cell">{{ $report->created_at->format('M d, Y') }}</td>
                            <td class="text-center">
                                <a href="{{ route('reporter.reports.show', $report) }}" class="prism-action-link">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="reporter-pagination prism-pagination"><x-pagination :paginator="$reports" /></div>
        @endif
    </div>
</div>
@endsection
