@extends('layouts.admin')
@section('title', 'Academic Periods')
@section('css')
<style>
    .academic-period-form {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
        align-items: end;
        gap: .875rem;
    }

    .academic-period-table {
        min-width: 850px;
    }

    .academic-period-table td {
        vertical-align: middle;
    }

    .academic-period-table .period-action {
        width: 180px;
        text-align: center;
    }

    .period-record-primary { color: #1e293b; font-weight: 600; }
    .period-modal .prism-label { display: block; margin-bottom: 6px; }
    .period-modal-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .period-archived-row { background: #f8fafc; color: #667085; }

    .period-actions {
        display: flex;
        justify-content: center;
        gap: .5rem;
    }
    .period-actions form { margin: 0; }

    @media (max-width: 991.98px) {
        .academic-period-form {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .academic-period-form button[type="submit"] {
            grid-column: 1 / -1;
            width: 100%;
            justify-content: center;
        }
    }
    @media (max-width: 575.98px) {
        .period-modal-grid { grid-template-columns: 1fr; }
    }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <h1 class="prism-page-title">Academic Periods</h1>
        <p class="prism-page-sub">
            Define official term dates used to classify incidents and filter reports.
        </p>
    </div>

    <div class="prism-note mb-4">
        <i class="bi bi-info-circle me-1"></i>
        Periods cannot overlap. Incidents are assigned using their recorded incident date,
        and existing incidents are synchronized whenever a period is created or updated.
    </div>

    <div class="prism-card mb-4">
        <div class="prism-card-header">
            <h2 class="prism-card-title">Add Academic Period</h2>
        </div>
        <div class="prism-card-body">
            <form method="POST" action="{{ route('admin.academic-periods.store') }}"
                  class="academic-period-form">
                @csrf
                <div>
                    <label class="prism-label">Academic Year</label>
                    <input type="text" name="academic_year" class="prism-input"
                           value="{{ old('academic_year') }}" placeholder="Enter year (e.g., 2023-2024)"
                           pattern="\d{4}-\d{4}" required>
                </div>
                <div>
                    <label class="prism-label">Term</label>
                    <select name="term_name" class="prism-select prism-select-enhanced" required>
                        @foreach(['1st Term', '2nd Term', '3rd Term'] as $term)
                            <option value="{{ $term }}" {{ old('term_name') === $term ? 'selected' : '' }}>
                                {{ $term }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="createStartsOn" class="prism-label">Start Date</label>
                    <input id="createStartsOn" type="date" name="starts_on" class="prism-input academic-date-input"
                           value="{{ old('starts_on') }}" required>
                </div>
                <div>
                    <label for="createEndsOn" class="prism-label">End Date</label>
                    <input id="createEndsOn" type="date" name="ends_on" class="prism-input academic-date-input"
                           value="{{ old('ends_on') }}" required>
                </div>
                <button type="submit" class="prism-btn prism-btn-primary">
                    <i class="bi bi-plus-lg"></i> Add Period
                </button>
            </form>
        </div>
    </div>

    <div class="prism-card">
        <div class="prism-card-header prism-card-header--blue">
            <h2 class="prism-card-title">Configured Periods</h2>
        </div>
        @if($periods->isEmpty())
            <div class="prism-table-empty">
                No academic periods configured yet.
            </div>
        @else
            <div class="table-responsive">
                <table class="prism-table academic-period-table">
                    <thead>
                        <tr>
                            <th>Academic Year</th>
                            <th>Term</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Status</th>
                            <th class="period-action"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($periods as $period)
                            <tr class="{{ in_array($period->statusLabel(), ['Archived', 'Ended'], true) ? 'period-archived-row' : '' }}">
                                <td class="period-record-primary">{{ $period->academic_year }}</td>
                                <td>{{ $period->term_name }}</td>
                                <td>{{ $period->starts_on->format('M j, Y') }}</td>
                                <td>{{ $period->ends_on->format('M j, Y') }}</td>
                                <td>
                                    <span class="prism-badge {{ $period->statusBadgeClass() }}">
                                        {{ $period->statusLabel() }}
                                    </span>
                                </td>
                                <td class="period-action">
                                    <div class="period-actions sdo-user-actions">
                                        @if($period->is_active)
                                        <button type="button"
                                                class="sdo-icon-btn sdo-action-view"
                                                title="Edit academic period"
                                                aria-label="Edit {{ $period->term_name }} A.Y. {{ $period->academic_year }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editPeriodModal"
                                                data-period-id="{{ $period->id }}"
                                                data-academic-year="{{ $period->academic_year }}"
                                                data-term-name="{{ $period->term_name }}"
                                                data-starts-on="{{ $period->starts_on->toDateString() }}"
                                                data-ends-on="{{ $period->ends_on->toDateString() }}"
                                                data-action-url="{{ route('admin.academic-periods.update', $period) }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        @endif
                                        <button type="button"
                                                class="sdo-icon-btn {{ $period->is_active ? 'text-danger' : 'text-success' }}"
                                                title="{{ $period->is_active ? 'Archive academic period' : 'Restore academic period' }}"
                                                aria-label="{{ $period->is_active ? 'Archive' : 'Restore' }} {{ $period->term_name }} A.Y. {{ $period->academic_year }}"
                                                data-bs-toggle="modal"
                                                data-bs-target="#periodStatusModal"
                                                data-period-status
                                                data-status-period-id="{{ $period->id }}"
                                                data-period-label="{{ $period->label }}"
                                                data-period-active="{{ $period->is_active ? '1' : '0' }}"
                                                data-action-url="{{ route('admin.academic-periods.toggle-status', $period) }}">
                                            <i class="bi bi-{{ $period->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="modal fade prism-modal period-modal" id="editPeriodModal" tabindex="-1"
     aria-labelledby="editPeriodModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="editPeriodModalTitle">Edit Academic Period</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editPeriodForm" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="edit_period_id" id="editPeriodId" value="{{ old('edit_period_id') }}">
                <div class="modal-body">
                    <div class="period-modal-grid">
                        <div>
                            <label for="editAcademicYear" class="prism-label">Academic Year <span class="text-danger">*</span></label>
                            <input id="editAcademicYear" name="academic_year" class="prism-input"
                                   value="{{ old('edit_period_id') ? old('academic_year') : '' }}"
                                   placeholder="Enter year (e.g., 2023-2024)" pattern="\d{4}-\d{4}" required>
                        </div>
                        <div>
                            <label for="editTermName" class="prism-label">Term <span class="text-danger">*</span></label>
                            <select id="editTermName" name="term_name" class="prism-select prism-select-enhanced" required>
                                @foreach(['1st Term', '2nd Term', '3rd Term'] as $term)
                                    <option value="{{ $term }}">{{ $term }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="editStartsOn" class="prism-label">Start Date <span class="text-danger">*</span></label>
                            <input id="editStartsOn" type="date" name="starts_on" class="prism-input academic-date-input"
                                   value="{{ old('edit_period_id') ? old('starts_on') : '' }}" required>
                        </div>
                        <div>
                            <label for="editEndsOn" class="prism-label">End Date <span class="text-danger">*</span></label>
                            <input id="editEndsOn" type="date" name="ends_on" class="prism-input academic-date-input"
                                   value="{{ old('edit_period_id') ? old('ends_on') : '' }}" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal period-modal" id="periodStatusModal" tabindex="-1"
     aria-labelledby="periodStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="periodStatusModalTitle">Archive Academic Period</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="periodStatusForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status_target_id" id="periodStatusTargetId"
                       value="{{ old('status_target_id') }}">
                <div class="modal-body">
                    <p id="periodStatusMessage" class="mb-2"></p>
                    <p id="periodStatusImpact" class="prism-cell-sub mb-3"></p>
                    <div id="periodArchiveReasonWrap">
                        <label for="periodArchiveReason" class="prism-label">
                            Archive Reason <span class="text-danger">*</span>
                        </label>
                        <textarea id="periodArchiveReason" name="archive_reason"
                                  class="prism-input @error('archive_reason') is-invalid @enderror"
                                  maxlength="1000"
                                  placeholder="Explain why this academic period should be archived.">{{ old('archive_reason') }}</textarea>
                        <div class="prism-cell-sub mt-1">Required and recorded in the audit trail.</div>
                        @error('archive_reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger" id="periodStatusSubmit">
                        Archive
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    document.querySelectorAll('.academic-date-input').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (event.key !== 'Backspace' && event.key !== 'Delete') return;

            event.preventDefault();
            input.value = '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    const connectDateRange = (startsOn, endsOn) => {
        if (!startsOn || !endsOn) return;

        const syncValidity = () => {
            endsOn.setCustomValidity(
                startsOn.value && endsOn.value && endsOn.value < startsOn.value
                    ? 'End date cannot be earlier than start date.'
                    : ''
            );
        };

        // Reassigning .min while the end date is mid-typing (e.g. only two digits of the
        // year entered) makes the browser re-validate the field and clear it - so only
        // touch .min when the start date itself changes, never in response to the end
        // date's own input.
        const syncMinimum = () => {
            endsOn.min = startsOn.value;
            syncValidity();
        };

        startsOn.addEventListener('input', syncMinimum);
        startsOn.addEventListener('change', syncMinimum);
        endsOn.addEventListener('input', syncValidity);
        endsOn.addEventListener('change', syncValidity);
        syncMinimum();
    };

    connectDateRange(
        document.getElementById('createStartsOn'),
        document.getElementById('createEndsOn')
    );

    const modalElement = document.getElementById('editPeriodModal');
    const form = document.getElementById('editPeriodForm');
    const idInput = document.getElementById('editPeriodId');
    const academicYear = document.getElementById('editAcademicYear');
    const termName = document.getElementById('editTermName');
    const startsOn = document.getElementById('editStartsOn');
    const endsOn = document.getElementById('editEndsOn');

    connectDateRange(startsOn, endsOn);

    if (!modalElement || !form) return;

    modalElement.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        const periodId = trigger.dataset.periodId;
        const preserveOldInput = idInput.value === periodId;

        form.action = trigger.dataset.actionUrl;
        idInput.value = periodId;

        if (!preserveOldInput) {
            academicYear.value = trigger.dataset.academicYear;
            termName.value = trigger.dataset.termName;
            termName.dispatchEvent(new Event('change'));
            startsOn.value = trigger.dataset.startsOn;
            endsOn.value = trigger.dataset.endsOn;
        }
    });

    modalElement.addEventListener('shown.bs.modal', () => {
        academicYear.focus();
        academicYear.select();
    });

    const failedPeriodId = @json(old('edit_period_id'));
    if (failedPeriodId) {
        const trigger = Array.from(document.querySelectorAll('[data-period-id]'))
            .find((button) => button.dataset.periodId === String(failedPeriodId));
        if (trigger) bootstrap.Modal.getOrCreateInstance(modalElement).show(trigger);
    }

    const statusModal = document.getElementById('periodStatusModal');
    const statusForm = document.getElementById('periodStatusForm');
    const statusTitle = document.getElementById('periodStatusModalTitle');
    const statusMessage = document.getElementById('periodStatusMessage');
    const statusImpact = document.getElementById('periodStatusImpact');
    const statusSubmit = document.getElementById('periodStatusSubmit');
    const statusReasonWrap = document.getElementById('periodArchiveReasonWrap');
    const statusReason = document.getElementById('periodArchiveReason');
    const statusTargetId = document.getElementById('periodStatusTargetId');

    statusModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger || !statusForm) return;

        const isArchiving = trigger.dataset.periodActive === '1';
        const action = isArchiving ? 'Archive' : 'Restore';
        const previousTargetId = statusTargetId.value;

        statusForm.action = trigger.dataset.actionUrl;
        statusTargetId.value = trigger.dataset.statusPeriodId;
        statusTitle.textContent = `${action} Academic Period`;
        statusMessage.textContent = `${action} ${trigger.dataset.periodLabel}?`;
        statusImpact.textContent = isArchiving
            ? 'It will no longer classify new incidents automatically. Existing incident and report history will remain unchanged.'
            : 'It will resume automatic classification for incidents within its configured dates.';
        statusSubmit.textContent = action;
        statusSubmit.className = isArchiving
            ? 'prism-btn prism-btn-danger'
            : 'prism-btn prism-btn-primary';
        statusReasonWrap.hidden = !isArchiving;
        statusReason.required = isArchiving;

        if (isArchiving && previousTargetId !== trigger.dataset.statusPeriodId) {
            statusReason.value = '';
        }
    });

    const failedStatusTargetId = @json(old('status_target_id'));
    const archiveReasonFailed = @json($errors->has('archive_reason'));

    if (archiveReasonFailed && failedStatusTargetId) {
        const trigger = Array.from(document.querySelectorAll('[data-period-status]'))
            .find((button) => button.dataset.statusPeriodId === String(failedStatusTargetId));

        if (trigger) bootstrap.Modal.getOrCreateInstance(statusModal).show(trigger);
    }
})();
</script>
@endpush
