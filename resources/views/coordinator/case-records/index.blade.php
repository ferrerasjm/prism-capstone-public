@extends('layouts.coordinator')
@section('title', 'Cases')

@section('css')
<style>
    .case-workspace {
        display: grid;
        gap: 16px;
        overflow-x: hidden;
    }

    .case-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .case-queue {
        background: #FFFFFF;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        overflow: hidden;
    }

    .case-queue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #3156C7;
        background: #3156C7;
    }

    .case-queue-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .case-filter-toggle {
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }

    .case-filter-toggle:hover {
        color: #FFFFFF;
    }

    .case-filters {
        padding: 14px 16px;
        border-bottom: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    .case-filters .prism-label {
        margin-bottom: 4px;
        font-size: 0.74rem;
        color: #475569;
    }

    .case-filters .prism-input,
    .case-filters .prism-select {
        min-height: 38px;
        font-size: 0.84rem;
    }

    .case-table-wrap {
        contain: layout paint;
    }

    .case-table-wrap .prism-table thead th {
        background: #F8FAFC;
    }

    .case-table-wrap .prism-table tbody td {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
    }

    /* Matches the officer case list (officer/cases/_results): the case number is a
       navy link with the status pill stacked beneath it, and the type badge folds
       into the offense cell so the title has room to sit on one line. */
    .case-number-link { display: inline-block; font-size: 0.83rem; }
    .case-status-pills { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
    .case-offense-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .case-table-wrap .prism-table td:nth-child(4) { min-width: 260px; }
    .case-table-wrap .prism-table th:nth-child(3),
    .case-table-wrap .prism-table td:nth-child(3),
    .case-table-wrap .prism-table th:nth-child(5),
    .case-table-wrap .prism-table td:nth-child(5) { white-space: nowrap; }

    .case-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    @media (max-width: 576px) {
        .case-topbar {
            display: grid;
        }

        .case-queue-head {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
@php
    $statusMap = [
        'open' => ['Open', 'prism-badge-blue'],
        'pending_nte' => ['Pending NTE', 'prism-badge-orange'],
        'awaiting_explanation' => ['Awaiting Explanation', 'prism-badge-violet'],
        'under_review' => ['Under Review', 'prism-badge-teal'],
        'pending_upcc' => ['Pending UPCC', 'prism-badge-navy'],
        'pending_resolution' => ['Pending Resolution', 'prism-badge-amber'],
        'resolved' => ['Resolved', 'prism-badge-green'],
        'dismissed' => ['Dismissed', 'prism-badge-gray'],
        'voided' => ['Voided', 'prism-badge-red'],
    ];
@endphp

<div class="prism-content case-workspace">

    <div class="case-topbar">
        <div>
            <h1 class="prism-page-title">Cases</h1>
            <p class="prism-page-sub">Read-only overview of all disciplinary cases</p>
        </div>
    </div>

    <div class="prism-metric-grid" aria-label="Case summary">
        <div class="prism-metric-card prism-metric-red">
            <div class="prism-metric-value">{{ $stats['open'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-folder2-open prism-metric-icon"></i> Open</div>
            <div class="prism-metric-note">Awaiting classification</div>
        </div>
        <div class="prism-metric-card prism-metric-orange">
            <div class="prism-metric-value">{{ $stats['pending_nte'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-envelope prism-metric-icon"></i> Pending NTE</div>
            <div class="prism-metric-note">Awaiting student response</div>
        </div>
        <div class="prism-metric-card prism-metric-green">
            <div class="prism-metric-value">{{ $stats['resolved'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-check2-circle prism-metric-icon"></i> Resolved</div>
            <div class="prism-metric-note">Case completed</div>
        </div>
        <div class="prism-metric-card prism-metric-blue">
            <div class="prism-metric-value">{{ $stats['under_review'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-arrow-repeat prism-metric-icon"></i> Under review</div>
            <div class="prism-metric-note">Under active review</div>
        </div>
    </div>

    <section class="case-queue">
        <div class="case-queue-head">
            <h2 class="case-queue-title">Cases</h2>
            @if(array_filter($filters))
                <a href="{{ route('coordinator.case-records.index') }}" class="case-filter-toggle">Clear filters</a>
            @endif
        </div>

        <div class="case-filters">
            <form method="GET" action="{{ route('coordinator.case-records.index') }}" class="row g-2 align-items-end" data-auto-submit>
                <div class="col-12 col-md-3">
                    <label class="prism-label" for="case-record-search">Search</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" aria-hidden="true"
                           style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                        <input id="case-record-search" type="text" name="search" class="prism-input"
                               style="padding-left:30px;" placeholder="Case number, student name, or student number"
                               value="{{ $filters['search'] ?? '' }}">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <label class="prism-label" for="case-record-type">Type</label>
                    <select id="case-record-type" name="case_type" class="prism-select">
                        <option value="">All Types</option>
                        <option value="minor" {{ ($filters['case_type'] ?? '') === 'minor' ? 'selected' : '' }}>Minor</option>
                        <option value="major" {{ ($filters['case_type'] ?? '') === 'major' ? 'selected' : '' }}>Major</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="prism-label" for="case-record-status">Status</label>
                    <select id="case-record-status" name="status" class="prism-select">
                        <option value="">All Statuses</option>
                        @foreach($statusMap as $status => [$label])
                            <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="prism-label" for="case-record-period">Academic Period</label>
                    <select id="case-record-period" name="academic_period_id" class="prism-select">
                        <option value="">All Academic Periods</option>
                        @foreach($academicPeriods as $period)
                            <option value="{{ $period->id }}" {{ (string) ($filters['academic_period_id'] ?? '') === (string) $period->id ? 'selected' : '' }}>
                                {{ $period->label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="prism-label" for="case-record-department">Department</label>
                    <select id="case-record-department" name="academic_unit_id" class="prism-select">
                        <option value="">All Departments</option>
                        @foreach($academicUnits as $unit)
                            <option value="{{ $unit->id }}" {{ (string) ($filters['academic_unit_id'] ?? '') === (string) $unit->id ? 'selected' : '' }}>
                                {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        @if($cases->isEmpty())
            <div class="prism-table-empty">No cases match the selected filters.</div>
        @else
            <div class="table-responsive case-table-wrap">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Case No. &amp; Status</th>
                            <th>Student</th>
                            <th>Department</th>
                            <th>Offense</th>
                            <th>Opened</th>
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cases as $case)
                            @php
                                $status = $statusMap[$case->status] ?? [ucwords(str_replace('_', ' ', $case->status)), 'prism-badge-gray'];
                                $typeBadge = match($case->case_type) {
                                    'minor' => ['Minor', 'prism-badge-blue'],
                                    'major' => ['Major', 'prism-badge-red'],
                                    default => ['Unclassified', 'prism-badge-gray'],
                                };
                                $department = $departments[$case->student?->studentProfile?->program] ?? null;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('coordinator.case-records.show', $case) }}"
                                       class="prism-cell-primary prism-ref-number prism-ref-link case-number-link">{{ $case->case_number }}</a>
                                    <div class="case-status-pills">
                                        <span class="prism-badge {{ $status[1] }}">{{ $status[0] }}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="prism-cell-primary">{{ $case->student?->list_name ?? 'Unknown student' }}</div>
                                    <div class="prism-cell-sub prism-ref-number">{{ $case->student?->studentProfile?->student_number ?? 'No student number' }}</div>
                                </td>
                                <td class="text-muted-cell">{{ $department ?? '—' }}</td>
                                <td>
                                    <div class="case-offense-head">
                                        @if($case->confirmedOffense)
                                            <span class="prism-cell-primary" style="font-size:.8rem;">{{ $case->confirmedOffense->code }}</span>
                                        @else
                                            <span class="text-muted-cell">Not yet classified</span>
                                        @endif
                                        <span class="prism-badge {{ $typeBadge[1] }}">{{ $typeBadge[0] }}</span>
                                    </div>
                                    @if($case->confirmedOffense)
                                        <div class="prism-cell-sub">{{ Str::limit($case->confirmedOffense->title, 70) }}</div>
                                    @endif
                                </td>
                                <td class="text-muted-cell">{{ $case->created_at?->format('M d, Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <a href="{{ route('coordinator.case-records.show', $case) }}" class="prism-action-link">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="case-pagination prism-pagination"><x-pagination :paginator="$cases" /></div>
        @endif
    </section>
</div>
@endsection
