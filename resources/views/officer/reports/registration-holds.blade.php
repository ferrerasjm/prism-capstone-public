@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
@endphp
@section('title', 'Registration Hold Report')
@section('css')
<style>
    .registration-holds-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .sdo-content .registration-holds-overview .prism-filter-bar {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .registration-holds-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        order: 1;
        margin: 0;
        border-bottom: 1px solid #e2e8f0;
    }

    .registration-holds-summary > div {
        width: auto;
        padding: 0;
    }

    .sdo-content .registration-holds-overview .prism-stat {
        position: relative;
        height: 100%;
        min-height: 96px;
        padding: 16px 60px 16px 18px;
        border: 0;
        border-right: 1px solid #e2e8f0;
        border-radius: 0;
        box-shadow: none;
    }

    .registration-holds-summary > div:last-child .prism-stat {
        border-right: 0;
    }

    .registration-holds-summary .prism-stat-icon {
        position: absolute;
        top: 16px;
        right: 16px;
        width: 36px;
        height: 36px;
        border-radius: 10px;
    }

    .registration-holds-summary .prism-stat-value {
        font-size: 1.5rem;
        line-height: 1;
    }

    .registration-holds-summary .prism-stat-desc {
        margin-top: 8px;
        color: #64748b;
        font-size: .8rem;
        font-weight: 600;
    }

    .registration-holds-summary .prism-stat-label { display: none; }

    .registration-holds-filter-toggle .prism-label {
        visibility: hidden;
    }

    .registration-hold-toggle {
        display: flex;
        align-items: center;
        gap: .5rem;
        width: 100%;
        min-height: 38px;
        padding: 0 12px;
        border: 1px solid #cbd5e1;
        border-radius: var(--radius-sm);
        background: #ffffff;
        color: #334155;
        font-size: .88rem;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
    }

    .registration-hold-toggle input {
        width: 16px;
        height: 16px;
        margin: 0;
        accent-color: #3156c7;
    }

    .registration-holds-card .prism-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 16px;
    }

    .registration-holds-card .prism-table th:last-child,
    .registration-holds-card .prism-table td:last-child {
        text-align: center;
        white-space: nowrap;
    }

    .lift-hold-btn {
        min-height: 30px;
        padding: 5px 12px;
        border: 1px solid #3156c7;
        border-radius: var(--radius-sm);
        background: #fff;
        color: #3156c7;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.4;
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s, color .15s;
    }

    .lift-hold-btn:hover,
    .lift-hold-btn:focus-visible {
        background: #3156c7;
        color: #fff;
    }

    .registration-holds-empty {
        display: grid;
        place-items: center;
        gap: 6px;
        margin: 16px;
        padding: 3rem 1.5rem;
        text-align: center;
        border: 1px dashed #dde3ee;
        border-radius: 10px;
        background: #fafbff;
    }

    .registration-holds-empty-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        margin-bottom: 6px;
        border-radius: 50%;
        background: #eaf0ff;
        color: #3156c7;
        font-size: 1.5rem;
    }

    .registration-holds-empty strong {
        color: var(--text);
        font-size: .98rem;
    }

    .registration-holds-empty span {
        max-width: 360px;
        color: var(--text-muted);
        font-size: .84rem;
    }

    @media (max-width: 991.98px) {
        .registration-holds-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 767.98px) {
        .registration-holds-summary {
            grid-template-columns: 1fr;
        }

        .registration-holds-card .prism-card-header {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>
@endsection
@section('content')
@include('officer.reports._print_header', ['reportTitle' => 'Registration Hold Report'])
@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header d-flex align-items-center
                justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'Registration Holds'],
            ]" />
            <h1 class="prism-page-title">Registration Hold Report</h1>
            <p class="prism-page-sub">
                For coordination with the Office of the Registrar
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'registration_hold_report',
            'filters' => ['active_only' => $activeOnly, 'program' => $program],
        ])
    </div>

    <div class="prism-alert prism-alert-danger mb-4">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div>
            This report lists students who <strong>cannot enroll</strong>
            in the next term. Submit this list to the Registrar's Office
            to block enrollment in NUIS.
        </div>
    </div>

    <div class="registration-holds-overview">
    <div class="prism-filter-bar report-actions">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.registration-holds') }}"
              class="row g-2 align-items-end" data-auto-submit>
            <input type="hidden" name="generate" value="1">
            @include('officer.reports._program_filter', ['columnClass' => 'col-12 col-md-5 col-xl-3'])
            <div class="col-12 col-md-5 col-xl-3 registration-holds-filter-toggle">
                <label class="prism-label">Cleared holds</label>
                <label class="registration-hold-toggle">
                    <input type="checkbox"
                           name="active_only"
                           value="0"
                           {{ ! $activeOnly ? 'checked' : '' }}>
                    <span>Include cleared holds</span>
                </label>
            </div>
            <div class="col-12 col-sm-auto"></div>
        </form>
    </div>

    <div class="registration-holds-summary">
        <div class="prism-stat accent-red">
            <div class="prism-stat-icon red">
                <i class="bi bi-lock-fill"></i>
            </div>
            <div class="prism-stat-value">{{ $activeHolds }}</div>
            <div class="prism-stat-desc">Active Holds</div>
        </div>
        <div class="prism-stat accent-green">
            <div class="prism-stat-icon green">
                <i class="bi bi-unlock-fill"></i>
            </div>
            <div class="prism-stat-value">{{ $clearedHolds }}</div>
            <div class="prism-stat-desc">Cleared Holds</div>
        </div>
        <div class="prism-stat accent-blue">
            <div class="prism-stat-icon blue">
                <i class="bi bi-collection-fill"></i>
            </div>
            <div class="prism-stat-value">{{ $totalHolds }}</div>
            <div class="prism-stat-desc">Total Holds</div>
        </div>
    </div>
    </div>

    <div class="prism-card registration-holds-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">
                {{ $activeOnly ? 'Active Registration Holds' : 'All Registration Holds' }}
            </h2>
            <span class="prism-badge prism-badge-blue">{{ $holds->count() }} {{ \Illuminate\Support\Str::plural('record', $holds->count()) }}</span>
        </div>

        @if($holds->isEmpty())
            <div class="registration-holds-empty">
                <span class="registration-holds-empty-icon"><i class="bi bi-clipboard-check"></i></span>
                <strong>No registration holds</strong>
                <span>
                    No registration holds
                    {{ $activeOnly ? 'currently active' : 'on record' }} for this filter.
                </span>
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Reason</th>
                            <th>Hold Since</th>
                            <th>Status</th>
                            @if(! $activeOnly)
                                <th>Cleared At</th>
                            @endif
                            <th class="text-center"><span class="visually-hidden">Action</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($holds as $hold)
                            <tr>
                                <td>
                                    <div class="prism-cell-primary">
                                        {{ $hold->student?->list_name }}
                                    </div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $hold->student?->studentProfile?->student_number ?? '—' }}
                                    </div>
                                </td>
                                <td class="prism-cell-sub">
                                    {{ \App\Models\StudentProfile::abbreviatedProgramName($hold->student?->studentProfile?->program, '—') }}
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $hold->student?->studentProfile?->year_level ?? '—' }}
                                </td>
                                <td class="prism-cell-sub" style="max-width:320px;">
                                    @if($hold->flag_reason)
                                        {!! preg_replace('/\b(Non-Readmission|Exclusion|Expulsion)\b/', '<strong>$1</strong>', e($hold->flag_reason)) !!}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="prism-cell-sub">
                                    {{ $hold->created_at->format('M d, Y') }}
                                </td>
                                <td>
                                    @if($hold->is_active)
                                        <span class="prism-badge prism-badge-red"
                                              style="font-size:0.65rem;">Active</span>
                                    @else
                                        <span class="prism-badge prism-badge-green"
                                              style="font-size:0.65rem;">Cleared</span>
                                    @endif
                                </td>
                                @if(! $activeOnly)
                                    <td class="prism-cell-sub">
                                        {{ $hold->deactivated_at?->format('M d, Y') ?? '—' }}
                                    </td>
                                @endif
                                <td class="text-center">
                                    @if($hold->is_active)
                                        <button type="button" class="lift-hold-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#liftHoldModal"
                                                data-action="{{ route($reportsRoutePrefix.'.reports.registration-holds.lift', $hold) }}"
                                                data-student="{{ $hold->student?->list_name }}">
                                            Lift Hold
                                        </button>
                                    @else
                                        <span class="text-muted-cell">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="modal fade prism-modal" id="liftHoldModal" tabindex="-1"
         aria-labelledby="liftHoldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="liftHoldForm">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                                Registration hold
                            </div>
                            <h2 class="modal-title" id="liftHoldModalLabel"
                                style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                                Lift this hold?
                            </h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p style="margin:0 0 14px;color:#475569;font-size:.84rem;line-height:1.65;">
                            <strong id="liftHoldStudentName"></strong> will be removed from the next
                            Registration Hold Report submitted to the Registrar's Office.
                        </p>
                        <label for="liftHoldReason" class="prism-label">Reason for lifting <span style="color:#dc2626;">*</span></label>
                        <textarea id="liftHoldReason" name="reason" class="prism-input w-100" rows="3"
                                  minlength="20" maxlength="1000" required
                                  placeholder="e.g. Compliance restored, waived by SDO decision, entered in error..."></textarea>
                        <div id="liftHoldReasonError" class="d-none" style="margin-top:6px;color:#dc2626;font-size:.78rem;">
                            Please provide a reason of at least 20 characters.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="prism-btn prism-btn-primary">Lift Hold</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
    const modalEl = document.getElementById('liftHoldModal');
    if (! modalEl) return;

    const form = document.getElementById('liftHoldForm');
    const studentName = document.getElementById('liftHoldStudentName');
    const reasonField = document.getElementById('liftHoldReason');
    const reasonError = document.getElementById('liftHoldReasonError');

    modalEl.addEventListener('show.bs.modal', function (e) {
        const trigger = e.relatedTarget;
        if (! trigger) return;

        form.action = trigger.dataset.action;
        studentName.textContent = trigger.dataset.student || 'This student';
        reasonField.value = '';
        reasonField.classList.remove('is-invalid');
        reasonError.classList.add('d-none');
        reasonField.dispatchEvent(new Event('input'));
    });

    form.addEventListener('submit', function (e) {
        const reason = reasonField.value.trim();

        if (reason.length < 20) {
            e.preventDefault();
            reasonField.classList.add('is-invalid');
            reasonError.classList.remove('d-none');
            reasonField.focus();
        }
    });
})();
</script>
@endpush
