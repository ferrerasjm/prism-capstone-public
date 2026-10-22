@extends('layouts.staff')
@section('title', 'University Service Offices')

@section('css')
<style>
    .us-office-body {
        width: 100%;
        max-width: 1100px;
        margin-inline: auto;
    }
    .office-create-form {
        display: grid;
        grid-template-columns: minmax(280px, 1fr) auto;
        align-items: end;
        gap: .875rem;
    }
    .office-action-column { width: 150px; text-align: center; }
    .office-actions { display: flex; justify-content: center; gap: 8px; }
    .office-icon-btn {
        width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid #e2e8f0; border-radius: 7px; background: #fff; color: #dc2626;
        transition: .15s ease;
    }
    .office-icon-btn:hover { background: #fef2f2; border-color: #fecaca; color: #b91c1c; }
    .office-icon-btn.edit { color: #3156c7; }
    .office-icon-btn.edit:hover { background: #eff4ff; border-color: #bfccf5; color: #294ab3; }
    .office-icon-btn.restore { color: #15803d; }
    .office-icon-btn.restore:hover { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .office-archived { background: #f8fafc; color: #667085; }
    .office-modal .modal-content {
        overflow: hidden;
        border: 1px solid #dfe5ef;
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 20px 45px rgba(15, 23, 42, .18);
    }
    .office-modal .modal-header {
        padding: 16px 18px;
        border-bottom: 1px solid #e5e9f2;
        background: #fff;
    }
    .office-modal .modal-title { color: #0f172a; font-size: 1rem !important; font-weight: 700; }
    .office-modal .modal-body { padding: 18px; color: #475569; background: #fff; }
    .office-modal .modal-footer {
        gap: 8px;
        padding: 12px 18px 18px;
        border-top: 1px solid #e5e9f2;
        background: #f8fafc;
    }
    .office-modal .prism-label { display: block; margin-bottom: 6px; color: #334155; }
    .office-modal textarea { min-height: 110px; resize: vertical; }
    @media (max-width: 575.98px) { .office-create-form { grid-template-columns: 1fr; } }
</style>
@endsection

@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Sanctions', 'url' => route('officer.sanctions.index')],
            ['label' => 'University Service Offices'],
        ]" />
        <h1 class="prism-page-title">University Service Offices</h1>
        <p class="prism-page-sub">Control which offices can receive new University Service assignments.</p>
    </div>

    @if($errors->any())
        <div class="prism-toast-region" aria-live="assertive" aria-atomic="true">
            <div class="toast fade prism-toast prism-toast-danger"
                 role="alert"
                 aria-live="assertive"
                 aria-atomic="true"
                 data-us-office-validation-toast>
                <span class="prism-toast-icon" aria-hidden="true">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </span>
                <div class="toast-body">
                    <span class="visually-hidden">Error: </span>{{ $errors->first() }}
                </div>
                <button type="button" class="btn-close prism-toast-close" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <div class="us-office-body">
        <div class="prism-note mb-4">
            <i class="bi bi-info-circle me-1"></i>
            Archiving hides an office from new supervisor accounts and assignments. Existing assignments and records remain unchanged.
        </div>

        <div class="prism-card mb-4">
            <div class="prism-card-header"><h2 class="prism-card-title">Add Office</h2></div>
            <div class="prism-card-body">
                <form method="POST" action="{{ route('officer.us-offices.store') }}" class="office-create-form">
                    @csrf
                    <div>
                        <label for="office-name" class="prism-label">Office Name</label>
                        <input id="office-name" name="name"
                               class="prism-input @error('name') is-invalid @enderror"
                               value="{{ old('name') }}"
                               placeholder="e.g. Community Extension Office" maxlength="255" required>
                    </div>
                    <button class="prism-btn prism-btn-primary" type="submit">
                        <i class="bi bi-plus-lg"></i> Add Office
                    </button>
                </form>
            </div>
        </div>

        @php
            $officeSortLink = function (string $column, string $natural) use ($sort, $direction) {
                $active = $sort === $column;
                $next = $active ? ($direction === 'asc' ? 'desc' : 'asc') : $natural;

                return [
                    'url' => route('officer.us-offices.index', ['sort' => $column, 'direction' => $next]),
                    'active' => $active,
                    'next' => $next,
                ];
            };
            $officeNameSort = $officeSortLink('name', 'asc');
            $officeCreatedSort = $officeSortLink('created_at', 'asc');
        @endphp
        <div class="prism-card">
            <div class="prism-card-header"><h2 class="prism-card-title">Configured Offices</h2></div>
            <div class="table-responsive">
                <table class="prism-table">
                    <thead>
                        <tr>
                            <th aria-sort="{{ $officeNameSort['active'] ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a href="{{ $officeNameSort['url'] }}" class="sdo-sort-link {{ $officeNameSort['active'] ? 'is-active' : '' }}">
                                    Office Name
                                    <i class="bi {{ $officeNameSort['active'] ? ($direction === 'asc' ? 'bi-sort-alpha-down' : 'bi-sort-alpha-up') : 'bi-arrow-down-up' }}"
                                       aria-hidden="true"></i>
                                    <span class="visually-hidden">
                                        {{ $officeNameSort['active'] ? 'Sorted '.($direction === 'asc' ? 'A to Z' : 'Z to A').'. ' : '' }}Sort by office name, {{ $officeNameSort['next'] === 'asc' ? 'A to Z' : 'Z to A' }}
                                    </span>
                                </a>
                            </th>
                            <th>Status</th>
                            <th aria-sort="{{ $officeCreatedSort['active'] ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <a href="{{ $officeCreatedSort['url'] }}" class="sdo-sort-link {{ $officeCreatedSort['active'] ? 'is-active' : '' }}">
                                    Date Created
                                    <i class="bi {{ $officeCreatedSort['active'] ? ($direction === 'asc' ? 'bi-sort-down-alt' : 'bi-sort-down') : 'bi-arrow-down-up' }}"
                                       aria-hidden="true"></i>
                                    <span class="visually-hidden">
                                        {{ $officeCreatedSort['active'] ? 'Sorted '.($direction === 'asc' ? 'oldest first' : 'newest first').'. ' : '' }}Sort by date created, {{ $officeCreatedSort['next'] === 'asc' ? 'oldest first' : 'newest first' }}
                                    </span>
                                </a>
                            </th>
                            <th class="office-action-column"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($offices as $office)
                        <tr class="{{ $office->is_active ? '' : 'office-archived' }}">
                            <td class="prism-cell-primary">{{ $office->name }}</td>
                            <td>
                                <span class="prism-badge {{ $office->is_active ? 'prism-badge-green' : 'prism-badge-gray' }}">
                                    {{ $office->is_active ? 'Active' : 'Archived' }}
                                </span>
                            </td>
                            <td>{{ $office->created_at->format('M d, Y') }}</td>
                            <td class="office-action-column">
                                <div class="office-actions">
                                    <button type="button"
                                            class="office-icon-btn edit"
                                            title="Edit office"
                                            aria-label="Edit {{ $office->name }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#officeEditModal"
                                            data-edit-office-id="{{ $office->id }}"
                                            data-edit-office-name="{{ $office->name }}"
                                            data-edit-action-url="{{ route('officer.us-offices.update', $office) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button"
                                            class="office-icon-btn {{ $office->is_active ? '' : 'restore' }}"
                                            title="{{ $office->is_active ? 'Archive office' : 'Restore office' }}"
                                            aria-label="{{ $office->is_active ? 'Archive' : 'Restore' }} {{ $office->name }}"
                                            data-bs-toggle="modal"
                                            data-bs-target="#officeStatusModal"
                                            data-office-id="{{ $office->id }}"
                                            data-office-name="{{ $office->name }}"
                                            data-office-active="{{ $office->is_active ? '1' : '0' }}"
                                            data-action-url="{{ route('officer.us-offices.toggle-status', $office) }}">
                                        <i class="bi bi-{{ $office->is_active ? 'archive' : 'arrow-counterclockwise' }}"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="prism-table-empty">No University Service offices configured.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade prism-modal office-modal" id="officeEditModal" tabindex="-1"
     aria-labelledby="officeEditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="officeEditModalTitle">Edit University Service Office</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="officeEditForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="edit_target_id" id="officeEditTargetId"
                       value="{{ old('edit_target_id') }}">

                <div class="modal-body">
                    <label for="officeEditName" class="prism-label">
                        Office Name <span class="text-danger">*</span>
                    </label>
                    <input id="officeEditName" name="edit_name"
                           class="prism-input @error('edit_name') is-invalid @enderror"
                           value="{{ old('edit_name') }}" maxlength="255" required>
                    @error('edit_name')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal office-modal" id="officeStatusModal" tabindex="-1"
     aria-labelledby="officeStatusModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="officeStatusModalTitle">Archive Office</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="officeStatusForm" method="POST">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status_target_id" id="officeStatusTargetId"
                       value="{{ old('status_target_id') }}">

                <div class="modal-body">
                    <p id="officeStatusModalMessage" class="mb-3" style="font-size:.84rem;"></p>

                    <div id="officeArchiveReasonWrap">
                        <label for="officeArchiveReason" class="prism-label">
                            Archive Reason <span class="text-danger">*</span>
                        </label>
                        <textarea id="officeArchiveReason" name="archive_reason"
                                  class="prism-input @error('archive_reason') is-invalid @enderror"
                                  rows="3" minlength="20" maxlength="1000"
                                  placeholder="Explain why this office should no longer receive new assignments.">{{ old('archive_reason') }}</textarea>
                        @error('archive_reason')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger" id="officeStatusSubmit">
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
    const editModalElement = document.getElementById('officeEditModal');
    const editForm = document.getElementById('officeEditForm');
    const editName = document.getElementById('officeEditName');
    const editTargetId = document.getElementById('officeEditTargetId');

    if (editModalElement && editForm) {
        editModalElement.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            const officeId = trigger.dataset.editOfficeId;
            const previousTargetId = editTargetId.value;

            editForm.action = trigger.dataset.editActionUrl;
            editTargetId.value = officeId;

            if (previousTargetId !== officeId) {
                editName.value = trigger.dataset.editOfficeName;
            }
        });

        editModalElement.addEventListener('shown.bs.modal', () => {
            editName.focus();
            editName.select();
        });

        const failedEditTargetId = @json(old('edit_target_id'));
        const editFailed = @json($errors->has('edit_name'));

        if (editFailed && failedEditTargetId) {
            const trigger = Array.from(document.querySelectorAll('[data-edit-office-id]'))
                .find((button) => button.dataset.editOfficeId === String(failedEditTargetId));

            if (trigger) {
                bootstrap.Modal.getOrCreateInstance(editModalElement).show(trigger);
            }
        }
    }

    const modalElement = document.getElementById('officeStatusModal');
    const form = document.getElementById('officeStatusForm');
    const title = document.getElementById('officeStatusModalTitle');
    const message = document.getElementById('officeStatusModalMessage');
    const reasonWrap = document.getElementById('officeArchiveReasonWrap');
    const reason = document.getElementById('officeArchiveReason');
    const targetId = document.getElementById('officeStatusTargetId');
    const submit = document.getElementById('officeStatusSubmit');

    if (!modalElement || !form) return;

    const configure = (trigger) => {
        if (!trigger) return;

        const officeId = trigger.dataset.officeId;
        const officeName = trigger.dataset.officeName;
        const isArchiving = trigger.dataset.officeActive === '1';
        const previousTargetId = targetId.value;

        form.action = trigger.dataset.actionUrl;
        targetId.value = officeId;

        if (isArchiving) {
            title.textContent = 'Archive University Service Office';
            message.textContent = `Archive ${officeName}? It will be unavailable for new supervisor accounts and assignments, while existing records remain unchanged.`;
            reasonWrap.hidden = false;
            reason.required = true;
            submit.textContent = 'Archive';
            submit.className = 'prism-btn prism-btn-danger';

            if (previousTargetId !== officeId) reason.value = '';
        } else {
            title.textContent = 'Restore University Service Office';
            message.textContent = `Restore ${officeName}? It will become available for new supervisor accounts and assignments.`;
            reasonWrap.hidden = true;
            reason.required = false;
            reason.value = '';
            submit.textContent = 'Restore';
            submit.className = 'prism-btn prism-btn-primary';
        }
    };

    modalElement.addEventListener('show.bs.modal', (event) => configure(event.relatedTarget));
    modalElement.addEventListener('shown.bs.modal', () => {
        if (!reasonWrap.hidden) reason.focus();
    });

    const failedTargetId = @json(old('status_target_id'));
    const archiveReasonFailed = @json($errors->has('archive_reason'));

    if (archiveReasonFailed && failedTargetId) {
        const trigger = Array.from(document.querySelectorAll('[data-office-id]'))
            .find((button) => button.dataset.officeId === String(failedTargetId));

        if (trigger) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show(trigger);
        }
    }
})();
</script>
@endpush
