@extends('layouts.admin')

@section('title', 'Admin Dashboard')

@section('css')
<style>
    .admin-dashboard { --dash-blue: #3158d4; --dash-navy: #15245b; }
    .admin-dashboard .dashboard-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:20px; }
    .admin-dashboard .dashboard-date { display:flex; align-items:center; gap:10px; padding:9px 12px; border:1px solid #dbe3f2; border-radius:9px; background:#fff; white-space:nowrap; }
    .admin-dashboard .dashboard-date-icon { display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:8px; background:#edf3ff; color:#3156c7; font-size:14px; }
    .admin-dashboard .dashboard-date-label { color:#64748b; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.05em; }
    .admin-dashboard .dashboard-date-value { margin-left:4px; color:#0f172a; font-size:12px; font-weight:700; }
    .admin-dashboard .sdo-summary-bar { margin-bottom: 18px; }
    .dashboard-grid { display:grid; grid-template-columns:minmax(0,1.55fr) minmax(300px,.8fr); gap:18px; }
    .dashboard-stack { display:grid; gap:18px; align-content:start; }
    .dashboard-panel { background:#fff; border:1px solid #e2e8f0; border-radius:16px; box-shadow:0 6px 20px rgba(30,41,59,.04); overflow:hidden; }
    .dashboard-panel-head { min-height:62px; display:flex; align-items:center; justify-content:space-between; gap:14px; padding:16px 20px; border-bottom:1px solid #edf1f6; }
    .dashboard-panel-title { color:#172033; font-size:16px; font-weight:700; margin:0; }
    .dashboard-panel-sub { color:#94a3b8; font-size:12px; margin-top:3px; }
    .dashboard-link { color:#3158d4; font-size:12px; font-weight:700; text-decoration:none; white-space:nowrap; }
    .dashboard-link:hover { color:#1f3f99; }
    .dashboard-panel-head.dashboard-panel-head--blue { background:#3156C7; border-bottom-color:#3156C7; }
    .dashboard-panel-head--blue .dashboard-panel-title { color:#fff; }
    .dashboard-panel-head--blue .dashboard-panel-sub { color:rgba(255,255,255,.8); }
    .dashboard-panel-head--blue .dashboard-link { color:#fff; }
    .dashboard-panel-head--blue .dashboard-link:hover { color:rgba(255,255,255,.85); }
    .dashboard-table { width:100%; border-collapse:collapse; }
    .dashboard-table th { padding:12px 20px; color:#8492aa; background:#fafbfc; font-size:11px; letter-spacing:.06em; text-transform:uppercase; font-weight:700; }
    .dashboard-table td { padding:14px 20px; border-top:1px solid #f0f3f7; color:#475569; font-size:13px; vertical-align:middle; }
    .dashboard-user { display:flex; align-items:center; gap:10px; min-width:0; }
    .dashboard-avatar { width:36px; height:36px; flex:0 0 36px; display:grid; place-items:center; border-radius:10px; color:#3158d4; background:#eaf0ff; font-size:11px; font-weight:700; }
    .dashboard-user-name { color:#1e293b; font-size:13px; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .dashboard-user-email { color:#94a3b8; font-size:11px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:250px; }
    .status-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 9px; border-radius:999px; font-size:10px; font-weight:700; text-transform:capitalize; }
    .status-pill::before { content:""; width:5px; height:5px; border-radius:50%; background:currentColor; }
    .status-active { color:#14825c; background:#e9f8f2; } .status-pending_setup { color:#a96209; background:#fff4dd; } .status-inactive { color:#64748b; background:#eef2f7; }
    .quick-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding:16px; }
    .quick-action { min-height:82px; display:flex; flex-direction:column; justify-content:space-between; padding:13px; border:1px solid #e7ebf1; border-radius:12px; color:#334155; text-decoration:none; transition:.18s ease; }
    .quick-action:hover { color:#2448b5; border-color:#cbd8ff; background:#f8faff; transform:translateY(-1px); }
    .quick-action i { color:#3158d4; font-size:19px; } .quick-action span { font-size:12px; font-weight:700; }
    .health-card { padding:18px 20px; background:linear-gradient(135deg,#17275f,#263d8d); color:#fff; }
    .health-top { display:flex; align-items:center; justify-content:space-between; }
    .health-label { color:#c7d2fe; font-size:11px; font-weight:600; } .health-value { margin-top:5px; font-size:26px; font-weight:700; }
    .health-ring { width:48px; height:48px; display:grid; place-items:center; border-radius:50%; background:conic-gradient(#7dd3fc calc(var(--health) * 1%),rgba(255,255,255,.15) 0); }
    .health-ring::after { content:""; width:36px; height:36px; border-radius:50%; background:#22377f; }
    .health-meta { display:flex; justify-content:space-between; gap:10px; margin-top:15px; padding-top:13px; border-top:1px solid rgba(255,255,255,.12); color:#cbd5e1; font-size:11px; }
    .roles-list { padding:8px 20px 16px; }
    .role-row { padding:9px 0; }
    .role-meta { display:flex; justify-content:space-between; gap:12px; color:#475569; font-size:12px; font-weight:600; }
    .role-track { height:5px; margin-top:7px; background:#eef2f7; border-radius:99px; overflow:hidden; }
    .role-fill { height:100%; min-width:3px; border-radius:99px; background:linear-gradient(90deg,#3158d4,#6d8cff); }
    .dashboard-empty { padding:28px 20px; text-align:center; color:#94a3b8; font-size:13px; }
    @media(max-width:1100px){.dashboard-grid{grid-template-columns:1fr}.dashboard-stack{grid-template-columns:1fr 1fr}.dashboard-stack .dashboard-panel:last-child{grid-column:1/-1}}
    @media(max-width:700px){.admin-dashboard .dashboard-heading{align-items:flex-start;flex-direction:column}.dashboard-date{display:none!important}.dashboard-stack{grid-template-columns:1fr}.dashboard-stack .dashboard-panel:last-child{grid-column:auto}.dashboard-table th:nth-child(2),.dashboard-table td:nth-child(2){display:none}.dashboard-table th,.dashboard-table td{padding-left:14px;padding-right:14px}.quick-actions{grid-template-columns:1fr 1fr}}
</style>
@endsection

@section('content')
<div class="admin-dashboard">
    <div class="dashboard-heading">
        <div>
            <h1 class="sdo-page-title mb-0">Welcome, Admin</h1>
            <p class="sdo-page-subtitle mb-0">Here’s a clear view of accounts and recent system activity.</p>
        </div>
        <div class="dashboard-date" aria-label="Today's date">
            <span class="dashboard-date-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
            <span><span class="dashboard-date-label">Today</span><span class="dashboard-date-value">{{ now()->format('F j, Y') }}</span></span>
        </div>
    </div>

    <div class="sdo-summary-bar" aria-label="Account summary">
        <div class="sdo-summary-item">
            <div>
                <div class="sdo-stat-value">{{ number_format($stats['total']) }}</div>
                <div class="sdo-stat-label">Total accounts</div>
            </div>
            <i class="bi bi-people-fill sdo-summary-icon" aria-hidden="true"></i>
        </div>
        <div class="sdo-summary-item">
            <div>
                <div class="sdo-stat-value">{{ number_format($stats['active']) }}</div>
                <div class="sdo-stat-label">Active accounts</div>
            </div>
            <i class="bi bi-person-check-fill sdo-summary-icon" aria-hidden="true"></i>
        </div>
        <div class="sdo-summary-item">
            <div>
                <div class="sdo-stat-value">{{ number_format($stats['pending']) }}</div>
                <div class="sdo-stat-label">Awaiting account setup</div>
            </div>
            <i class="bi bi-hourglass-split sdo-summary-icon" aria-hidden="true"></i>
        </div>
        <div class="sdo-summary-item">
            <div>
                <div class="sdo-stat-value">{{ number_format($stats['inactive']) }}</div>
                <div class="sdo-stat-label">Inactive accounts</div>
            </div>
            <i class="bi bi-person-dash-fill sdo-summary-icon" aria-hidden="true"></i>
        </div>
    </div>

    <div class="dashboard-grid">
        <section class="dashboard-panel">
            <div class="dashboard-panel-head dashboard-panel-head--blue"><div><h2 class="dashboard-panel-title">Recently Added Users</h2><div class="dashboard-panel-sub">Latest accounts created in the system</div></div><a class="dashboard-link" href="{{ route('admin.users.index') }}">View all</a></div>
            @if($recentUsers->isEmpty())<div class="dashboard-empty">No user accounts have been added yet.</div>@else
            <div style="overflow-x:auto"><table class="dashboard-table"><thead><tr><th>User</th><th>Role</th><th>Status</th><th>Added</th></tr></thead><tbody>
                @foreach($recentUsers as $user)
                <tr><td><div class="dashboard-user"><div class="dashboard-avatar sdo-avatar-sm sdo-avatar-tone-{{ $user->id % 6 }}">{{ strtoupper(substr($user->first_name,0,1).substr($user->last_name,0,1)) }}</div><div style="min-width:0"><div class="dashboard-user-name">{{ $user->list_name }}</div><div class="dashboard-user-email">{{ $user->email }}</div></div></div></td><td>{{ ucwords(str_replace('_',' ',$user->role)) }}</td><td><span class="status-pill status-{{ $user->status }}">{{ str_replace('_',' ',$user->status) }}</span></td><td title="{{ $user->created_at?->format('M j, Y \a\t g:i A') }}">{{ $user->created_at?->diffForHumans() }}</td></tr>
                @endforeach
            </tbody></table></div>@endif
        </section>

        <div class="dashboard-stack">
            <section class="dashboard-panel"><div class="dashboard-panel-head"><div><h2 class="dashboard-panel-title">Quick Actions</h2><div class="dashboard-panel-sub">Common administration tasks</div></div></div><div class="quick-actions">
                <a class="quick-action" href="{{ route('admin.users.index', ['create' => 1]) }}"><i class="bi bi-person-plus"></i><span>Add new user</span></a>
                <a class="quick-action" href="{{ route('admin.import.index') }}"><i class="bi bi-cloud-arrow-up"></i><span>Import students</span></a>
                <a class="quick-action" href="{{ route('admin.users.index', ['status'=>'pending_setup']) }}"><i class="bi bi-person-gear"></i><span>Pending setup</span></a>
                <a class="quick-action" href="{{ route('admin.risk-weights.index') }}"><i class="bi bi-sliders"></i><span>Risk weights</span></a>
            </div></section>
            <section class="dashboard-panel health-card"><div class="health-top"><div><div class="health-label">Account activation rate</div><div class="health-value">{{ $activePercentage }}%</div></div><div class="health-ring" style="--health:{{ $activePercentage }}" aria-label="{{ $activePercentage }} percent active"></div></div><div class="health-meta"><span><i class="bi bi-person-plus me-1"></i>{{ number_format($newUsersThisMonth) }} new this month</span><span><i class="bi bi-upload me-1"></i>{{ number_format($studentsImportedThisMonth) }} imported</span></div></section>
            <section class="dashboard-panel"><div class="dashboard-panel-head"><div><h2 class="dashboard-panel-title">Users By Role</h2><div class="dashboard-panel-sub">Account distribution</div></div></div><div class="roles-list">@foreach($roleBreakdown->take(5) as $role)<div class="role-row"><div class="role-meta"><span>{{ $role['label'] }}</span><span>{{ number_format($role['count']) }}</span></div><div class="role-track"><div class="role-fill" style="width:{{ $role['percentage'] }}%"></div></div></div>@endforeach</div></section>
        </div>
    </div>

    <section class="dashboard-panel" style="margin-top:18px">
        <div class="dashboard-panel-head"><div><h2 class="dashboard-panel-title">Recent Imports</h2><div class="dashboard-panel-sub">Latest student account batches</div></div><a class="dashboard-link" href="{{ route('admin.import.index') }}">Import history</a></div>
        @if($recentImports->isEmpty())<div class="dashboard-empty">No student imports have been completed yet.</div>@else<div style="overflow-x:auto"><table class="dashboard-table"><thead><tr><th>File</th><th>Imported by</th><th>Created</th><th>Issues</th><th>Date</th></tr></thead><tbody>@foreach($recentImports as $import)<tr><td><a class="dashboard-link" href="{{ route('admin.import.show',$import->import_batch_id) }}">{{ $import->file_name }}</a></td><td>{{ $import->importedBy?->list_name ?? 'System' }}</td><td><span class="status-pill status-active">{{ number_format($import->created_count) }}</span></td><td>{{ number_format($import->skipped_count + $import->error_count) }}</td><td>{{ $import->created_at?->format('M j, Y') }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>
</div>
@endsection
