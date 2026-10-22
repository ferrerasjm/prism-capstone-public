@extends('layouts.staff')
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

    .case-department-scope {
        display: flex;
        align-items: center;
        gap: 7px;
        min-width: 0;
        padding-left: 14px;
        border-left: 1px solid rgba(255, 255, 255, 0.28);
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

    .case-queue-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 14px;
        min-width: 0;
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

    /* Five columns now that Type and Status fold into Case No. and Offense --
       the space they freed goes to Student and Offense. */
    .case-table-wrap .prism-table th:nth-child(1),
    .case-table-wrap .prism-table td:nth-child(1) { width: 20%; }

    .case-table-wrap .prism-table th:nth-child(2),
    .case-table-wrap .prism-table td:nth-child(2) { width: 26%; }

    .case-table-wrap .prism-table th:nth-child(3),
    .case-table-wrap .prism-table td:nth-child(3) { width: 38%; }

    .case-table-wrap .prism-table th:nth-child(4),
    .case-table-wrap .prism-table td:nth-child(4) { width: 10%; white-space: nowrap; }

    .case-table-wrap .prism-table th:nth-child(5),
    .case-table-wrap .prism-table td:nth-child(5) { width: 6%; white-space: nowrap; }

    .case-number-link {
        display: inline-block;
        font-size: 0.83rem;
    }

    .case-status-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }

    .case-offense-head {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .case-status-pills .prism-badge {
        gap: 4px;
    }

    .prism-badge-escalation {
        /* Matches .prism-badge-orange's saturation -- --orange-soft (#fff7ed) is
           near-white and barely visible against the card background, unlike every
           other status pill's pastel-but-visible background. */
        background: #ffedd5;
        color: #9a3412;
    }

    .case-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    @media (max-width: 576px) {
        .case-topbar {
            display: grid;
        }

        .case-department-scope {
            align-items: flex-start;
            padding-left: 0;
            border-left: 0;
        }

        .case-queue-head,
        .case-queue-actions {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
<div class="prism-content case-workspace">

    <div class="case-topbar">
        <div>
            <h1 class="prism-page-title">Disciplinary Cases</h1>
            <p class="prism-page-sub">All disciplinary cases assigned to your office</p>
        </div>

    </div>

    <div class="prism-metric-grid" aria-label="Case summary">
        <div class="prism-metric-card prism-metric-red">
            <div class="prism-metric-value">{{ $stats['escalation_alerts'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-exclamation-triangle prism-metric-icon"></i> Escalation Alert</div>
            <div class="prism-metric-note">Reached escalation threshold</div>
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
            <div class="case-queue-actions">
                @if(array_filter($filters))
                    <a href="{{ route('officer.cases.index') }}" class="case-filter-toggle" data-ajax-target="officerCaseResults">Clear filters</a>
                @endif
                <x-department-scope :departments="$assignedDepartments" class="case-department-scope" />
            </div>
        </div>

        <div class="case-filters">
        <form method="GET" action="{{ route('officer.cases.index') }}" class="row g-2 align-items-end" data-auto-submit data-ajax-table-filter="officerCaseResults">
            <div class="col-12 col-md-4 col-xl position-relative">
                <label class="prism-label">Search</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute" aria-hidden="true"
                       style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                    <input type="text" name="search" id="caseSearchInput" class="prism-input"
                           style="padding-left:30px;"
                           placeholder="Case number or student name"
                           value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                </div>
                <div id="caseSearchSuggestions" class="position-absolute d-none"
                     style="top:100%;left:0.25rem;right:0.25rem;z-index:1050;max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:0 10px 24px rgba(15,23,42,.12);"></div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="caseType" class="prism-label">Type</label>
                <select id="caseType" name="case_type" class="prism-select">
                    <option value="">All Types</option>
                    <option value="unclassified" {{ ($filters['case_type'] ?? '') === 'unclassified' ? 'selected' : '' }}>Unclassified</option>
                    <option value="minor" {{ ($filters['case_type'] ?? '') === 'minor' ? 'selected' : '' }}>Minor</option>
                    <option value="major" {{ ($filters['case_type'] ?? '') === 'major' ? 'selected' : '' }}>Major</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="caseStatus" class="prism-label">Status</label>
                <select id="caseStatus" name="status" class="prism-select">
                    <option value="">All Statuses</option>
                    @php
                        $caseStatusLabels = [
                            'open' => 'Open',
                            'pending_nte' => 'Pending NTE',
                            'awaiting_explanation' => 'Awaiting Explanation',
                            'under_review' => 'Under Review',
                            'pending_upcc' => 'Pending UPCC',
                            'pending_resolution' => 'Pending Resolution',
                            'resolved' => 'Resolved',
                            'dismissed' => 'Dismissed',
                            'voided' => 'Voided',
                        ];
                    @endphp
                    @foreach(['open','pending_nte','awaiting_explanation','under_review','pending_upcc','pending_resolution','resolved','dismissed','voided'] as $s)
                        <option value="{{ $s }}" {{ ($filters['status'] ?? '') === $s ? 'selected' : '' }}>
                            {{ $caseStatusLabels[$s] }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl">
                <label for="caseAcademicPeriod" class="prism-label">Academic Period</label>
                <select id="caseAcademicPeriod" name="academic_period_id" class="prism-select">
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

        <div id="officerCaseResults" data-ajax-table data-skeleton-target>
            <div class="prism-skeleton-wrap" style="padding:16px;">
                @for ($i = 0; $i < 4; $i++)
                    <div class="prism-skeleton-row">
                        <span class="prism-skeleton prism-skeleton-text w-40"></span>
                        <span class="prism-skeleton prism-skeleton-badge"></span>
                        <span class="prism-skeleton prism-skeleton-text w-60"></span>
                    </div>
                @endfor
            </div>
            <div data-ajax-table-content>
                @include('officer.cases._results', compact('stats', 'cases'))
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('caseSearchInput');
    const suggestions = document.getElementById('caseSearchSuggestions');

    if (!search || !suggestions) return;

    let searchTimer;
    let activeRequest;

    const closeSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    const addText = (parent, text, className, fontSize = null) => {
        const element = document.createElement('span');
        element.className = className;
        element.textContent = text;
        if (fontSize) element.style.fontSize = fontSize;
        parent.appendChild(element);
    };

    search.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        const value = search.value.trim();

        if (activeRequest) activeRequest.abort();
        if (value.length < 1) {
            closeSuggestions();
            return;
        }

        searchTimer = window.setTimeout(async () => {
            activeRequest = new AbortController();

            try {
                const response = await fetch(`{{ route('officer.cases.search-suggestions') }}?q=${encodeURIComponent(value)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
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
                    data.results.forEach((caseItem) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'w-100 border-0 border-bottom bg-white px-3 py-2 text-start';
                        addText(option, caseItem.case_number, 'd-block fw-bold text-dark');
                        addText(
                            option,
                            `${caseItem.student_name}${caseItem.student_number ? ` · ${caseItem.student_number}` : ''}`,
                            'd-block text-muted',
                            '.74rem'
                        );
                        option.addEventListener('click', () => {
                            search.value = caseItem.case_number;
                            closeSuggestions();
                            search.focus();
                        });
                        suggestions.appendChild(option);
                    });
                }

                suggestions.classList.remove('d-none');
            } catch (error) {
                if (error.name !== 'AbortError') closeSuggestions();
            }
        }, 250);
    });

    document.addEventListener('click', (event) => {
        if (event.target !== search && !suggestions.contains(event.target)) closeSuggestions();
    });
})();
</script>
@endpush
