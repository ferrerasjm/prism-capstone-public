@extends('layouts.reporting-user')

@section('title', 'Reporting User Dashboard')

@section('css')
<style>
    .reporter-dashboard { display: grid; gap: 20px; }
    .reporter-dashboard > * { margin-top: 0 !important; margin-bottom: 0 !important; }
    .reporter-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
    .reporter-date { display: flex; align-items: center; gap: 10px; flex-shrink: 0; padding: 9px 12px; border: 1px solid #dbe3f2; border-radius: 9px; background: #fff; white-space: nowrap; }
    .reporter-date-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 8px; background: #edf3ff; color: #3156c7; }
    .reporter-date-label { color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .reporter-date-value { margin-left: 4px; color: #0f172a; font-size: 12px; font-weight: 700; }
    .reporter-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .reporter-stat { position: relative; min-height: 108px; padding: 17px 18px; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .reporter-stat-icon { position: absolute; top: 16px; right: 16px; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; color: var(--stat-color); background: var(--stat-soft); }
    .reporter-stat-value { color: #0f172a; font-size: 1.55rem; font-weight: 750; line-height: 1; }
    .reporter-stat-label { margin-top: 9px; color: #64748b; font-size: .78rem; font-weight: 600; }
    .reporter-stat-note { margin-top: 3px; color: #94a3b8; font-size: .68rem; }
    .reporter-stat.blue { --stat-color: #3156c7; --stat-soft: #edf3ff; }
    .reporter-stat.amber { --stat-color: #b45309; --stat-soft: #fff7e6; }
    .reporter-stat.violet { --stat-color: #7c3aed; --stat-soft: #f3e8ff; }
    .reporter-stat.green { --stat-color: #15803d; --stat-soft: #ecfdf5; }
    .reporter-panel { overflow: hidden; min-width: 0; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .reporter-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 62px; padding: 14px 18px; border-bottom: 1px solid #edf1f6; }
    .reporter-panel-title { margin: 0; color: #172033; font-size: .95rem; font-weight: 700; }
    .reporter-panel-sub { margin-top: 3px; color: #94a3b8; font-size: .72rem; }
    .reporter-panel-link { color: #3156c7; font-size: .74rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .reporter-panel-head .prism-btn { min-height: 36px; padding: 7px 12px; font-size: .75rem; }
    .reporter-empty { display: grid; min-height: 180px; place-items: center; padding: 24px; color: #64748b; font-size: .82rem; text-align: center; }
    .reporter-recent-table { table-layout: fixed; width: 100%; }
    .reporter-recent-table th { height: 42px; padding-top: 0 !important; padding-bottom: 0 !important; vertical-align: middle; }
    .reporter-recent-table td { height: 60px; vertical-align: middle; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .reporter-recent-table th:nth-child(1) { width: 18%; }
    .reporter-recent-table th:nth-child(2) { width: 16%; }
    .reporter-recent-table th:nth-child(3) { width: 24%; }
    .reporter-recent-table th:nth-child(4) { width: 15%; }
    .reporter-recent-table th:nth-child(5) { width: 15%; }
    .reporter-recent-table th:nth-child(6) { width: 12%; text-align: center; }
    .reporter-recent-table th:last-child, .reporter-recent-table td:last-child { padding-left: 18px !important; padding-right: 18px !important; }
    .reporter-recent-table td:last-child { text-align: center; white-space: nowrap; }
    @media(max-width:767.98px){
        .reporter-recent-table { table-layout: auto; min-width: 640px; }
        .reporter-recent-table td { white-space: normal; }
    }
    @media(max-width:1050px){.reporter-stats{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:575.98px){.reporter-heading{align-items:flex-start;flex-direction:column;gap:14px}.reporter-stats{grid-template-columns:1fr}.reporter-panel-head{align-items:flex-start;flex-direction:column}.reporter-panel-head .prism-btn{justify-content:center;width:100%}}
</style>
@endsection

@section('content')
@php
    $statusLabels = [
        'pending_review' => ['label' => 'Pending Review', 'class' => 'prism-badge-orange'],
        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-blue'],
        'converted_to_case' => ['label' => 'Processed', 'class' => 'prism-badge-green'],
        'dismissed' => ['label' => 'Closed', 'class' => 'prism-badge-gray'],
    ];
@endphp
<div class="prism-content reporter-dashboard">
    <header class="reporter-heading">
        <div>
            <h1 class="prism-page-title">Welcome, {{ auth()->user()->first_name }}</h1>
            <p class="prism-page-sub">Submit incident reports and follow their progress with the Student Discipline Office.</p>
        </div>
        <div class="reporter-date" aria-label="Today's date">
            <span class="reporter-date-icon"><i class="bi bi-calendar3"></i></span>
            <span><span class="reporter-date-label">Today</span><span class="reporter-date-value">{{ now()->format('F j, Y') }}</span></span>
        </div>
    </header>

    @include('partials.dashboard-announcements')

    <section class="reporter-stats" aria-label="Report summary">
        <div class="reporter-stat blue"><span class="reporter-stat-icon"><i class="bi bi-files"></i></span><div class="reporter-stat-value">{{ $stats['total'] }}</div><div class="reporter-stat-label">Total complaints</div><div class="reporter-stat-note">All complaints you submitted</div></div>
        <div class="reporter-stat amber"><span class="reporter-stat-icon"><i class="bi bi-hourglass-split"></i></span><div class="reporter-stat-value">{{ $stats['pending'] }}</div><div class="reporter-stat-label">Pending review</div><div class="reporter-stat-note">Waiting for initial assessment</div></div>
        <div class="reporter-stat violet"><span class="reporter-stat-icon"><i class="bi bi-search"></i></span><div class="reporter-stat-value">{{ $stats['under_review'] }}</div><div class="reporter-stat-label">Under review</div><div class="reporter-stat-note">Currently being assessed</div></div>
        <div class="reporter-stat green"><span class="reporter-stat-icon"><i class="bi bi-check2-circle"></i></span><div class="reporter-stat-value">{{ $stats['processed'] }}</div><div class="reporter-stat-label">Processed</div><div class="reporter-stat-note">Complaints moved forward by SDO</div></div>
    </section>

    <section class="reporter-panel">
        <div class="reporter-panel-head">
            <div><h2 class="reporter-panel-title">Recently Processed</h2><div class="reporter-panel-sub">Your latest complaints moved forward by the SDO</div></div>
            <a href="{{ route('reporter.reports.index') }}" class="reporter-panel-link">View All Complaints</a>
        </div>
        @if($recentlyProcessed->isEmpty())
            <div class="reporter-empty"><span><i class="bi bi-inbox d-block mb-2" style="font-size:1.25rem;color:#94a3b8;"></i>No complaints have been processed yet.</span></div>
        @else
            <div class="table-responsive">
                <table class="prism-table reporter-recent-table">
                    <thead><tr><th>Report No.</th><th>Incident Date</th><th>Location</th><th>Status</th><th>Submitted</th><th class="text-center"><span class="visually-hidden">Action</span></th></tr></thead>
                    <tbody>
                        @foreach($recentlyProcessed as $report)
                            @php
                                $state = $statusLabels[$report->status] ?? ['label' => ucwords(str_replace('_', ' ', $report->status)), 'class' => 'prism-badge-gray'];
                            @endphp
                            <tr>
                                <td><div class="prism-cell-primary prism-ref-number">{{ $report->report_number }}</div></td>
                                <td class="text-muted-cell">{{ $report->date_of_incident->format('M d, Y') }}</td>
                                <td class="text-muted-cell">{{ $report->location ?: 'Not provided' }}</td>
                                <td><span class="prism-badge {{ $state['class'] }}">{{ $state['label'] }}</span></td>
                                <td class="text-muted-cell">{{ $report->created_at->format('M d, Y') }}</td>
                                <td class="text-center"><a href="{{ route('reporter.reports.show', $report) }}" class="prism-action-link">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
