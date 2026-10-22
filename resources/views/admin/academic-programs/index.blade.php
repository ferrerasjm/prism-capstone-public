@extends('layouts.admin')
@section('title', 'Academic Catalog')

@section('css')
<style>
    .catalog-page { --catalog-blue: #3158d4; --catalog-ink: #172033; }
    .catalog-heading { display:flex; align-items:flex-end; justify-content:space-between; gap:20px; margin-bottom:20px; }
    .catalog-heading-copy { min-width:0; }
    .catalog-summary { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:12px; margin-bottom:18px; }
    .catalog-summary-item { display:flex; align-items:center; justify-content:space-between; gap:12px; min-width:0; padding:14px 16px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; box-shadow:0 4px 14px rgba(30,41,59,.03); }
    .catalog-summary-value { color:var(--catalog-ink); font-size:21px; font-weight:700; line-height:1; }
    .catalog-summary-label { margin-top:6px; color:#94a3b8; font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .catalog-summary-icon { display:grid; place-items:center; width:34px; height:34px; border-radius:9px; background:#edf3ff; color:var(--catalog-blue); }
    .catalog-notice { display:flex; align-items:flex-start; gap:9px; margin-bottom:18px; padding:11px 14px; border:1px solid #dbe7ff; border-radius:10px; background:#f7faff; color:#52627a; font-size:12px; line-height:1.5; }
    .catalog-notice i { margin-top:1px; color:var(--catalog-blue); }
    .catalog-panel { overflow:hidden; border:1px solid #e2e8f0; border-radius:16px; background:#fff; box-shadow:0 6px 20px rgba(30,41,59,.04); }
    .catalog-panel-head { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; border-bottom:1px solid #edf1f6; }
    .catalog-panel-controls { display:flex; align-items:center; justify-content:flex-end; gap:10px; }
    .catalog-filter { min-width:220px; }
    .catalog-filter .prism-select { min-height:38px; padding-top:8px; padding-bottom:8px; }
    .catalog-panel-actions { display:flex; align-items:center; gap:8px; }
    .catalog-panel-title { margin:0; color:var(--catalog-ink); font-size:16px; font-weight:700; }
    .catalog-panel-sub { margin-top:3px; color:#94a3b8; font-size:12px; }
    .catalog-actions { display:flex; gap:.45rem; justify-content:flex-end; align-items:center; }
    .catalog-actions form { margin:0; }
    .catalog-table { width:100%; }
    .catalog-table th { padding:11px 20px; color:#8492aa; background:#fafbfc; font-size:10px; letter-spacing:.06em; text-transform:uppercase; font-weight:700; }
    .catalog-table td { padding:13px 20px; border-top:1px solid #f0f3f7; color:#475569; font-size:13px; vertical-align:middle; }
    .catalog-table .catalog-group-row td { padding-top:12px; padding-bottom:12px; border-top:1px solid #e3eaf6; background:#f5f8ff; }
    .catalog-group-cell { display:flex; align-items:center; justify-content:space-between; gap:16px; }
    .catalog-group-name { display:flex; align-items:center; gap:10px; color:#1e3a8a; font-weight:700; }
    .catalog-group-meta { display:flex; align-items:center; gap:12px; color:#71819b; font-size:11px; font-weight:600; }
    .catalog-record-name { color:#1e293b; font-weight:600; }
    .catalog-program-name { padding-left:58px !important; }
    .catalog-status { width:110px; }
    .catalog-actions-column { width:125px; text-align:right; }
    .catalog-modal .prism-label { display:block; margin-bottom:6px; }
    .catalog-field-error { margin-top:6px; color:#c2410c; font-size:11px; font-weight:600; }
    .catalog-field-error[hidden] { display:none; }
    .archived-row { background:#fafbfc; color:#94a3b8; }
    .catalog-empty { padding:36px 20px !important; text-align:center; color:#94a3b8 !important; }
    @media (max-width: 767.98px) {
        .catalog-heading { align-items:flex-start; flex-direction:column; }
        .catalog-panel-head { align-items:flex-start; flex-direction:column; }
        .catalog-panel-controls { align-items:stretch; flex-direction:column; width:100%; }
        .catalog-filter { width:100%; }
        .catalog-panel-actions { width:100%; }
        .catalog-panel-actions .prism-btn { flex:1; }
        .catalog-summary { grid-template-columns:repeat(2, minmax(0,1fr)); }
        .catalog-table th, .catalog-table td { padding-left:14px; padding-right:14px; }
        .catalog-table th:nth-child(2), .catalog-table td:nth-child(2) { display:none; }
        .catalog-program-name { padding-left:28px !important; }
        .catalog-group-cell { align-items:flex-start; flex-direction:column; gap:6px; }
        .catalog-group-meta { padding-left:38px; }
    }
</style>
@endsection

@section('content')
@php
    $catalogToastMessages = collect($errors->toArray())->flatten()->unique();
@endphp
@if($catalogToastMessages->isNotEmpty())
    <script>
        window.addEventListener('DOMContentLoaded', function () {
            @foreach($catalogToastMessages as $message)
                window.PrismToast?.show(@json($message), 'danger');
            @endforeach
        });
    </script>
@endif

<div class="prism-content catalog-page">
    <div class="catalog-heading">
        <div class="catalog-heading-copy">
            <h1 class="prism-page-title mb-0">Academic Catalog</h1>
            <p class="prism-page-sub mb-0">Organize departments and the programs they offer.</p>
        </div>
    </div>

    <div class="catalog-summary" aria-label="Academic catalog summary">
        <div class="catalog-summary-item"><div><div class="catalog-summary-value">{{ $units->count() }}</div><div class="catalog-summary-label">Departments</div></div><span class="catalog-summary-icon"><i class="bi bi-building" aria-hidden="true"></i></span></div>
        <div class="catalog-summary-item"><div><div class="catalog-summary-value">{{ $units->where('is_active', true)->count() }}</div><div class="catalog-summary-label">Active departments</div></div><span class="catalog-summary-icon"><i class="bi bi-building-check" aria-hidden="true"></i></span></div>
        <div class="catalog-summary-item"><div><div class="catalog-summary-value">{{ $units->flatMap->programs->where('is_active', true)->count() }}</div><div class="catalog-summary-label">Active programs</div></div><span class="catalog-summary-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span></div>
        <div class="catalog-summary-item"><div><div class="catalog-summary-value">{{ $units->flatMap->programs->where('is_active', false)->count() }}</div><div class="catalog-summary-label">Archived programs</div></div><span class="catalog-summary-icon"><i class="bi bi-archive" aria-hidden="true"></i></span></div>
    </div>

    <div class="catalog-notice">
        <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
        Archived programs are hidden from new student profiles but retained in existing profiles and historical reports.
    </div>

    <div class="catalog-panel">
        <div class="catalog-panel-head">
            <div><h2 class="catalog-panel-title">Catalog Structure</h2><div class="catalog-panel-sub">Departments and their programs in one place</div></div>
            <div class="catalog-panel-controls">
                <form method="GET" action="{{ route('admin.academic-programs.index') }}" class="catalog-filter">
                    <label for="catalog-department-filter" class="visually-hidden">Filter By Department</label>
                    <select id="catalog-department-filter" name="department" class="prism-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        @foreach($allUnits as $unit)
                            <option value="{{ $unit->id }}" {{ (string) $selectedUnitId === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </form>
            <div class="catalog-panel-actions">
                <button class="prism-btn prism-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addUnitModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Department</button>
                <button class="prism-btn prism-btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addProgramModal"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add Program</button>
            </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="catalog-table">
                <thead><tr><th>Department and programs</th><th>Date created</th><th class="catalog-status">Status</th><th class="catalog-actions-column"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                @forelse($units as $unit)
                    <tr class="catalog-group-row">
                        <td colspan="4">
                            <div class="catalog-group-cell">
                                <div class="catalog-group-name">{{ $unit->name }} <span class="prism-badge {{ $unit->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $unit->is_active ? $unit->programs->where('is_active', true)->count().' Active' : 'Archived' }}</span></div>
                                <div class="catalog-group-meta">
                                    <div class="catalog-actions sdo-user-actions">
                                        <button class="sdo-icon-btn sdo-action-view" type="button" title="Edit academic department" aria-label="Edit {{ $unit->name }}" data-bs-toggle="modal" data-bs-target="#editUnitModal" data-unit-id="{{ $unit->id }}" data-unit-name="{{ $unit->name }}" data-action-url="{{ route('admin.academic-units.update', $unit) }}"><i class="bi bi-pencil"></i></button>
                                        <button class="sdo-icon-btn {{ $unit->is_active ? 'text-danger' : 'text-success' }}" type="button" title="{{ $unit->is_active ? 'Archive academic department' : 'Restore academic department' }}" aria-label="{{ $unit->is_active ? 'Archive' : 'Restore' }} {{ $unit->name }}" data-bs-toggle="modal" data-bs-target="#catalogStatusModal" data-catalog-status data-record-type="department" data-record-id="{{ $unit->id }}" data-record-name="{{ $unit->name }}" data-record-active="{{ $unit->is_active ? '1' : '0' }}" data-action-url="{{ route('admin.academic-units.toggle-status', $unit) }}"><i class="bi bi-{{ $unit->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i></button>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @forelse($unit->programs->sortBy('name') as $program)
                        <tr class="{{ $program->is_active ? '' : 'archived-row' }}">
                            <td class="catalog-record-name catalog-program-name">{{ $program->name }}</td>
                            <td>{{ $program->created_at->format('M d, Y') }}</td>
                            <td><span class="prism-badge {{ $program->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">{{ $program->is_active ? 'Active' : 'Archived' }}</span></td>
                            <td class="catalog-actions-column"><div class="catalog-actions sdo-user-actions"><button class="sdo-icon-btn sdo-action-view" type="button" title="Edit academic program" aria-label="Edit {{ $program->name }}" data-bs-toggle="modal" data-bs-target="#editProgramModal" data-program-id="{{ $program->id }}" data-program-name="{{ $program->name }}" data-program-unit-id="{{ $program->academic_unit_id }}" data-action-url="{{ route('admin.academic-programs.update', $program) }}"><i class="bi bi-pencil"></i></button><button class="sdo-icon-btn {{ $program->is_active ? 'text-danger' : 'text-success' }}" type="button" title="{{ $program->is_active ? 'Archive academic program' : 'Restore academic program' }}" aria-label="{{ $program->is_active ? 'Archive' : 'Restore' }} {{ $program->name }}" data-bs-toggle="modal" data-bs-target="#catalogStatusModal" data-catalog-status data-record-type="program" data-record-id="{{ $program->id }}" data-record-name="{{ $program->name }}" data-record-active="{{ $program->is_active ? '1' : '0' }}" data-action-url="{{ route('admin.academic-programs.toggle-status', $program) }}"><i class="bi bi-{{ $program->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i></button></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="catalog-empty">No programs assigned to this department.</td></tr>
                    @endforelse
                @empty
                    <tr><td colspan="4" class="catalog-empty">No academic departments configured.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade prism-modal catalog-modal" id="addUnitModal" tabindex="-1"
     aria-labelledby="addUnitModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="addUnitModalTitle">Add Academic Department</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.academic-units.store') }}" id="addUnitModalForm" novalidate>
                @csrf
                <div class="modal-body">
                    <label for="unit-name" class="prism-label">Department name <span class="text-danger">*</span></label>
                    <input id="unit-name" name="name" class="prism-input" value="{{ old('name') }}"
                           placeholder="e.g. Computing Department" maxlength="150" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button class="prism-btn prism-btn-primary" type="submit">Add department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal catalog-modal" id="addProgramModal" tabindex="-1"
     aria-labelledby="addProgramModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="addProgramModalTitle">Add Academic Program</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.academic-programs.store') }}" id="addProgramModalForm" novalidate>
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="academic-unit" class="prism-label">Department <span class="text-danger">*</span></label>
                        <select id="academic-unit" name="academic_unit_id" class="prism-select prism-select-enhanced" required>
                            <option value="">Select a department</option>
                            @foreach($allUnits->where('is_active', true) as $unit)
                                <option value="{{ $unit->id }}" {{ (string) old('academic_unit_id') === (string) $unit->id ? 'selected' : '' }}>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                        <div class="catalog-field-error" id="academic-unit-modal-error" role="alert" hidden>Please select a department.</div>
                    </div>
                    <div>
                        <label for="program-name" class="prism-label">Program name <span class="text-danger">*</span></label>
                           <input id="program-name" name="name" class="prism-input"
                               placeholder="e.g. BS Computer Science" maxlength="100" value="{{ old('academic_unit_id') ? old('name') : '' }}" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button class="prism-btn prism-btn-primary" type="submit">Add program</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal catalog-modal" id="editUnitModal" tabindex="-1"
     aria-labelledby="editUnitModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="editUnitModalTitle">Edit Academic Department</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editUnitForm" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="edit_unit_id" id="editUnitId" value="{{ old('edit_unit_id') }}">
                <div class="modal-body">
                    <label for="editUnitName" class="prism-label">Department Name <span class="text-danger">*</span></label>
                    <input id="editUnitName" name="name"
                           class="prism-input @if(old('edit_unit_id')) @error('name') is-invalid @enderror @endif"
                           value="{{ old('edit_unit_id') ? old('name') : '' }}" maxlength="150" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal catalog-modal" id="editProgramModal" tabindex="-1"
     aria-labelledby="editProgramModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="editProgramModalTitle">Edit Academic Program</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProgramForm" method="POST">
                @csrf @method('PATCH')
                <input type="hidden" name="edit_program_id" id="editProgramId" value="{{ old('edit_program_id') }}">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editProgramName" class="prism-label">Program Name <span class="text-danger">*</span></label>
                        <input id="editProgramName" name="name"
                               class="prism-input @if(old('edit_program_id')) @error('name') is-invalid @enderror @endif"
                               value="{{ old('edit_program_id') ? old('name') : '' }}" maxlength="100" required>
                    </div>
                    <div>
                        <label for="editProgramUnit" class="prism-label">Academic Department <span class="text-danger">*</span></label>
                        <select id="editProgramUnit" name="academic_unit_id" class="prism-select prism-select-enhanced" required>
                            @foreach($allUnits as $unit)
                                <option value="{{ $unit->id }}" data-unit-active="{{ $unit->is_active ? '1' : '0' }}">
                                    {{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
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

<div class="modal fade prism-modal catalog-modal" id="catalogStatusModal" tabindex="-1"
     aria-labelledby="catalogStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="catalogStatusModalTitle">Archive Academic Department</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="catalogStatusForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status_target_type" id="catalogStatusTargetType"
                       value="{{ old('status_target_type') }}">
                <input type="hidden" name="status_target_id" id="catalogStatusTargetId"
                       value="{{ old('status_target_id') }}">
                <div class="modal-body">
                    <p id="catalogStatusMessage" class="mb-2"></p>
                    <p id="catalogStatusImpact" class="prism-cell-sub mb-3"></p>
                    <div id="catalogArchiveReasonWrap">
                        <label for="catalogArchiveReason" class="prism-label">
                            Archive Reason <span class="text-danger">*</span>
                        </label>
                        <textarea id="catalogArchiveReason" name="archive_reason"
                                  class="prism-input @error('archive_reason') is-invalid @enderror"
                                  maxlength="1000"
                                  placeholder="Explain why this record should be archived.">{{ old('archive_reason') }}</textarea>
                        <div class="prism-cell-sub mt-1">Required and recorded in the audit trail.</div>
                        @error('archive_reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger" id="catalogStatusSubmit">
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
    const configureModal = ({
        modalId, formId, idInputId, nameInputId, triggerSelector,
        idKey, nameKey, actionKey, failedId, afterConfigure,
    }) => {
        const modalElement = document.getElementById(modalId);
        const form = document.getElementById(formId);
        const idInput = document.getElementById(idInputId);
        const nameInput = document.getElementById(nameInputId);

        if (!modalElement || !form) return;

        modalElement.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const recordId = trigger.dataset[idKey];
            const previousId = idInput.value;
            form.action = trigger.dataset[actionKey];
            idInput.value = recordId;

            if (previousId !== recordId) nameInput.value = trigger.dataset[nameKey];
            afterConfigure?.(trigger, previousId === recordId);
        });

        modalElement.addEventListener('shown.bs.modal', () => {
            nameInput.focus();
            nameInput.select();
        });

        if (failedId) {
            const trigger = Array.from(document.querySelectorAll(triggerSelector))
                .find((button) => button.dataset[idKey] === String(failedId));
            if (trigger) bootstrap.Modal.getOrCreateInstance(modalElement).show(trigger);
        }
    };

    configureModal({
        modalId: 'editUnitModal',
        formId: 'editUnitForm',
        idInputId: 'editUnitId',
        nameInputId: 'editUnitName',
        triggerSelector: '[data-unit-id]',
        idKey: 'unitId',
        nameKey: 'unitName',
        actionKey: 'actionUrl',
        failedId: @json(old('edit_unit_id')),
    });

    configureModal({
        modalId: 'editProgramModal',
        formId: 'editProgramForm',
        idInputId: 'editProgramId',
        nameInputId: 'editProgramName',
        triggerSelector: '[data-program-id]',
        idKey: 'programId',
        nameKey: 'programName',
        actionKey: 'actionUrl',
        failedId: @json(old('edit_program_id')),
        afterConfigure: (trigger, preserveOldInput) => {
            const select = document.getElementById('editProgramUnit');
            const selectedId = preserveOldInput
                ? @json(old('academic_unit_id'))
                : trigger.dataset.programUnitId;

            Array.from(select.options).forEach((option) => {
                option.disabled = option.dataset.unitActive !== '1' && option.value !== String(selectedId);
            });
            select.value = String(selectedId);
            select.dispatchEvent(new Event('change'));
        },
    });

    const statusModal = document.getElementById('catalogStatusModal');
    const statusForm = document.getElementById('catalogStatusForm');
    const statusTitle = document.getElementById('catalogStatusModalTitle');
    const statusMessage = document.getElementById('catalogStatusMessage');
    const statusImpact = document.getElementById('catalogStatusImpact');
    const statusSubmit = document.getElementById('catalogStatusSubmit');
    const statusReasonWrap = document.getElementById('catalogArchiveReasonWrap');
    const statusReason = document.getElementById('catalogArchiveReason');
    const statusTargetType = document.getElementById('catalogStatusTargetType');
    const statusTargetId = document.getElementById('catalogStatusTargetId');

    statusModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger || !statusForm) return;

        const isDepartment = trigger.dataset.recordType === 'department';
        const isArchiving = trigger.dataset.recordActive === '1';
        const recordLabel = isDepartment ? 'Academic Department' : 'Academic Program';
        const recordName = trigger.dataset.recordName;
        const previousType = statusTargetType.value;
        const previousId = statusTargetId.value;

        statusForm.action = trigger.dataset.actionUrl;
        statusTargetType.value = trigger.dataset.recordType;
        statusTargetId.value = trigger.dataset.recordId;
        statusTitle.textContent = `${isArchiving ? 'Archive' : 'Restore'} ${recordLabel}`;
        statusMessage.textContent = `${isArchiving ? 'Archive' : 'Restore'} ${recordName}?`;
        statusSubmit.textContent = isArchiving ? 'Archive' : 'Restore';
        statusSubmit.className = isArchiving
            ? 'prism-btn prism-btn-danger'
            : 'prism-btn prism-btn-primary';
        statusReasonWrap.hidden = !isArchiving;
        statusReason.required = isArchiving;

        if (isArchiving
            && (previousType !== trigger.dataset.recordType || previousId !== trigger.dataset.recordId)) {
            statusReason.value = '';
        }

        if (isDepartment) {
            statusImpact.textContent = isArchiving
                ? 'It will be unavailable for new programs. Active programs must be archived or moved first.'
                : 'It will become available when creating and organizing academic programs.';
        } else {
            statusImpact.textContent = isArchiving
                ? 'It will be hidden from new student profiles while existing student records remain unchanged.'
                : 'It will become available for new student profiles.';
        }
    });

    const failedStatusType = @json(old('status_target_type'));
    const failedStatusId = @json(old('status_target_id'));
    const archiveReasonFailed = @json($errors->has('archive_reason'));

    if (archiveReasonFailed && failedStatusType && failedStatusId) {
        const trigger = Array.from(document.querySelectorAll('[data-catalog-status]'))
            .find((button) => button.dataset.recordType === String(failedStatusType)
                && button.dataset.recordId === String(failedStatusId));

        if (trigger) bootstrap.Modal.getOrCreateInstance(statusModal).show(trigger);
    }

    if (@json(old('academic_unit_id')) && @json($errors->any())) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('addProgramModal')).show();
    }

    const addProgramModalForm = document.getElementById('addProgramModalForm');
    const academicUnitSelect = document.getElementById('academic-unit');
    const academicUnitModalError = document.getElementById('academic-unit-modal-error');
    const programNameInput = document.getElementById('program-name');
    const addUnitModalForm = document.getElementById('addUnitModalForm');
    const unitNameInput = document.getElementById('unit-name');

    const clearAcademicUnitModalError = () => {
        academicUnitSelect?.classList.remove('is-invalid');
        if (academicUnitModalError) academicUnitModalError.hidden = true;
    };

    academicUnitSelect?.addEventListener('change', clearAcademicUnitModalError);
    academicUnitSelect?.addEventListener('input', clearAcademicUnitModalError);
    programNameInput?.addEventListener('input', () => programNameInput.classList.remove('is-invalid'));
    unitNameInput?.addEventListener('input', () => unitNameInput.classList.remove('is-invalid'));
    addProgramModalForm?.addEventListener('submit', (event) => {
        if (!academicUnitSelect?.value) {
            event.preventDefault();
            academicUnitSelect.classList.add('is-invalid');
            academicUnitModalError.hidden = false;
            academicUnitSelect.focus();
            return;
        }

        clearAcademicUnitModalError();

        if (!programNameInput?.value.trim()) {
            event.preventDefault();
            programNameInput.classList.add('is-invalid');
            window.PrismToast?.show('The program name is required.', 'danger');
            programNameInput.focus();
        }
    });

    addUnitModalForm?.addEventListener('submit', (event) => {
        if (unitNameInput?.value.trim()) return;

        event.preventDefault();
        unitNameInput.classList.add('is-invalid');
        window.PrismToast?.show('The department name is required.', 'danger');
        unitNameInput.focus();
    });
})();
</script>
@endpush
