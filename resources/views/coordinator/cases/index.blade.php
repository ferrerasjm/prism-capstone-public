@extends('layouts.coordinator')
@section('title', 'UPCC Management')
@section('css')
<style>
    .upcc-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .upcc-summary .prism-stat {
        position: relative;
        display: block;
        min-height: 104px;
        padding: 17px 18px;
        overflow: hidden;
        border: 1px solid #e1e7f0;
        border-radius: 13px;
        background: #fff;
        box-shadow: 0 3px 12px rgba(15, 23, 42, .035);
    }

    .upcc-summary .prism-stat-value {
        color: #111827;
        font-size: 1.55rem;
        font-weight: 750;
        line-height: 1;
    }

    .upcc-summary .prism-stat-title {
        margin-top: 9px;
        margin-bottom: 0;
        color: #64748b;
        font-size: .78rem;
        font-weight: 600;
    }

    .upcc-summary .prism-stat-desc {
        margin-top: 3px;
        color: #94a3b8;
        font-size: .68rem;
        font-weight: 400;
    }

    .upcc-summary .prism-stat-icon {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: grid;
        place-items: center;
        font-size: 1rem;
    }

    .upcc-summary .prism-stat-label { display: none; }
    .upcc-summary .accent-blue .prism-stat-icon { color: #3156c7; background: #eef4ff; }
    .upcc-summary .accent-orange .prism-stat-icon { color: #ea580c; background: #fff0e7; }
    .upcc-summary .accent-violet .prism-stat-icon { color: #7c3aed; background: #f2e8ff; }
    .upcc-summary .accent-red .prism-stat-icon { color: #ef4444; background: #fff1f3; }

    .upcc-case-panel {
        overflow: hidden;
        border: 1px solid #dfe5ef;
        border-radius: 12px;
        background: #fff;
    }

    .upcc-case-panel > .prism-filter-bar {
        border: 0;
        border-bottom: 1px solid #e5e9f2;
        border-radius: 0;
    }

    .upcc-case-panel > .prism-card {
        border: 0;
        border-radius: 0;
    }

    .upcc-case-panel > .prism-card > .prism-card-header {
        border-bottom-color: #3156c7;
        background: #3156c7;
    }

    .upcc-case-panel > .prism-card > .prism-card-header .prism-card-title {
        color: #fff;
    }

    .upcc-case-panel > .prism-card > .prism-card-header .prism-badge {
        border: 1px solid rgba(255, 255, 255, .38);
        background: rgba(255, 255, 255, .14);
        color: #fff;
    }

    .upcc-table-filters {
        padding: 16px 20px;
        border-bottom: 1px solid #e5e9f2;
        background: #fff;
    }

    .upcc-table-filters .prism-label {
        margin-bottom: 7px;
        color: #334155;
    }

    /* A grey outline the eye actually catches: the shared .prism-btn-outline
       border (#cbd5e1) all but disappears against the page header, and this is
       the only action up here. */
    .upcc-void-btn {
        background: #ffffff;
        color: #334155;
        border-color: #94a3b8;
    }
    .upcc-void-btn:hover,
    .upcc-void-btn:focus {
        background: #f8fafc;
        border-color: #64748b;
        color: #0f172a;
    }

    .upcc-void-count {
        min-width: 21px;
        padding: 2px 6px;
        border-radius: 99px;
        background: #dc2626;
        color: #fff;
        font-size: .68rem;
        font-weight: 700;
        text-align: center;
    }

    @media (max-width: 991.98px) {
        .upcc-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 575.98px) {
        .upcc-summary { grid-template-columns: 1fr; }
    }

    /* Case number + status pill stacked in one cell, matching the officer case
       list (officer/cases/_results). The reclaimed UPCC Status column width goes
       to the offense so its title stops truncating. */
    #coordinatorMajorCasesResults .coord-case-number-link {
        display: inline-block;
        font-size: 0.83rem;
    }
    #coordinatorMajorCasesResults .coord-case-status-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }
    #coordinatorMajorCasesResults .prism-table td:nth-child(3) { min-width: 260px; }
    #coordinatorMajorCasesResults .prism-table th:nth-child(4),
    #coordinatorMajorCasesResults .prism-table td:nth-child(4),
    #coordinatorMajorCasesResults .prism-table th:nth-child(5),
    #coordinatorMajorCasesResults .prism-table td:nth-child(5) { white-space: nowrap; }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header d-flex align-items-center
                justify-content-between flex-wrap gap-2">
        <div>
            <h1 class="prism-page-title">UPCC Management</h1>
            <p class="prism-page-sub">
                Forwarded cases requiring UPCC proceedings
            </p>
        </div>
        <a href="{{ route('coordinator.void-approvals.index') }}"
           class="prism-btn prism-btn-outline upcc-void-btn prism-btn-sm"
           style="align-self:flex-start;flex:0 0 auto;">
            Void Requests
            @if($stats['void_approvals'] > 0)
                <span class="upcc-void-count">
                    {{ $stats['void_approvals'] }}
                </span>
            @endif
        </a>
    </div>

    <div class="upcc-case-panel">
        <div class="prism-card">
            <div class="prism-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div>
                    <h2 class="prism-card-title mb-0">UPCC Cases</h2>
                </div>
                <span class="prism-badge prism-badge-red">{{ $majorCases->total() }} total</span>
            </div>

            <div class="upcc-table-filters">
                <form method="GET"
                      action="{{ route('coordinator.cases.index') }}"
                      class="row g-2 align-items-end" data-auto-submit
                      data-ajax-table-filter="coordinatorMajorCasesResults">
                    <div class="col-12 col-sm-6 col-lg-4 position-relative">
                        <label class="prism-label">Search</label>
                        <div class="position-relative">
                            <i class="bi bi-search position-absolute" aria-hidden="true"
                               style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                            <input type="text" name="search" id="upccCaseSearchInput" class="prism-input"
                                   style="padding-left:30px;"
                                   placeholder="Case number or student name"
                                   value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                        </div>
                        <div id="upccCaseSearchSuggestions" class="position-absolute d-none"
                             style="top:100%;left:.25rem;right:.25rem;z-index:1050;max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:0 10px 24px rgba(15,23,42,.12);"></div>
                    </div>
                    <div class="col-12 col-sm-4 col-lg-2">
                        <label for="coordinatorCaseStatus" class="prism-label">Status</label>
                        <select id="coordinatorCaseStatus" name="status" class="prism-select">
                            <option value="">All</option>
                            <option value="pending_upcc"
                                {{ ($filters['status'] ?? '') === 'pending_upcc' ? 'selected' : '' }}>
                                Pending UPCC
                            </option>
                            <option value="pending_resolution"
                                {{ ($filters['status'] ?? '') === 'pending_resolution' ? 'selected' : '' }}>
                                Pending Resolution
                            </option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3" style="max-width:240px;">
                        <label for="coordinatorCaseAcademicPeriod" class="prism-label">Academic Period</label>
                        <select id="coordinatorCaseAcademicPeriod" name="academic_period_id" class="prism-select">
                            <option value="">All Academic Periods</option>
                            @foreach($academicPeriods as $period)
                                <option value="{{ $period->id }}"
                                    {{ (string) ($filters['academic_period_id'] ?? '') === (string) $period->id ? 'selected' : '' }}>
                                    {{ $period->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            <div id="coordinatorMajorCasesResults" data-ajax-table data-skeleton-target>
                <div class="prism-skeleton-wrap" style="padding:16px 20px;">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="prism-skeleton-row">
                            <span class="prism-skeleton prism-skeleton-text w-40"></span>
                            <span class="prism-skeleton prism-skeleton-badge"></span>
                            <span class="prism-skeleton prism-skeleton-text w-60"></span>
                        </div>
                    @endfor
                </div>
                <div data-ajax-table-content>
                    @include('coordinator.cases._major-cases-table', compact('majorCases'))
                </div>
            </div>
        </div>

        @if(false)
        <div class="prism-card mt-4">
            <div class="prism-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                <div>
                    <h2 class="prism-card-title mb-0">Minor Cases</h2>
                    <div class="prism-cell-sub">Read-only coordinator review list</div>
                </div>
                <span class="prism-badge prism-badge-blue">{{ $minorCases->total() }} total</span>
            </div>

            @if($minorCases->isEmpty())
                <div class="prism-table-empty">
                    No minor cases found.
                </div>
            @else
                <div class="table-responsive">
                    <table class="prism-table">
                        <thead>
                            <tr>
                                <th>Case No.</th>
                                <th>Student</th>
                                <th>Offense</th>
                                <th>Sanction</th>
                                <th>UPCC Status</th>
                                <th>Forwarded</th>
                                <th class="text-center"><span class="visually-hidden">Action</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($minorCases as $case)
                                @php
                                    $referral  = $case->upccReferral;
                                    $upccLabel = match(true) {
                                        $case->status === 'pending_resolution'
                                            => ['Pending Resolution', 'prism-badge-green'],
                                        ! $referral
                                            => ['Not Scheduled',      'prism-badge-orange'],
                                        $referral->status === 'scheduled'
                                            => ['Scheduled',          'prism-badge-blue'],
                                        $referral->status === 'in_progress'
                                            => ['In Progress',        'prism-badge-violet'],
                                        $referral->status === 'concluded'
                                            && ! $referral->decision_distributed_at
                                            => ['Awaiting Distribution', 'prism-badge-orange'],
                                        $referral->status === 'concluded'
                                            && $referral->decision_distributed_at
                                            && ! $referral->decision_final_at
                                            => ['Appeal Window',      'prism-badge-orange'],
                                        $referral->status === 'reconsideration_filed'
                                            => ['Appeal Filed',       'prism-badge-red'],
                                        default
                                            => [$referral->status,   'prism-badge-gray'],
                                    };
                                @endphp
                                <tr>
                                    <td>
                                        <div class="prism-cell-primary prism-ref-number"
                                             style="font-size:0.83rem;">
                                            {{ $case->case_number }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="prism-cell-primary">
                                            {{ $case->student?->list_name }}
                                        </div>
                                        <div class="prism-cell-sub prism-ref-number">
                                            {{ $case->student?->studentProfile?->student_number }}
                                        </div>
                                    </td>
                                    <td>
                                        @if($case->confirmedOffense)
                                            <div class="prism-cell-primary" style="font-size:0.8rem;">
                                                {{ $case->confirmedOffense->code }}
                                            </div>
                                            <div class="prism-cell-sub">
                                                {{ Str::limit($case->confirmedOffense->title, 32) }}
                                            </div>
                                        @else
                                            <span class="text-muted-cell">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($case->assigned_sanction_category)
                                            <span class="prism-badge {{ \App\Models\DisciplinaryCase::sanctionCategoryBadgeClass($case->assigned_sanction_category) }}">
                                                Category {{ $case->assigned_sanction_category }}
                                            </span>
                                        @else
                                            <span class="text-muted-cell">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="prism-badge {{ $upccLabel[1] }}"
                                              style="font-size:0.72rem;">
                                            {{ $upccLabel[0] }}
                                        </span>
                                        @if($referral?->scheduled_conference_at
                                            && $referral->status === 'scheduled')
                                            <div class="prism-cell-sub" style="margin-top:3px;">
                                                {{ \Carbon\Carbon::parse($referral->scheduled_conference_at)->format('M d, Y \a\t g:i A') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-muted-cell">
                                        {{ $case->forwarded_at
                                            ? \Carbon\Carbon::parse($case->forwarded_at)->format('M d, Y')
                                            : '—' }}
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('coordinator.cases.show', $case) }}"
                                           class="prism-action-link">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 prism-pagination"><x-pagination :paginator="$minorCases" /></div>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('upccCaseSearchInput');
    const suggestions = document.getElementById('upccCaseSearchSuggestions');
    if (!search || !suggestions) return;

    let timer;
    let request;
    const close = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    search.addEventListener('input', () => {
        window.clearTimeout(timer);
        if (request) request.abort();
        const query = search.value.trim();
        if (query.length < 1) return close();

        timer = window.setTimeout(async () => {
            request = new AbortController();
            try {
                const response = await fetch(`{{ route('coordinator.cases.search-suggestions') }}?q=${encodeURIComponent(query)}`, {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                });
                const data = await response.json();
                suggestions.replaceChildren();

                if (!data.results?.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-muted';
                    empty.style.fontSize = '.8rem';
                    empty.textContent = 'No matching cases found.';
                    suggestions.appendChild(empty);
                } else {
                    data.results.forEach((item) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'w-100 border-0 border-bottom bg-white px-3 py-2 text-start';

                        const number = document.createElement('span');
                        number.className = 'd-block fw-bold text-dark';
                        number.textContent = item.case_number;

                        const student = document.createElement('span');
                        student.className = 'd-block text-muted';
                        student.style.fontSize = '.74rem';
                        student.textContent = `${item.student_name}${item.student_number ? ` · ${item.student_number}` : ''}`;

                        option.append(number, student);
                        option.addEventListener('click', () => {
                            search.value = item.case_number;
                            close();
                            search.focus();
                        });
                        suggestions.appendChild(option);
                    });
                }
                suggestions.classList.remove('d-none');
            } catch (error) {
                if (error.name !== 'AbortError') close();
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== search && !suggestions.contains(event.target)) close();
    });
})();
</script>
@endpush
