@extends('layouts.staff')

@section('title', 'Incident Reports')

@section('css')
<style>
    .incident-workspace {
        display: grid;
        gap: 16px;
        overflow-x: hidden;
    }

    .incident-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .incident-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .incident-actions .prism-btn {
        justify-content: center;
        min-height: 40px;
        padding-inline: 14px;
        border-radius: 8px;
        box-shadow: none;
    }

    .incident-actions .prism-btn-primary:hover {
        box-shadow: none;
    }

    .incident-actions .prism-btn-blue-outline {
        background: #FFFFFF;
        border-width: 1.5px;
        font-weight: 700;
    }

    .incident-queue {
        background: #FFFFFF;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        overflow: hidden;
    }

    .incident-queue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #3156C7;
        background: #3156C7;
    }

    .incident-queue-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .incident-filter-toggle {
        color: rgba(255, 255, 255, .82);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }

    .incident-filter-toggle:hover {
        color: #FFFFFF;
    }

    .incident-queue-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 14px;
    }

    .incident-department-scope {
        display: flex;
        align-items: center;
        gap: 7px;
        min-width: 0;
        padding-left: 14px;
        border-left: 1px solid rgba(255, 255, 255, 0.28);
    }

    .incident-filters {
        padding: 14px 16px;
        border-bottom: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    .incident-filters .prism-label {
        margin-bottom: 4px;
        font-size: 0.74rem;
        color: #475569;
    }

    .incident-filters .prism-input,
    .incident-filters .prism-select {
        min-height: 38px;
        font-size: 0.84rem;
    }

    .incident-table-wrap {
        contain: layout paint;
    }

    .incident-table-wrap .prism-table thead th {
        background: #F8FAFC;
    }

    .incident-table-wrap .prism-table tbody td {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
    }

    /* Five columns now that Source and Status fold into the Report No. and
       Reported By cells -- the freed width goes to the two name columns. */
    .incident-table-wrap .prism-table th:nth-child(1),
    .incident-table-wrap .prism-table td:nth-child(1) { width: 22%; }

    .incident-table-wrap .prism-table th:nth-child(2),
    .incident-table-wrap .prism-table td:nth-child(2) { width: 32%; }

    .incident-table-wrap .prism-table th:nth-child(3),
    .incident-table-wrap .prism-table td:nth-child(3) { width: 28%; }

    .incident-table-wrap .prism-table th:nth-child(4),
    .incident-table-wrap .prism-table td:nth-child(4) { width: 12%; white-space: nowrap; }

    .incident-table-wrap .prism-table th:nth-child(5),
    .incident-table-wrap .prism-table td:nth-child(5) { width: 6%; white-space: nowrap; }

    .incident-number-link {
        display: inline-block;
        font-size: 0.83rem;
    }

    .incident-status-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }

    .incident-reporter-head {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .incident-sort-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #3156C7;
        text-decoration: none;
    }

    .incident-sort-link:hover {
        color: #24439E;
    }

    .incident-sort-link:focus-visible {
        border-radius: 4px;
        outline: 2px solid #3156C7;
        outline-offset: 2px;
    }

    .incident-sort-link i {
        font-size: .72rem;
    }

    .incident-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    @media (max-width: 576px) {
        .incident-topbar {
            display: grid;
        }

        .incident-actions {
            display: flex;
            gap: 10px;
        }

        .incident-actions .prism-btn {
            flex: 1 1 0;
            width: auto;
            min-width: 0;
        }

        .incident-queue-head,
        .incident-queue-actions {
            align-items: flex-start;
            flex-direction: column;
        }

        .incident-department-scope {
            align-items: flex-start;
            padding-left: 0;
            border-left: 0;
        }

    }
</style>
@endsection

