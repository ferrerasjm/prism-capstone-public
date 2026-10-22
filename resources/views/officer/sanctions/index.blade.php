@extends('layouts.staff')
@section('title', 'Sanctions')

@section('css')
<style>
    .sanction-workspace {
        display: grid;
        gap: 16px;
    }

    .sanction-topbar {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 14px;
        border-bottom: 1px solid #DDE3EE;
    }

    .sanction-queue {
        background: #FFFFFF;
        border: 1px solid #DDE3EE;
        border-radius: 8px;
        overflow: hidden;
    }

    .sanction-queue-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid #3156C7;
        background: #3156C7;
    }

    .sanction-queue-title {
        margin: 0;
        color: #FFFFFF;
        font-size: 1rem;
        font-weight: 700;
    }

    .sanction-filter-toggle {
        color: rgba(255, 255, 255, 0.82);
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }

    .sanction-filter-toggle:hover {
        color: #FFFFFF;
    }

    .sanction-queue-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 14px;
        min-width: 0;
    }

    .sanction-department-scope {
        display: flex;
        align-items: center;
        gap: 7px;
        min-width: 0;
        padding-left: 14px;
        border-left: 1px solid rgba(255, 255, 255, 0.28);
    }

    .sanction-filters {
        padding: 14px 16px;
        border-bottom: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    .sanction-filters .prism-input {
        min-height: 38px;
        font-size: 0.84rem;
    }
    .sanction-assignment-cell { min-width: 280px; }
    .sanction-assignment { display: grid; gap: 4px; font-size: .8rem; line-height: 1.4; }
    .sanction-assignment-title { color: #172033; font-weight: 700; }
    .sanction-assignment-meta { color: #64748b; }
    .sanction-assignment-label { margin-right: 4px; color: #94a3b8; font-size: .67rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .sanction-referral { display: grid; gap: 8px; }
    .sanction-referral-status { display: flex; flex-wrap: wrap; gap: 6px; }
    .sanction-referral-person { color: #334155; font-size: .8rem; }
    .sanction-referral-person strong { color: #172033; }

    .sanction-filters .prism-label {
        margin-bottom: 4px;
        font-size: 0.74rem;
        color: #475569;
    }

    .sanction-filters .prism-select {
        min-height: 38px;
        font-size: 0.84rem;
    }

    .sanction-table-wrap .prism-table thead th {
        background: #F8FAFC;
    }

    .sanction-table-wrap .prism-table tbody td {
        padding-top: 13px !important;
        padding-bottom: 13px !important;
    }

    /* Six columns now that Status sits under the case number. */
    .sanction-table-wrap .prism-table th:nth-child(1),
    .sanction-table-wrap .prism-table td:nth-child(1) { width: 17%; }

    .sanction-table-wrap .prism-table th:nth-child(2),
    .sanction-table-wrap .prism-table td:nth-child(2) { width: 22%; }

    .sanction-table-wrap .prism-table th:nth-child(3),
    .sanction-table-wrap .prism-table td:nth-child(3) { width: 16%; }

    .sanction-table-wrap .prism-table th:nth-child(4),
    .sanction-table-wrap .prism-table td:nth-child(4) { width: 29%; }

    .sanction-table-wrap .prism-table th:nth-child(5),
    .sanction-table-wrap .prism-table td:nth-child(5) { width: 10%; white-space: nowrap; }

    .sanction-table-wrap .prism-table th:nth-child(6),
    .sanction-table-wrap .prism-table td:nth-child(6) { width: 6%; white-space: nowrap; }

    .sanction-case-link {
        display: inline-block;
        font-size: 0.83rem;
    }

    .sanction-status-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 5px;
    }

    .sanction-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }

    @media (max-width: 576px) {
        .sanction-topbar {
            display: grid;
        }

        .sanction-department-scope {
            align-items: flex-start;
            padding-left: 0;
            border-left: 0;
        }

        .sanction-queue-head,
        .sanction-queue-actions {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>
@endsection

@section('content')
<div class="prism-content sanction-workspace">

    <div class="sanction-topbar">
        <div>
            <h1 class="prism-page-title">Sanctions</h1>
            <p class="prism-page-sub">Active and completed sanctions across all intervention categories</p>
        </div>
        <a href="{{ route('officer.us-offices.index') }}" class="prism-btn prism-btn-blue-outline">
            University Service Offices
        </a>
    </div>

    @foreach(['success','error'] as $t)
        @if(session($t))
            <div class="prism-alert prism-alert-{{ $t === 'error' ? 'danger' : $t }} mb-4">
                <i class="bi bi-{{ $t === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' }}"></i>
                <div>{{ session($t) }}</div>
            </div>
        @endif
    @endforeach

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="prism-metric-grid" aria-label="Sanction summary">
        <div class="prism-metric-card prism-metric-orange">
            <div class="prism-metric-value">{{ $stats['pending'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-hourglass-split prism-metric-icon"></i> Pending</div>
            <div class="prism-metric-note">Not yet started</div>
        </div>
        <div class="prism-metric-card prism-metric-blue">
            <div class="prism-metric-value">{{ $stats['in_progress'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-arrow-repeat prism-metric-icon"></i> In progress</div>
            <div class="prism-metric-note">Currently ongoing</div>
        </div>
        <div class="prism-metric-card prism-metric-green">
            <div class="prism-metric-value">{{ $stats['completed'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-check2-circle prism-metric-icon"></i> Completed</div>
            <div class="prism-metric-note">Sanction fulfilled</div>
        </div>
        <div class="prism-metric-card prism-metric-red">
            <div class="prism-metric-value">{{ $stats['overdue'] }}</div>
            <div class="prism-metric-label"><i class="bi bi-exclamation-circle prism-metric-icon"></i> Overdue</div>
            <div class="prism-metric-note">Past due date</div>
        </div>
    </div>

    <section class="sanction-queue">
        <div class="sanction-queue-head">
            <h2 class="sanction-queue-title">
                @if($type)
                    {{ $typeLabels[$type] ?? $type }}
                @else
                    All Sanctions
                @endif
            </h2>
            <div class="sanction-queue-actions">
                @if(array_filter($filters))
                    <a href="{{ route('officer.sanctions.index') }}" class="sanction-filter-toggle" data-ajax-target="sanctionTableContainer">Clear filters</a>
                @endif
                <x-department-scope :departments="$assignedDepartments" class="sanction-department-scope" />
            </div>
        </div>

        <div class="sanction-filters">
        <form method="GET" action="{{ route('officer.sanctions.index') }}"
              class="row g-2 align-items-end" data-auto-submit data-ajax-table-filter="sanctionTableContainer">
            <div class="col-12 col-md-4 col-xl">
                <label for="sanctionSearch" class="prism-label">Search</label>
                <div class="position-relative">
                    <i class="bi bi-search position-absolute" aria-hidden="true"
                       style="left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:.7rem;pointer-events:none;"></i>
                    <input type="text" name="search" id="sanctionSearch" class="prism-input"
                           style="padding-left:30px;"
                           placeholder="Case number or student name"
                           value="{{ $filters['search'] ?? '' }}" autocomplete="off">
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="sanctionType" class="prism-label">Sanction Type</label>
                <select id="sanctionType" name="type" class="prism-select">
                    <option value="">All Types</option>
                    <option value="probation"              {{ $type === 'probation'              ? 'selected' : '' }}>Probation</option>
                    <option value="counseling_referral"    {{ $type === 'counseling_referral'    ? 'selected' : '' }}>Counseling Referral</option>
                    <option value="university_service"     {{ $type === 'university_service'     ? 'selected' : '' }}>University Service</option>
                    <option value="discipline_education"   {{ $type === 'discipline_education'   ? 'selected' : '' }}>Discipline Education</option>
                    <option value="evaluation_monitoring"  {{ $type === 'evaluation_monitoring'  ? 'selected' : '' }}>Evaluation / Monitoring</option>
                    <option value="non_readmission"        {{ $type === 'non_readmission'        ? 'selected' : '' }}>Non-Readmission</option>
                    <option value="exclusion"              {{ $type === 'exclusion'              ? 'selected' : '' }}>Exclusion</option>
                    <option value="expulsion"              {{ $type === 'expulsion'              ? 'selected' : '' }}>Expulsion</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl">
                <label for="sanctionStatus" class="prism-label">Status</label>
                <select id="sanctionStatus" name="status" class="prism-select">
                    <option value="">All Statuses</option>
                    @foreach(['pending' => 'Pending', 'in_progress' => 'In Progress', 'completed' => 'Completed', 'non_compliant' => 'Non-Compliant', 'waived' => 'Waived'] as $value => $label)
                        <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
        </div>

        <div id="sanctionTableContainer" data-ajax-table data-skeleton-target>
            <div class="prism-skeleton-wrap" style="padding:16px;">
                @for ($i = 0; $i < 5; $i++)
                    <div class="prism-skeleton-row">
                        <span class="prism-skeleton prism-skeleton-text w-40"></span>
                        <span class="prism-skeleton prism-skeleton-badge"></span>
                        <span class="prism-skeleton prism-skeleton-text w-60"></span>
                    </div>
                @endfor
            </div>
            <div data-ajax-table-content>
                @include('officer.sanctions._table', compact('sanctions', 'type', 'typeLabels'))
            </div>
        </div>
    </section>

</div>
@endsection
