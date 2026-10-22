@extends('layouts.guidance')

@section('title', 'Guidance Dashboard')

@section('css')
<style>
    .guidance-dashboard { display: grid; gap: 20px; }
    .prism-content.guidance-dashboard > * + * { margin-top: 0; }
    .guidance-dashboard .dashboard-announcements { margin-bottom: 0; }
    .guidance-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
    .guidance-date { display: flex; align-items: center; gap: 10px; flex-shrink: 0; padding: 9px 12px; border: 1px solid #dbe3f2; border-radius: 9px; background: #fff; white-space: nowrap; }
    .guidance-date-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 8px; background: #edf3ff; color: #3156c7; }
    .guidance-date-label { color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .guidance-date-value { margin-left: 4px; color: #0f172a; font-size: 12px; font-weight: 700; }
    .guidance-stats { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 12px; }
    .guidance-stat { position: relative; min-height: 104px; padding: 17px 18px; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .guidance-stat-icon { position: absolute; top: 16px; right: 16px; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; color: var(--stat-color); background: var(--stat-soft); }
    .guidance-stat-value { color: #0f172a; font-size: 1.55rem; font-weight: 750; line-height: 1; }
    .guidance-stat-label { margin-top: 9px; color: #64748b; font-size: .78rem; font-weight: 600; }
    .guidance-stat-note { margin-top: 3px; color: #94a3b8; font-size: .68rem; }
    .guidance-stat.blue { --stat-color:#3156c7; --stat-soft:#edf3ff; } .guidance-stat.amber { --stat-color:#c2410c; --stat-soft:#fff1e8; }
    .guidance-stat.violet { --stat-color:#7c3aed; --stat-soft:#f3e8ff; } .guidance-stat.green { --stat-color:#15803d; --stat-soft:#ecfdf5; }
    .guidance-grid { display: grid; grid-template-columns: minmax(0,1.45fr) minmax(300px,.7fr); gap: 16px; }
    .guidance-grid--single { grid-template-columns: 1fr; }
    .guidance-panel { overflow: hidden; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .guidance-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 62px; padding: 14px 18px; border-bottom: 1px solid #edf1f6; }
    .guidance-panel-title { margin: 0; color: #172033; font-size: .95rem; font-weight: 700; }
    .guidance-panel-sub { margin-top: 3px; color: #94a3b8; font-size: .72rem; }
    .guidance-panel-head.guidance-panel-head--blue { background: #3156C7; border-bottom-color: #3156C7; }
    .guidance-panel-head--blue .guidance-panel-title { color: #fff; }
    .guidance-panel-head--blue .guidance-panel-sub { color: rgba(255,255,255,.8); }
    .guidance-link { color: #3156c7; font-size: .74rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .guidance-table-wrap { overflow-x: auto; overflow-y: auto; max-height: 380px; }
    .guidance-table-wrap thead th { position: sticky; top: 0; z-index: 1; }
    .guidance-table { width: 100%; border-collapse: collapse; }
    .guidance-table th { padding: 10px 18px; background: #fafbfc; color: #8492aa; font-size: .65rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .guidance-table td { padding: 12px 18px; border-top: 1px solid #f0f3f7; color: #475569; font-size: .75rem; vertical-align: middle; }
    .guidance-student { color: #1e293b; font-size: .88rem; font-weight: 700; } .guidance-meta { margin-top: 2px; color: #94a3b8; font-size: .66rem; }
    .guidance-actions { display: grid; gap: 10px; padding: 14px; }
    .guidance-action { display: flex; align-items: center; gap: 12px; min-height: 66px; padding: 12px; border: 1px solid #e5eaf2; border-radius: 10px; color: inherit; background: #fbfcfe; text-decoration: none; }
    .guidance-action:hover { border-color: #cbd8f4; background: #f8faff; }
    .guidance-action-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 36px; border-radius: 9px; color: #3156c7; background: #eaf0ff; }
    .guidance-action-title { color: #1e293b; font-size: .78rem; font-weight: 700; } .guidance-action-sub { margin-top: 2px; color: #94a3b8; font-size: .66rem; }
    .guidance-empty { padding: 30px 18px; color: #94a3b8; font-size: .78rem; text-align: center; }
    @media(max-width:1050px){.guidance-stats{grid-template-columns:repeat(2,1fr)}.guidance-grid{grid-template-columns:1fr}}
    @media(max-width:575.98px){.guidance-heading{align-items:flex-start;flex-direction:column;gap:14px}.guidance-date{width:auto}.guidance-stats{grid-template-columns:1fr}}
</style>
@endsection

@section('content')
<div class="prism-content guidance-dashboard">
    <header class="guidance-heading">
        <div><h1 class="prism-page-title">Welcome, {{ auth()->user()->first_name }}</h1><p class="prism-page-sub">Track assigned counseling referrals and keep student support actions moving.</p></div>
        <div class="guidance-date" aria-label="Today's date"><span class="guidance-date-icon"><i class="bi bi-calendar3"></i></span><span><span class="guidance-date-label">Today</span><span class="guidance-date-value">{{ now()->format('F j, Y') }}</span></span></div>
    </header>

    @include('partials.dashboard-announcements')

    <section class="guidance-stats" aria-label="Referral summary">
        <div class="guidance-stat blue"><span class="guidance-stat-icon"><i class="bi bi-person-lines-fill"></i></span><div class="guidance-stat-value">{{ $stats['total'] }}</div><div class="guidance-stat-label">Total referrals</div><div class="guidance-stat-note">All referrals assigned to you</div></div>
        <div class="guidance-stat amber"><span class="guidance-stat-icon"><i class="bi bi-hourglass-split"></i></span><div class="guidance-stat-value">{{ $stats['pending'] }}</div><div class="guidance-stat-label">Pending action</div><div class="guidance-stat-note">Referrals still in progress</div></div>
        <div class="guidance-stat violet"><span class="guidance-stat-icon"><i class="bi bi-bell"></i></span><div class="guidance-stat-value">{{ $stats['unacknowledged'] }}</div><div class="guidance-stat-label">Need acknowledgment</div><div class="guidance-stat-note">New referrals awaiting receipt</div></div>
        <div class="guidance-stat green"><span class="guidance-stat-icon"><i class="bi bi-check-circle"></i></span><div class="guidance-stat-value">{{ $stats['completed_month'] }}</div><div class="guidance-stat-label">Completed this month</div><div class="guidance-stat-note">Fulfilled counseling requirements</div></div>
    </section>

    <div class="guidance-grid guidance-grid--single">
        <section class="guidance-panel">
            <div class="guidance-panel-head"><div><h2 class="guidance-panel-title">Recent Referrals</h2><div class="guidance-panel-sub">Pending referrals are shown first</div></div><a class="guidance-link" href="{{ route('guidance.referrals.index') }}">View All</a></div>
            @if($recentReferrals->isEmpty())<div class="guidance-empty">No referrals assigned to you.</div>@else
            <div class="guidance-table-wrap"><table class="guidance-table"><thead><tr><th>Student</th><th>Program</th><th>Year</th><th>Date Referred</th><th>Student Response</th><th>Status</th><th class="text-center">Action</th></tr></thead><tbody>
                @foreach($recentReferrals as $referral)
                    @php $statusClass = match($referral->status){'completed'=>'prism-badge-green','incomplete'=>'prism-badge-red',default=>'prism-badge-orange'}; @endphp
                    <tr><td><div class="guidance-student">{{ $referral->student?->list_name ?? 'Unknown student' }}</div><div class="guidance-meta prism-ref-number">{{ $referral->student?->studentProfile?->student_number ?? 'No student number' }}</div></td><td>{{ $referral->student?->studentProfile?->program ?? '—' }}</td><td>{{ $referral->student?->studentProfile?->year_level ?? '—' }}</td><td>{{ $referral->referred_at?->format('M j, Y') ?? '—' }}</td><td><span class="prism-badge {{ $referral->acknowledged_at ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $referral->acknowledged_at ? 'Acknowledged' : 'Awaiting Response' }}</span></td><td><span class="prism-badge {{ $statusClass }}">{{ ucfirst($referral->status) }}</span></td><td class="text-center"><a href="{{ route('guidance.referrals.show', $referral) }}" class="prism-action-link">View</a></td></tr>
                @endforeach
            </tbody></table></div>@endif
        </section>
    </div>
</div>
@endsection