@section('content')
<div class="prism-content incident-workspace">

    <div class="incident-topbar">
        <div>
            <h1 class="prism-page-title">Incident Reports</h1>
            <p class="prism-page-sub">All reports submitted to the Student Discipline Office</p>
        </div>
        <div class="incident-actions">
            <a href="{{ route('officer.incidents.create') }}" class="prism-btn prism-btn-blue-outline">
                Direct Encoding
            </a>
            <a href="{{ route('officer.violations.create') }}" class="prism-btn prism-btn-primary">
                Violation Slip
            </a>
        </div>
    </div>

    <div class="prism-metric-grid" aria-label="Incident report summary">
        <div class="prism-metric-card prism-metric-orange">
            <div class="prism-metric-value">{{ $stats['pending'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-inbox prism-metric-icon"></i> Pending review</div>
            <div class="prism-metric-note">Awaiting initial review</div>
        </div>
        <div class="prism-metric-card prism-metric-blue">
            <div class="prism-metric-value">{{ $stats['reviewing'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-arrow-repeat prism-metric-icon"></i> Under review</div>
            <div class="prism-metric-note">Currently under review</div>
        </div>
        <div class="prism-metric-card prism-metric-green">
            <div class="prism-metric-value">{{ $stats['converted'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-folder2-open prism-metric-icon"></i> Cases created</div>
            <div class="prism-metric-note">Converted to cases</div>
        </div>
        <div class="prism-metric-card prism-metric-gray">
            <div class="prism-metric-value">{{ $stats['dismissed'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-x-circle prism-metric-icon"></i> Dismissed</div>
            <div class="prism-metric-note">Reports dismissed</div>
        </div>
    </div>

    <section class="incident-queue">
        <div class="incident-queue-head">
            <h2 class="incident-queue-title">Reports</h2>
            <div class="incident-queue-actions">
                @if(array_filter($filters))
                    <a href="{{ route('officer.incidents.index') }}" class="incident-filter-toggle" data-ajax-target="officerIncidentResults">Clear filters</a>
                @endif
                <x-department-scope :departments="$assignedDepartments" class="incident-department-scope" />
            </div>
        </div>

        <div class="incident-filters">
        <form method="GET" action="{{ route('officer.incidents.index') }}" id="incidentFilterForm"
              class="row g-2 align-items-end" data-auto-submit data-ajax-table-filter="officerIncidentResults">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <input type="hidden" name="direction" value="{{ $direction }}">
            <div class="col-12 col-md-4 col-xl position-relative">
                <label class="prism-label">Search</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute" aria-hidden="true"
                       style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                    <input type="text" name="search" id="incidentSearchInput" class="prism-input"
                        style="padding-left:30px;"
                        placeholder="Report no., name, description..."
                        value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                </div>
                <div id="incidentSearchSuggestions" class="position-absolute d-none"
                     style="top:100%;left:0.25rem;right:0.25rem;z-index:1050;max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--border);border-radius:0 0 8px 8px;box-shadow:0 10px 24px rgba(15,23,42,.12);"></div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="incidentSourceType" class="prism-label">Source</label>
                <select id="incidentSourceType" name="source_type" class="prism-select">
                    <option value="">All Sources</option>
                    <option value="violation_slip"   {{ ($filters['source_type'] ?? '') === 'violation_slip'   ? 'selected' : '' }}>Violation Slip</option>
                    <option value="student_complaint" {{ ($filters['source_type'] ?? '') === 'student_complaint'? 'selected' : '' }}>Student Complaint</option>
                    <option value="faculty_report"   {{ ($filters['source_type'] ?? '') === 'faculty_report'   ? 'selected' : '' }}>Faculty Report</option>
                    <option value="security_report"  {{ ($filters['source_type'] ?? '') === 'security_report'  ? 'selected' : '' }}>Security Report</option>
                    <option value="direct_encoding"  {{ ($filters['source_type'] ?? '') === 'direct_encoding'  ? 'selected' : '' }}>Direct Encoding</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="incidentStatus" class="prism-label">Status</label>
                <select id="incidentStatus" name="status" class="prism-select">
                    <option value="">All Statuses</option>
                    <option value="pending_review"    {{ ($filters['status'] ?? '') === 'pending_review'    ? 'selected' : '' }}>Pending Review</option>
                    <option value="under_review"      {{ ($filters['status'] ?? '') === 'under_review'      ? 'selected' : '' }}>Under Review</option>
                    <option value="converted_to_case" {{ ($filters['status'] ?? '') === 'converted_to_case' ? 'selected' : '' }}>Converted to Case</option>
                    <option value="dismissed"         {{ ($filters['status'] ?? '') === 'dismissed'         ? 'selected' : '' }}>Dismissed</option>
                </select>
            </div>
            <div class="col-12 col-md-4 col-xl">
                <label for="incidentAcademicPeriod" class="prism-label">Academic Period</label>
                <select id="incidentAcademicPeriod" name="academic_period_id" class="prism-select">
                    <option value="">All Academic Periods</option>
                    @foreach($academicPeriods as $period)
                        <option value="{{ $period->id }}"
                            {{ (string) ($filters['academic_period_id'] ?? '') === (string) $period->id ? 'selected' : '' }}>
                            {{ $period->label }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="incidentDateFrom" class="prism-label">Date From</label>
                <input id="incidentDateFrom" type="date" name="date_from" class="prism-input"
                    value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="incidentDateTo" class="prism-label">Date To</label>
                <input id="incidentDateTo" type="date" name="date_to" class="prism-input"
                    value="{{ $filters['date_to'] ?? '' }}">
            </div>
        </form>
        </div>

        <div id="officerIncidentResults" data-ajax-table data-skeleton-target>
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
                @include('officer.incidents._results', compact('stats', 'reports'))
            </div>
        </div>
    </section>

</div>
@endsection

@push('scripts')
<script>
(() => {
    const search = document.getElementById('incidentSearchInput');
    const suggestions = document.getElementById('incidentSearchSuggestions');

    if (!search || !suggestions) return;

    let searchTimer;
    let activeRequest;

    const closeSuggestions = () => {
        suggestions.classList.add('d-none');
        suggestions.replaceChildren();
    };

    const addText = (parent, text, className) => {
        const element = document.createElement('span');
        element.className = className;
        element.textContent = text;
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
                const response = await fetch(`{{ route('officer.incidents.search-suggestions') }}?q=${encodeURIComponent(value)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: activeRequest.signal,
                });
                const data = await response.json();
                suggestions.replaceChildren();

                if (!data.results?.length) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-muted';
                    empty.style.fontSize = '.8rem';
                    empty.textContent = 'No matching reports found.';
                    suggestions.appendChild(empty);
                } else {
                    data.results.forEach((report) => {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'w-100 border-0 border-bottom bg-white px-3 py-2 text-start';
                        addText(option, report.report_number, 'd-block fw-bold text-dark');
                        addText(option, report.student_name || report.reported_by || 'No person identified', 'd-block text-muted');
                        option.lastElementChild.style.fontSize = '.74rem';
                        option.addEventListener('click', () => {
                            search.value = report.report_number;
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
