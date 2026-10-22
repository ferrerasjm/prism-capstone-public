@extends('layouts.coordinator')

@section('title', 'Student Handbook')

@section('css')
<style>
    .handbook-page { min-width: 0; max-width: 100%; }
    .handbook-cards {
        width: 100%;
        min-width: 0;
        max-width: 960px;
        margin-inline: auto;
    }
    .handbook-cards > .prism-card {
        width: 100%;
        min-width: 0;
        max-width: 100%;
    }
    .handbook-current {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 20px;
        align-items: center;
    }
    .handbook-current > * { min-width: 0; }
    .handbook-current-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        margin-top: 10px;
        color: #64748b;
        font-size: .78rem;
    }
    .handbook-current-meta span {
        display: inline-flex;
        align-items: center;
        min-width: 0;
        max-width: 100%;
        gap: 6px;
        overflow-wrap: anywhere;
    }
    .handbook-current-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
    .handbook-upload-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .handbook-upload-grid > *,
    .handbook-modal-grid > * { min-width: 0; }
    .handbook-upload-grid .full { grid-column: 1 / -1; }
    .handbook-upload-grid .prism-label,
    .handbook-modal .prism-label { display: block; margin-bottom: 6px; }
    .handbook-edition-input {
        display: flex;
        align-items: stretch;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        border-radius: 9px;
    }
    div.prism-page-header.handbook-page-header {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        justify-content: flex-start !important;
        text-align: left !important;
        width: 100% !important;
        gap: 6px !important;
    }
    .handbook-edition-input .prism-input {
        min-width: 0;
        border-radius: 9px 0 0 9px;
    }
    .handbook-edition-suffix {
        display: inline-flex;
        align-items: center;
        padding: 0 14px;
        border: 1px solid #e4e7ec;
        border-left: 0;
        border-radius: 0 9px 9px 0;
        background: #f8fafc;
        color: #667085;
        font-size: .82rem;
        font-weight: 600;
        white-space: nowrap;
    }
    .handbook-edition-input:focus-within {
        border-radius: 9px;
        box-shadow: 0 0 0 4px rgba(47, 79, 191, 0.13);
    }
    .handbook-edition-input:focus-within .prism-input {
        border-color: #93c5fd;
        box-shadow: none;
    }
    .handbook-edition-input:focus-within .handbook-edition-suffix {
        border-color: #93c5fd;
    }
    .handbook-upload-grid textarea,
    .handbook-modal textarea { min-height: 94px; resize: vertical; }
    .handbook-dropzone {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        min-width: 0;
        max-width: 100%;
        padding: 28px 20px;
        border: 1.5px dashed #c7d2e8;
        border-radius: 14px;
        background: #f8faff;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .handbook-dropzone:hover,
    .handbook-dropzone:focus-visible {
        border-color: #93b4f4;
        background: #f2f6ff;
    }
    .handbook-dropzone.is-dragover {
        border-color: #3156c7;
        background: #eef3ff;
    }
    .handbook-dropzone.has-file {
        border-style: solid;
        border-color: #93b4f4;
    }
    .handbook-dropzone-icon {
        display: grid;
        place-items: center;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #e7ecfb;
        color: #3156c7;
        font-size: 1.15rem;
    }
    .handbook-dropzone-text { max-width: 100%; overflow-wrap: anywhere; font-size: .86rem; color: #475569; }
    .handbook-dropzone-link { color: #3156c7; font-weight: 700; }
    .handbook-dropzone-hint { font-size: .74rem; color: #94a3b8; }
    .handbook-dropzone-filename {
        display: none;
        align-items: center;
        gap: 6px;
        min-width: 0;
        max-width: 100%;
        font-size: .8rem;
        font-weight: 600;
        color: #1e293b;
    }
    .handbook-dropzone-filename span {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .handbook-dropzone.has-file .handbook-dropzone-filename { display: flex; }
    .handbook-dropzone.has-file .handbook-dropzone-text,
    .handbook-dropzone.has-file .handbook-dropzone-hint { display: none; }
    .handbook-upload-actions {
        display: flex;
        justify-content: flex-end;
        padding-top: 2px;
    }
    .handbook-edition { color: #1e293b; font-weight: 700; }
    .handbook-file-meta { margin-top: 3px; color: #94a3b8; font-size: .7rem; }
    .handbook-action-column { width: 1%; white-space: nowrap; text-align: right; }
    .handbook-cards .prism-table th:first-child,
    .handbook-cards .prism-table td:first-child { padding-left: 32px !important; }
    /* Natural content width so the table can overflow/scroll on narrow screens instead of columns cramming to fit. */
    .handbook-cards .prism-table {
        width: max-content;
        min-width: 100%;
    }
    .handbook-cards .prism-table td,
    .handbook-cards .prism-table th { white-space: nowrap; }
    .handbook-cards .prism-table .handbook-file-meta { white-space: normal; }
    .handbook-date {
        font-variant-numeric: tabular-nums;
        font-feature-settings: "tnum" 1, "lnum" 1;
    }
    .handbook-cards .table-responsive .prism-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .handbook-actions { display: flex; justify-content: flex-end; gap: 7px; }
    .handbook-icon-btn {
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #e2e8f0;
        border-radius: 7px;
        background: #fff;
        color: #3156c7;
        text-decoration: none;
        transition: .15s ease;
    }
    .handbook-icon-btn:hover { border-color: #bfccf5; background: #eff4ff; color: #294ab3; }
    .handbook-icon-btn.publish { color: #15803d; }
    .handbook-icon-btn.publish:hover { border-color: #bbf7d0; background: #f0fdf4; color: #166534; }
    .handbook-icon-btn.delete { color: #dc2626; }
    .handbook-icon-btn.delete:hover { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
    .handbook-archived-row { background: #f8fafc; color: #667085; }
    .handbook-modal.show {
        display: flex !important;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem !important;
    }
    .handbook-modal .modal-dialog {
        max-width: min(620px, calc(100vw - 2rem));
        margin: 0;
        width: 100%;
        max-height: 100%;
    }
    .handbook-modal .modal-content {
        max-height: calc(100vh - 3rem);
        overflow-y: auto;
    }
    .handbook-confirm-modal .modal-dialog { max-width: min(500px, calc(100vw - 2rem)); }
    .handbook-confirm-modal .modal-body > :last-child { margin-bottom: 0; }
    .handbook-modal-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .handbook-modal-grid .full { grid-column: 1 / -1; }
    .handbook-cards .table-responsive {
        width: 100%;
        min-width: 0;
        max-width: 100%;
        max-height: 420px;
        overflow-x: auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        /* Edge shadows hint the table scrolls horizontally, so it reads as intentional, not a layout glitch. */
        background:
            linear-gradient(to right, #fff 30%, rgba(255,255,255,0)) 0 0,
            linear-gradient(to left, #fff 30%, rgba(255,255,255,0)) 100% 0,
            linear-gradient(to right, rgba(15,23,42,.12), rgba(15,23,42,0)) 0 0,
            linear-gradient(to left, rgba(15,23,42,.12), rgba(15,23,42,0)) 100% 0;
        background-repeat: no-repeat;
        background-color: #fff;
        background-size: 28px 100%, 28px 100%, 10px 100%, 10px 100%;
        background-attachment: local, local, scroll, scroll;
    }
    @media (max-width: 767.98px) {
        .handbook-current { grid-template-columns: minmax(0, 1fr); }
        .handbook-current-actions { justify-content: flex-start; }
    }
    @media (max-width: 575.98px) {
        .handbook-upload-grid,
        .handbook-modal-grid { grid-template-columns: minmax(0, 1fr); }
        .handbook-upload-grid .full,
        .handbook-modal-grid .full { grid-column: auto; }
    }
    .handbook-page-header {
        display: block !important;
        width: 100% !important;
        text-align: left !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .handbook-page-header .prism-page-title,
    .handbook-page-header .prism-page-sub {
        margin: 0 !important;
        text-align: left !important;
    }
</style>
@endsection

@section('content')
<div class="prism-content handbook-page">
        <div class="prism-page-header handbook-page-header" style="display:flex;flex-direction:column;align-items:flex-start;text-align:left;">
            <h1 class="prism-page-title">Student Handbook</h1>
            <p class="prism-page-sub">Publish the handbook edition shown to students and retain prior editions for historical reference.</p>
        </div>

    @if($errors->any())
        <div class="prism-toast-region" aria-live="polite" aria-atomic="true">
            <div class="toast fade prism-toast prism-toast-danger" role="status" aria-live="polite" aria-atomic="true"
                 id="handbookErrorToast">
                <span class="prism-toast-icon" aria-hidden="true"><i class="bi bi-exclamation-circle-fill"></i></span>
                <div class="toast-body">
                    <span class="visually-hidden">Error: </span>{{ $errors->first() }}
                </div>
                <button type="button" class="btn-close prism-toast-close" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <div class="handbook-cards">
    <div class="prism-card mb-4">
        <div class="prism-card-header">
            <h2 class="prism-card-title">Currently Published</h2>
        </div>
        <div class="prism-card-body">
            @if($currentHandbook)
                <div class="handbook-current">
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h3 class="handbook-edition mb-0">{{ $currentHandbook->title }}</h3>
                            <span class="prism-badge prism-badge-green">Published</span>
                        </div>
                        <div class="handbook-current-meta">
                            <span><i class="bi bi-book"></i>{{ $currentHandbook->edition }}</span>
                            @if($currentHandbook->academic_year)
                                <span><i class="bi bi-calendar3"></i>Academic Year {{ $currentHandbook->academic_year }}</span>
                            @endif
                            <span>
                                <i class="bi bi-calendar-check"></i>
                                Effective {{ $currentHandbook->effective_date?->format('F j, Y') ?? 'date not recorded' }}
                            </span>
                            @if($currentHandbook->valid_until)
                                <span><i class="bi bi-calendar-x"></i>Valid Until {{ $currentHandbook->valid_until->format('F j, Y') }}</span>
                            @endif
                            <span><i class="bi bi-file-earmark-pdf"></i>{{ $currentHandbook->formattedFileSize() }}</span>
                            <span><i class="bi bi-person"></i>Uploaded by {{ $currentHandbook->uploadedBy?->list_name ?? 'System migration' }}</span>
                        </div>
                    </div>
                    <div class="handbook-current-actions">
                        <a class="prism-btn prism-btn-blue-outline"
                           href="{{ route($routePrefix.'.handbooks.download', $currentHandbook) }}">
                            <i class="bi bi-download"></i> Download
                        </a>
                    </div>
                </div>
            @else
                <div class="prism-table-empty">No handbook is currently published.</div>
            @endif
        </div>
    </div>

    <div class="prism-card mb-4">
        <div class="prism-card-header prism-card-header--blue">
            <h2 class="prism-card-title">Upload New Edition</h2>
        </div>
        <div class="prism-card-body">
            <form method="POST" action="{{ route($routePrefix.'.handbooks.store') }}"
                  enctype="multipart/form-data" class="handbook-upload-grid">
                @csrf
                <div>
                    <label for="handbookTitle" class="prism-label">Title <span class="text-danger">*</span></label>
                    <input id="handbookTitle" name="title" class="prism-input"
                           value="{{ old('title', 'NU Clark Student Handbook') }}"
                           maxlength="150" required>
                </div>
                <div>
                    <label for="handbookEdition" class="prism-label">Edition <span class="text-danger">*</span></label>
                    <div class="handbook-edition-input">
                        <input id="handbookEdition" name="edition_year" class="prism-input"
                               value="{{ old('edition_year') }}" placeholder="e.g. 2026"
                               inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                               aria-describedby="handbookEditionSuffix" required>
                        <span class="handbook-edition-suffix" id="handbookEditionSuffix">Edition</span>
                    </div>
                </div>
                <div>
                    <label for="handbookAcademicYear" class="prism-label">Academic Year <span class="prism-label-opt">(optional)</span></label>
                    <input id="handbookAcademicYear" name="academic_year" class="prism-input"
                           value="{{ old('academic_year') }}" placeholder="e.g. 2026–2027"
                           maxlength="20">
                </div>
                <div>
                    <label for="handbookEffectiveDate" class="prism-label">Effective Date <span class="text-danger">*</span></label>
                    <input id="handbookEffectiveDate" type="date" name="effective_date"
                           class="prism-input" value="{{ old('effective_date') }}" required>
                </div>
                <div>
                    <label for="handbookValidUntil" class="prism-label">Valid Until <span class="prism-label-opt">(optional)</span></label>
                    <input id="handbookValidUntil" type="date" name="valid_until"
                           class="prism-input" value="{{ old('valid_until') }}">
                </div>
                <div class="full">
                    <label for="handbookFile" class="prism-label">Handbook PDF <span class="text-danger">*</span></label>
                    <label for="handbookFile" class="handbook-dropzone" id="handbookDropzone">
                        <div class="handbook-dropzone-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
                        <div class="handbook-dropzone-text">
                            <span class="handbook-dropzone-link">Click here</span> to upload your file or drag and drop.
                        </div>
                        <div class="handbook-dropzone-hint">Supported format: PDF (up to 20 MB)</div>
                        <div class="handbook-dropzone-filename" id="handbookFileName">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                            <span id="handbookFileNameText"></span>
                        </div>
                    </label>
                    <input id="handbookFile" type="file" name="handbook_file"
                           class="visually-hidden" accept=".pdf,application/pdf" required>
                </div>
                <div class="full prism-note">
                    <i class="bi bi-info-circle me-1"></i>
                    Publishing a draft automatically archives the current edition. Published and archived editions remain immutable.
                </div>
                <div class="full">
                    <label for="handbookNotes" class="prism-label">Edition Notes <span class="prism-label-opt">(optional)</span></label>
                    <textarea id="handbookNotes" name="notes" class="prism-input"
                              maxlength="1000" placeholder="Summarize notable changes or administrative notes.">{{ old('notes') }}</textarea>
                </div>
                <div class="full handbook-upload-actions">
                    <button class="prism-btn prism-btn-primary" type="submit">
                        Upload
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="prism-card">
        <div class="prism-card-header">
            <h2 class="prism-card-title">Edition Library</h2>
        </div>
        <div class="table-responsive">
            <table class="prism-table">
                <thead>
                    <tr>
                        <th>Edition</th>
                        <th>Effective Date</th>
                        <th>Valid Until</th>
                        <th>Status</th>
                        <th>Published</th>
                        <th class="handbook-action-column"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($handbooks as $handbook)
                    @php
                        $statusClass = match($handbook->status) {
                            'published' => 'prism-badge-green',
                            'draft' => 'prism-badge-blue',
                            default => 'prism-badge-gray',
                        };
                    @endphp
                    <tr class="{{ $handbook->status === 'archived' ? 'handbook-archived-row' : '' }}">
                        <td>
                            <div class="handbook-edition">{{ $handbook->edition }}</div>
                            <div class="handbook-file-meta">
                                {{ $handbook->title }} · {{ $handbook->formattedFileSize() }}
                            </div>
                        </td>
                        <td class="handbook-date">{{ $handbook->effective_date?->format('M j, Y') ?? 'Not recorded' }}</td>
                        <td class="handbook-date">{{ $handbook->valid_until?->format('M j, Y') ?? '—' }}</td>
                        <td><span class="prism-badge {{ $statusClass }}">{{ ucfirst($handbook->status) }}</span></td>
                        <td>
                            @if($handbook->published_at)
                                <span class="handbook-date">{{ $handbook->published_at->format('M j, Y') }}</span>
                                <div class="handbook-file-meta">{{ $handbook->publishedBy?->list_name ?? 'System migration' }}</div>
                            @else
                                —
                            @endif
                        </td>
                        <td class="handbook-action-column">
                            <div class="handbook-actions">
                                @if($handbook->status === 'draft')
                                    <button type="button" class="handbook-icon-btn"
                                            title="Edit draft" aria-label="Edit {{ $handbook->edition }}"
                                            data-bs-toggle="modal" data-bs-target="#handbookEditModal"
                                            data-handbook-id="{{ $handbook->id }}"
                                            data-title="{{ $handbook->title }}"
                                            data-edition="{{ $handbook->edition }}"
                                            data-edition-year="{{ $handbook->editionYear() }}"
                                            data-academic-year="{{ $handbook->academic_year }}"
                                            data-effective-date="{{ $handbook->effective_date?->toDateString() }}"
                                            data-valid-until="{{ $handbook->valid_until?->toDateString() }}"
                                            data-notes="{{ $handbook->notes }}"
                                            data-action-url="{{ route($routePrefix.'.handbooks.update', $handbook) }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="handbook-icon-btn publish"
                                            title="Publish edition" aria-label="Publish {{ $handbook->edition }}"
                                            data-bs-toggle="modal" data-bs-target="#handbookPublishModal"
                                            data-handbook-id="{{ $handbook->id }}"
                                            data-edition="{{ $handbook->edition }}"
                                            data-action-url="{{ route($routePrefix.'.handbooks.publish', $handbook) }}">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button type="button" class="handbook-icon-btn delete"
                                            title="Delete draft" aria-label="Delete {{ $handbook->edition }}"
                                            data-bs-toggle="modal" data-bs-target="#handbookDeleteModal"
                                            data-handbook-id="{{ $handbook->id }}"
                                            data-edition="{{ $handbook->edition }}"
                                            data-action-url="{{ route($routePrefix.'.handbooks.destroy', $handbook) }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="prism-table-empty">No handbook editions have been uploaded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>

<div class="modal fade prism-modal handbook-modal" id="handbookEditModal" tabindex="-1"
     aria-labelledby="handbookEditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="handbookEditModalTitle">Edit Handbook Draft</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="handbookEditForm" method="POST" enctype="multipart/form-data">
                @csrf @method('PATCH')
                <input type="hidden" name="edit_target_id" id="handbookEditTargetId" value="{{ old('edit_target_id') }}">
                <div class="modal-body">
                    <div class="handbook-modal-grid">
                        <div>
                            <label for="handbookEditTitle" class="prism-label">Title <span class="text-danger">*</span></label>
                            <input id="handbookEditTitle" name="edit_title" class="prism-input"
                                   value="{{ old('edit_title') }}" maxlength="150" required>
                        </div>
                        <div>
                            <label for="handbookEditEdition" class="prism-label">Edition <span class="text-danger">*</span></label>
                            <div class="handbook-edition-input">
                                <input id="handbookEditEdition" name="edit_edition_year" class="prism-input"
                                       value="{{ old('edit_edition_year') }}"
                                       inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                                       aria-describedby="handbookEditEditionSuffix" required>
                                <span class="handbook-edition-suffix" id="handbookEditEditionSuffix">Edition</span>
                            </div>
                        </div>
                        <div>
                            <label for="handbookEditAcademicYear" class="prism-label">Academic Year <span class="prism-label-opt">(optional)</span></label>
                            <input id="handbookEditAcademicYear" name="edit_academic_year" class="prism-input"
                                   value="{{ old('edit_academic_year') }}" maxlength="20">
                        </div>
                        <div>
                            <label for="handbookEditEffectiveDate" class="prism-label">Effective Date <span class="text-danger">*</span></label>
                            <input id="handbookEditEffectiveDate" type="date" name="edit_effective_date"
                                   class="prism-input" value="{{ old('edit_effective_date') }}" required>
                        </div>
                        <div>
                            <label for="handbookEditValidUntil" class="prism-label">Valid Until <span class="prism-label-opt">(optional)</span></label>
                            <input id="handbookEditValidUntil" type="date" name="edit_valid_until"
                                   class="prism-input" value="{{ old('edit_valid_until') }}">
                        </div>
                        <div class="full">
                            <label for="handbookEditFile" class="prism-label">Replace PDF <span class="prism-label-opt">(optional)</span></label>
                            <input id="handbookEditFile" type="file" name="edit_handbook_file"
                                   class="prism-input" accept=".pdf,application/pdf">
                        </div>
                        <div class="full">
                            <label for="handbookEditNotes" class="prism-label">Edition Notes <span class="prism-label-opt">(optional)</span></label>
                            <textarea id="handbookEditNotes" name="edit_notes"
                                      class="prism-input" maxlength="1000">{{ old('edit_notes') }}</textarea>
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

<div class="modal fade prism-modal handbook-modal handbook-confirm-modal" id="handbookPublishModal" tabindex="-1"
     aria-labelledby="handbookPublishModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="handbookPublishModalTitle">Publish Handbook Edition</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="handbookPublishForm" method="POST">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <p class="mb-2">Publish <strong id="handbookPublishEdition"></strong> to all student dashboards?</p>
                    <p class="prism-cell-sub mb-0">
                        The currently published edition will be archived for historical reference.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary">Publish Edition</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal handbook-modal" id="handbookDeleteModal" tabindex="-1"
     aria-labelledby="handbookDeleteModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="handbookDeleteModalTitle">Delete Handbook Draft</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="handbookDeleteForm" method="POST">
                @csrf @method('DELETE')
                <input type="hidden" name="delete_target_id" id="handbookDeleteTargetId" value="{{ old('delete_target_id') }}">
                <div class="modal-body">
                    <p>Delete <strong id="handbookDeleteEdition"></strong>? This draft has never been published.</p>
                    <label for="handbookDeleteReason" class="prism-label">
                        Deletion Reason <span class="text-danger">*</span>
                    </label>
                    <textarea id="handbookDeleteReason" name="delete_reason" class="prism-input"
                              minlength="20" maxlength="500" required
                              placeholder="Explain why this draft should be removed.">{{ old('delete_reason') }}</textarea>
                    <div class="prism-cell-sub mt-1">Required and recorded in the audit trail.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger">
                        <i class="bi bi-trash"></i> Delete Draft
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
    const editModal = document.getElementById('handbookEditModal');
    const editForm = document.getElementById('handbookEditForm');

    editModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;

        editForm.action = trigger.dataset.actionUrl;
        document.getElementById('handbookEditTargetId').value = trigger.dataset.handbookId;
        document.getElementById('handbookEditTitle').value = trigger.dataset.title || '';
        document.getElementById('handbookEditEdition').value = trigger.dataset.editionYear || '';
        document.getElementById('handbookEditAcademicYear').value = trigger.dataset.academicYear || '';
        document.getElementById('handbookEditEffectiveDate').value = trigger.dataset.effectiveDate || '';
        document.getElementById('handbookEditValidUntil').value = trigger.dataset.validUntil || '';
        document.getElementById('handbookEditNotes').value = trigger.dataset.notes || '';
    });

    const publishModal = document.getElementById('handbookPublishModal');
    publishModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        document.getElementById('handbookPublishForm').action = trigger.dataset.actionUrl;
        document.getElementById('handbookPublishEdition').textContent = trigger.dataset.edition;
    });

    const deleteModal = document.getElementById('handbookDeleteModal');
    deleteModal?.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        document.getElementById('handbookDeleteForm').action = trigger.dataset.actionUrl;
        document.getElementById('handbookDeleteTargetId').value = trigger.dataset.handbookId;
        document.getElementById('handbookDeleteEdition').textContent = trigger.dataset.edition;
    });

    @if(old('edit_target_id'))
        editForm.action = @json(route($routePrefix.'.handbooks.update', old('edit_target_id')));
        bootstrap.Modal.getOrCreateInstance(editModal).show();
    @elseif(old('delete_target_id'))
        document.getElementById('handbookDeleteForm').action =
            @json(route($routePrefix.'.handbooks.destroy', old('delete_target_id')));
        bootstrap.Modal.getOrCreateInstance(deleteModal).show();
    @endif

    const dropzone = document.getElementById('handbookDropzone');
    const fileInput = document.getElementById('handbookFile');
    const fileNameText = document.getElementById('handbookFileNameText');

    if (dropzone && fileInput && fileNameText) {
        const showSelectedFile = () => {
            const file = fileInput.files?.[0];
            dropzone.classList.toggle('has-file', !!file);
            fileNameText.textContent = file ? file.name : '';
        };

        fileInput.addEventListener('change', showSelectedFile);

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.classList.remove('is-dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            const droppedFiles = e.dataTransfer?.files;
            if (droppedFiles?.length) {
                fileInput.files = droppedFiles;
                showSelectedFile();
            }
        });
    }
})();
</script>
@endpush
