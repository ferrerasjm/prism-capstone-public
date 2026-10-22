@extends('layouts.coordinator')
@section('title', 'Audit Log')
@section('css')
<style>
    .audit-log-panel {
        overflow: hidden;
        border: 1px solid #dfe5ef;
        border-radius: 12px;
        background: #fff;
    }
    .audit-log-panel .prism-filter-bar {
        margin: 0;
        border: 0;
        border-bottom: 1px solid #e5e9f2;
        border-radius: 0;
        box-shadow: none;
    }
    .audit-log-panel > .prism-card {
        border: 0;
        border-radius: 0;
    }
    .audit-log-panel .prism-card > .prism-card-header:first-child {
        padding: 18px 22px;
        background: #fbfcfe;
        border-radius: 0;
    }
    .audit-log-panel .prism-card > .prism-card-header.audit-log-header--blue:first-child {
        background: #3156C7;
        border-bottom-color: #3156C7;
    }
    .audit-log-header--blue .prism-card-title {
        color: #FFFFFF;
    }
    .audit-log-panel .prism-audit-list {
        gap: 14px;
        padding: 18px 22px 22px;
    }
    .audit-log-panel .prism-audit-item {
        grid-template-columns: minmax(0, 60%) minmax(280px, 25%);
        justify-content: space-between;
        gap: 0;
        padding: 0;
        align-items: stretch;
        overflow: hidden;
        border-color: #e3e9f2;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
        transition: box-shadow .15s ease, border-color .15s ease;
    }
    .audit-log-panel .prism-audit-item:hover {
        box-shadow: 0 6px 16px rgba(15, 23, 42, .08);
        border-color: #c7d2fe;
    }
    .audit-log-panel .prism-audit-main {
        padding: 18px 20px;
    }
    .audit-log-panel .prism-audit-topline {
        gap: 12px;
    }
    .audit-log-panel .prism-audit-title {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
        font-size: .94rem;
        line-height: 1.5;
    }
    .audit-log-panel .prism-audit-summary {
        flex: 0 1 auto;
        min-width: 0;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
        color: #0f172a;
        font-weight: 600;
    }
    .audit-log-panel .prism-audit-title .prism-badge {
        flex: 0 0 auto;
    }
    .audit-action-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        border-radius: 9px;
        background: #eff6ff;
        color: #2563eb;
        font-size: .9rem;
    }
    .audit-action-icon.is-muted { background: #f1f5f9; color: #64748b; }
    .audit-action-icon.is-danger { background: #fef2f2; color: #dc2626; }
    .audit-action-icon.is-warning { background: #fff7ed; color: #ea580c; }
    .audit-log-panel .prism-audit-title {
        font-size: .94rem;
    }
    .audit-log-panel .prism-audit-meta {
        align-items: center;
        margin-top: 5px;
    }
    .audit-meta-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .audit-meta-item + .audit-meta-item::before {
        content: '';
        width: 3px;
        height: 3px;
        margin-right: 5px;
        border-radius: 50%;
        background: #cbd5e1;
    }
    .audit-log-panel .prism-audit-empty {
        margin: 12px 0 0 46px;
        color: #94a3b8;
        font-size: .76rem;
    }
    .audit-log-panel .prism-audit-details,
    .audit-log-panel .prism-audit-reason {
        margin-left: 46px;
    }
    .audit-snapshot-card {
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fff;
    }
    .audit-snapshot-card .prism-audit-snapshot-title {
        margin: 0;
        padding: 8px 10px;
        border-bottom: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .audit-snapshot-list { margin: 0; }
    .audit-snapshot-row {
        display: grid;
        grid-template-columns: minmax(100px, .8fr) minmax(0, 1.4fr);
        gap: 10px;
        padding: 8px 10px;
        border-top: 1px solid #f1f5f9;
        font-size: .76rem;
        line-height: 1.4;
    }
    .audit-snapshot-row:first-child { border-top: 0; }
    .audit-snapshot-row dt { color: #64748b; font-weight: 600; }
    .audit-snapshot-row dd { margin: 0; color: #1e293b; overflow-wrap: anywhere; }
    .audit-snapshot-none { padding: 10px; color: #94a3b8; font-size: .76rem; }
    .audit-log-panel .prism-audit-actor {
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        padding: 18px 20px;
        border-left: 1px solid #e8edf4;
        background: #fafbfd;
    }
    .audit-actor-label {
        margin-bottom: 8px;
        color: #94a3b8;
        font-size: .64rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
    }
    .audit-actor-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .audit-actor-avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        border-radius: 50%;
        background: #e0e7ff;
        color: #3730a3;
        font-size: .72rem;
        font-weight: 700;
    }
    .audit-role { margin-top: 9px; }
    .audit-role-badge {
        display: inline-block;
        padding: 3px 10px;
        border: 1px solid transparent;
        border-radius: 20px;
        font-size: .68rem;
        font-weight: 700;
    }
    .audit-role-badge.is-administrative { background: #fef3c7; color: #92400e; border-color: #fcd34d; }
    .audit-role-badge.is-operational { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
    .audit-role-badge.is-support { background: #dbeafe; color: #1e40af; border-color: #93c5fd; }
    .audit-log-panel summary.prism-action-link {
        list-style: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: .8rem;
    }
    .audit-log-panel summary.prism-action-link::-webkit-details-marker {
        display: none;
    }
    .audit-log-panel summary.prism-action-link i {
        font-size: .62rem;
        line-height: 1;
        transition: transform .15s ease;
    }
    .audit-log-panel .prism-audit-details[open] summary.prism-action-link i {
        transform: rotate(90deg);
    }
    /* Wrapper is padding and rule only - the pagination component lays out its
       own row (summary left, page strip + size picker right). Making this a flex
       parent and stretching the nav to 100% is what used to push the size picker
       onto a second line. */
    .audit-pagination {
        min-height: 58px;
        padding: 11px 22px;
        border-top: 1px solid #e8edf4;
        background: #fbfcfe;
    }
    .audit-pagination nav { margin-left: 0; }
    .audit-pagination .pagination { margin: 0; }
    .audit-pagination .prism-pagination-controls { flex-wrap: nowrap; }
    /* Page buttons are styled once, in shared.css. */
    .audit-filter-actions {
        display: flex;
        align-items: end;
        gap: 8px;
        flex-wrap: nowrap;
        white-space: nowrap;
    }

    @media (min-width: 1200px) {
        .audit-filter-form {
            display: grid;
            grid-template-columns: minmax(210px, 1.35fr) repeat(2, minmax(125px, .85fr)) repeat(2, minmax(135px, .9fr)) max-content;
            align-items: end;
            gap: 8px;
            margin: 0;
        }
        .audit-filter-form > div {
            width: auto;
            padding: 0;
        }
        .audit-filter-actions { justify-content: flex-start !important; }
    }
    @media (max-width: 992px) {
        .audit-log-panel .prism-audit-item { grid-template-columns: 1fr; }
        .audit-log-panel .prism-audit-actor {
            border-top: 1px solid #e8edf4;
            border-left: 0;
        }
    }
    @media (max-width: 576px) {
        .audit-log-panel .prism-card-header,
        .audit-log-panel .prism-audit-list { padding-left: 14px; padding-right: 14px; }
        .audit-pagination { padding: 14px; }
        .audit-pagination nav { margin-left: 0; }
        /* Too narrow to keep the strip and the size picker side by side. */
        .audit-pagination .prism-pagination-controls { flex-wrap: wrap; }
        .audit-pagination .pagination { width: 100%; justify-content: flex-end; }
        .audit-log-panel .prism-audit-details,
        .audit-log-panel .prism-audit-reason,
        .audit-log-panel .prism-audit-empty { margin-left: 0; }
        .audit-snapshot-row { grid-template-columns: 1fr; }
    }
</style>
@endsection
@section('content')
@php
    $activeFilters = collect($filters)->filter(fn ($value) => filled($value))->count();
    $formatAuditValue = [\App\Models\AuditLog::class, 'formatValue'];
    $actionBadges = [
        'create' => 'prism-badge-green',
        'update' => 'prism-badge-blue',
        'delete' => 'prism-badge-red',
        'void' => 'prism-badge-red',
        'override' => 'prism-badge-orange',
        'dismiss' => 'prism-badge-gray',
        'login' => 'prism-badge-blue',
        'logout' => 'prism-badge-gray',
    ];
@endphp

<div class="prism-content">
    <div class="prism-page-header">
        <h1 class="prism-page-title">System Audit Log</h1>
        <p class="prism-page-sub">Read-only history of recorded coordinator and system actions</p>
    </div>

    <div class="audit-log-panel">
    <div class="prism-card">
        <div class="prism-card-header audit-log-header--blue">
            <h2 class="prism-card-title">
                Recorded Actions
            </h2>
        </div>

        <div class="prism-filter-bar">
            <form method="GET" action="{{ route('coordinator.audit-logs.index') }}" class="row g-2 align-items-end audit-filter-form" data-auto-submit>
                <div class="col-12 col-xl-4">
                    <label class="prism-label">Search</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" aria-hidden="true"
                           style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                        <input class="prism-input" name="search" style="padding-left:30px;"
                               value="{{ $filters['search'] ?? '' }}"
                               placeholder="Actor, module, or record ID">
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="auditModule" class="prism-label">Module</label>
                    <select id="auditModule" class="prism-select" name="module">
                        <option value="">All modules</option>
                        @foreach($modules as $module)
                            <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>
                                {{ ucwords(str_replace('_', ' ', $module)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="auditAction" class="prism-label">Action</label>
                    <select id="auditAction" class="prism-select" name="action">
                        <option value="">All actions</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>
                                {{ ucwords(str_replace('_', ' ', $action)) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="auditFrom" class="prism-label">From</label>
                    <input id="auditFrom" type="date" class="prism-input" name="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div class="col-6 col-md-4 col-xl-2">
                    <label for="auditTo" class="prism-label">To</label>
                    <input id="auditTo" type="date" class="prism-input" name="to" value="{{ $filters['to'] ?? '' }}">
                </div>
            </form>
        </div>

        @if($logs->isEmpty())
            <div class="prism-table-empty">
                No audit records match the selected filters.
            </div>
        @else
            <div class="prism-audit-list">
                @foreach($logs as $log)
                    @php
                        $moduleLabel = ucwords(str_replace('_', ' ', (string) $log->module));
                        $actionLabel = ucwords(str_replace('_', ' ', (string) $log->action));
                        $actionClass = $actionBadges[$log->action] ?? 'prism-badge-gray';
                        $oldValues = $log->safe_old_values;
                        $newValues = $log->safe_new_values;
                        $summary = $log->summary($oldValues, $newValues);
                        $hasSnapshot = ! is_null($oldValues) || ! is_null($newValues);
                        $actionIcon = match ($log->action) {
                            'login' => 'bi-box-arrow-in-right',
                            'logout' => 'bi-box-arrow-right',
                            'create' => 'bi-plus-lg',
                            'update' => 'bi-pencil',
                            'delete', 'void' => 'bi-trash3',
                            'override' => 'bi-exclamation-triangle',
                            default => 'bi-activity',
                        };
                        $actionIconClass = match ($log->action) {
                            'delete', 'void' => 'is-danger',
                            'override' => 'is-warning',
                            'logout', 'dismiss' => 'is-muted',
                            default => '',
                        };
                        $roleBadgeClass = match ($log->user?->role) {
                            'discipline_coordinator', 'super_admin', 'admin' => 'is-administrative',
                            'discipline_officer', 'us_supervisor' => 'is-operational',
                            default => 'is-support',
                        };
                    @endphp

                    <article class="prism-audit-item">
                        <div class="prism-audit-main">
                            <div class="prism-audit-topline">
                                <span class="audit-action-icon {{ $actionIconClass }}" aria-hidden="true"><i class="bi {{ $actionIcon }}"></i></span>
                                <div>
                                    <div class="prism-audit-title">
                                        <span class="prism-audit-summary">{{ $summary }}</span>
                                        <span class="prism-badge {{ $actionClass }}">{{ $actionLabel }}</span>
                                    </div>
                                    <div class="prism-audit-meta">
                                        <span class="audit-meta-item"><i class="bi bi-clock"></i>{{ $log->created_at?->format('M d, Y \a\t g:i A') ?? '-' }}</span>
                                        @if(! is_null($log->record_id))
                                            <span class="audit-meta-item"><i class="bi bi-hash"></i>Record {{ $log->record_id }}</span>
                                        @endif
                                        <span class="audit-meta-item"><i class="bi bi-folder2"></i>{{ $moduleLabel ?: 'Unknown Module' }}</span>
                                        @if($log->ip_address)
                                            <span class="audit-meta-item"><i class="bi bi-globe2"></i>{{ $log->ip_address }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if($log->reason)
                                <div class="prism-audit-reason">
                                    <strong>Reason:</strong> {{ $log->reason }}
                                </div>
                            @endif

                            @if($hasSnapshot)
                                <details class="prism-audit-details">
                                    <summary class="prism-action-link">
                                        <i class="bi bi-chevron-right"></i> View details
                                    </summary>
                                    <div class="prism-audit-snapshot">
                                        <div class="audit-snapshot-card">
                                            <div class="prism-audit-snapshot-title">Before</div>
                                            @if(empty($oldValues))
                                                <div class="audit-snapshot-none">No previous values</div>
                                            @else
                                                <dl class="audit-snapshot-list">
                                                    @foreach($oldValues as $field => $value)
                                                        <div class="audit-snapshot-row">
                                                            <dt>{{ \App\Models\AuditLog::formatField((string) $field, $newValues['role'] ?? $oldValues['role'] ?? null) }}</dt>
                                                            <dd>{{ $formatAuditValue($value) }}</dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            @endif
                                        </div>
                                        <div class="audit-snapshot-card">
                                            <div class="prism-audit-snapshot-title">After</div>
                                            @if(empty($newValues))
                                                <div class="audit-snapshot-none">No new values</div>
                                            @else
                                                <dl class="audit-snapshot-list">
                                                    @foreach($newValues as $field => $value)
                                                        <div class="audit-snapshot-row">
                                                            <dt>{{ \App\Models\AuditLog::formatField((string) $field, $newValues['role'] ?? $oldValues['role'] ?? null) }}</dt>
                                                            <dd>{{ $formatAuditValue($value) }}</dd>
                                                        </div>
                                                    @endforeach
                                                </dl>
                                            @endif
                                        </div>
                                    </div>
                                </details>
                            @else
                                <div class="prism-audit-empty">No value snapshot recorded.</div>
                            @endif
                        </div>

                        <aside class="prism-audit-actor">
                            <div class="audit-actor-label">Performed by</div>
                            <div class="audit-actor-row">
                                <span class="audit-actor-avatar" aria-hidden="true">
                                    {{ $log->user ? strtoupper(substr($log->user->first_name, 0, 1).substr($log->user->last_name, 0, 1)) : 'SY' }}
                                </span>
                                <div class="min-w-0">
                                    <div class="prism-audit-actor-name">{{ $log->user?->list_name ?? 'System' }}</div>
                                    <div class="prism-cell-sub">{{ $log->user?->email ?? 'Automated action' }}</div>
                                </div>
                            </div>
                            @if($log->user?->role)
                                <div class="audit-role">
                                    <span class="audit-role-badge {{ $roleBadgeClass }}">
                                        {{ ucwords(str_replace('_', ' ', $log->user->role)) }}
                                    </span>
                                </div>
                            @endif
                        </aside>
                    </article>
                @endforeach
            </div>

            <div class="audit-pagination">
                {{-- The component renders the summary with or without a page strip. --}}
                <x-pagination :paginator="$logs" :on-each-side="1" />
            </div>
        @endif
    </div>
    </div>
</div>
@endsection
