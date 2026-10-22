@extends('layouts.student')
@section('title', 'My Complaints')
@section('css')
<style>
    .student-complaint-panel { overflow: hidden; border-radius: 12px; }
    .student-complaint-panel .prism-card-header { min-height: 62px; padding: 14px 18px; }
    .student-complaint-table { table-layout: fixed; }
    .student-complaint-table th { height: 42px; padding-top: 0 !important; padding-bottom: 0 !important; vertical-align: middle; }
    .student-complaint-table td { height: 66px; vertical-align: middle; }
    .student-complaint-table th:nth-child(1) { width: 23%; }
    .student-complaint-table th:nth-child(2) { width: 23%; }
    .student-complaint-table th:nth-child(3) { width: 23%; }
    .student-complaint-table th:nth-child(4) { width: 19%; }
    .student-complaint-table th:nth-child(5) { width: 12%; text-align: center; }
    .student-complaint-table th:last-child,
    .student-complaint-table td:last-child { padding-left: 18px !important; padding-right: 18px !important; }
    .student-complaint-table td:last-child { text-align: center; white-space: nowrap; }
    .student-complaint-action { color: #3156c7; font-size: .78rem; }
    .student-complaint-action:hover { color: #3156c7; text-decoration: underline; }
    @media(max-width:767.98px){
        .student-complaint-table{min-width:720px}
        .prism-page-header .student-complaint-submit-btn{flex:0 1 auto;width:auto;align-self:flex-start;}
    }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header d-flex align-items-start justify-content-between">
        <div><h1 class="prism-page-title">My Complaints</h1><p class="prism-page-sub">Complaints you have submitted to the SDO</p></div>
        <a href="{{ route('student.complaints.create') }}" class="prism-btn prism-btn-primary student-complaint-submit-btn"><i class="bi bi-plus-lg"></i> Submit Complaint</a>
    </div>
    <div class="prism-card student-complaint-panel">
        <div class="prism-card-header prism-card-header--blue"><h2 class="prism-card-title">Submitted Complaints</h2></div>
        @if($complaints->isEmpty())
            <div class="prism-table-empty">You have not submitted any complaints.</div>
        @else
            <div class="table-responsive">
                <table class="prism-table student-complaint-table">
                    <thead><tr><th>Report No.</th><th>Incident Date</th><th>Status</th><th>Submitted</th><th class="text-center"><span class="visually-hidden">Action</span></th></tr></thead>
                    <tbody>
                        @foreach($complaints as $c)
                        <tr>
                            <td><span class="prism-ref-number">{{ $c->report_number }}</span></td>
                            <td class="text-muted-cell">{{ \Carbon\Carbon::parse($c->date_of_incident)->format('M d, Y') }}</td>
                            <td>
                                @if($c->status==='pending_review') <span class="prism-badge prism-badge-orange">Pending Review</span>
                                @elseif($c->status==='under_review') <span class="prism-badge prism-badge-blue">Under Review</span>
                                @elseif($c->status==='converted_to_case') <span class="prism-badge prism-badge-green">Processed</span>
                                @else <span class="prism-badge prism-badge-gray">Closed</span>
                                @endif
                            </td>
                            <td class="text-muted-cell">{{ $c->created_at->format('M d, Y') }}</td>
                            <td>
                                <a href="{{ route('student.complaints.show', $c) }}" class="prism-action-link student-complaint-action">View</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="student-pagination"><x-pagination :paginator="$complaints" /></div>
        @endif
    </div>
</div>
@endsection
