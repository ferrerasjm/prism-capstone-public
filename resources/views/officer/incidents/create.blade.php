@extends('layouts.staff')
@section('title', 'Direct Encoding')

@section('css')
<style>
    .prism-dropzone {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 150px;
        padding: 22px 20px;
        text-align: center;
        background: #FAFBFF;
        border: 1.5px dashed #AEBCE7;
        border-radius: 12px;
        cursor: pointer;
        transition: border-color .18s, background .18s, box-shadow .18s;
    }
    .prism-dropzone:hover,
    .prism-dropzone:focus-visible,
    .prism-dropzone.drag-over {
        background: #F3F6FF;
        border-color: #3156C7;
        box-shadow: 0 0 0 3px rgba(49, 86, 199, .08);
        outline: none;
    }
    .prism-dropzone.has-files {
        background: #F0FDF4;
        border-color: #4ADE80;
    }
    .prism-dropzone-icon { color: #6077C9; font-size: 2.2rem; }
    .prism-dropzone-label { margin: 10px 0 2px; color: #172033; font-size: .88rem; font-weight: 650; }
    .prism-dropzone-hint { margin: 0; color: #64748B; font-size: .76rem; }
    .prism-dropzone-files {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: center;
        margin-top: 12px;
    }
    .prism-dropzone-file {
        display: inline-block;
        max-width: 100%;
        padding: 5px 10px;
        overflow: hidden;
        color: #166534;
        background: #DCFCE7;
        border-radius: 7px;
        font-size: .76rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
@endsection

@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Incident Reports', 'url' => route('officer.incidents.index')],
            ['label' => 'Direct Encoding'],
        ]" />
        <h1 class="prism-page-title">Direct Encoding</h1>
        <p class="prism-page-sub">Encode an incident report received through non-system channels (phone call, walk-in, physical letter).</p>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="row justify-content-center"><div class="col-12 col-lg-9 col-xl-8">
        <form method="POST" action="{{ route('officer.incidents.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="prism-card mb-4 student-lookup-card">
                <div class="prism-card-header"><h2 class="prism-card-title">Student Subject <span class="prism-label-opt">(optional)</span></h2></div>
                <div class="prism-card-body">
                    <label class="prism-label">Student Number</label>
                    <div class="position-relative mb-2 student-lookup-wrap">
                        <input type="text" id="studentNumberInput" class="prism-input" placeholder="Enter student number (e.g. 2023-000000)" autocomplete="off">
                        <div id="lookupSpinner" class="spinner-border spinner-border-sm position-absolute d-none" style="right:12px;top:50%;margin-top:-0.5rem;color:var(--blue);"></div>
                        
                        <div id="suggestionsDropdown" class="position-absolute d-none" style="top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);max-height:250px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                            <div id="suggestionsList"></div>
                        </div>
                    </div>
                    <input type="hidden" name="student_id" id="studentId" value="{{ old('student_id') }}">
                    <div id="studentCard" class="d-none" style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="prism-avatar prism-avatar-md" id="studentAvatar">—</div>
                            <div>
                                <div id="studentName" style="font-weight:700;font-size:0.95rem;"></div>
                                <div id="studentNumber" class="prism-ref-number" style="font-size:0.78rem;color:var(--text-muted);"></div>
                                <div id="studentProgram" style="font-size:0.78rem;color:var(--text-muted);"></div>
                            </div>
                        </div>
                    </div>
                    <div id="studentNotFound" class="d-none mt-2">
                        <div class="prism-alert prism-alert-warning" style="font-size:0.82rem;"><i class="bi bi-exclamation-triangle-fill"></i><span id="notFoundMsg">Student not found.</span></div>
                    </div>
                    <p class="prism-hint mt-2">Leave blank if the student subject is unknown at this time.</p>
                </div>
            </div>

            <div class="prism-card">
                <div class="prism-card-header"><h2 class="prism-card-title">Incident Details</h2></div>
                <div class="prism-card-body">
                    <div class="row g-3">
                        <div class="col-7">
                            <label class="prism-label">Date of Incident <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_incident" class="prism-input @error('date_of_incident') is-invalid @enderror" value="{{ old('date_of_incident', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-5">
                            <label class="prism-label">Time <span class="prism-label-opt">(optional)</span></label>
                            <input type="time" name="time_of_incident" class="prism-input" value="{{ old('time_of_incident') }}">
                        </div>
                        <div class="col-12">
                            <label class="prism-label">Location <span class="prism-label-opt">(optional)</span></label>
                            <input type="text" name="location" class="prism-input" value="{{ old('location') }}" placeholder="e.g., Hallway, Library, Gymnasium">
                        </div>
                        <div class="col-12">
                            <label class="prism-label">Incident Description <span class="text-danger">*</span></label>
                            <textarea name="description" class="prism-input @error('description') is-invalid @enderror" rows="6" required minlength="20" maxlength="2000" placeholder="Describe the incident in detail...">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="prism-label">Violation Type</label>
                            <select name="violation_type" class="prism-select prism-select-optional prism-select-enhanced">
                                <option value="">Select if applicable</option>
                                <optgroup label="Uniform / Attire">
                                    <option value="Not wearing University uniform"
                                            {{ old('violation_type') === 'Not wearing University uniform' ? 'selected' : '' }}>
                                        Not wearing University uniform
                                    </option>
                                    <option value="Wearing incomplete University uniform"
                                            {{ old('violation_type') === 'Wearing incomplete University uniform' ? 'selected' : '' }}>
                                        Wearing incomplete University uniform
                                    </option>
                                    <option value="Not wearing University ID"
                                            {{ old('violation_type') === 'Not wearing University ID' ? 'selected' : '' }}>
                                        Not wearing University ID
                                    </option>
                                    <option value="Not wearing corporate attire"
                                            {{ old('violation_type') === 'Not wearing corporate attire' ? 'selected' : '' }}>
                                        Not wearing corporate attire
                                    </option>
                                    <option value="Wearing inappropriate attire"
                                            {{ old('violation_type') === 'Wearing inappropriate attire' ? 'selected' : '' }}>
                                        Wearing inappropriate attire
                                    </option>
                                </optgroup>
                                <optgroup label="Other">
                                    <option value="Bringing prohibited items"
                                            {{ old('violation_type') === 'Bringing prohibited items' ? 'selected' : '' }}>
                                        Bringing prohibited items
                                    </option>
                                    <option value="Others"
                                            {{ old('violation_type') === 'Others' ? 'selected' : '' }}>
                                        Others (describe in description)
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="prism-card-header border-top"><h2 class="prism-card-title">Attachments <span class="prism-label-opt">(optional)</span></h2></div>
                <div class="prism-card-body">
                    <div class="prism-dropzone" id="attachmentsDropzone" role="button" tabindex="0"
                         aria-controls="attachmentsInput" aria-label="Select or drop attachment files">
                        <i class="bi bi-file-earmark-arrow-up prism-dropzone-icon"></i>
                        <p class="prism-dropzone-label">Choose Files</p>
                        <p class="prism-dropzone-hint">or drag and drop them here</p>
                        <div id="attachmentsFileList" class="prism-dropzone-files"></div>
                    </div>
                    <input type="file" name="attachments[]" id="attachmentsInput" accept=".pdf,.jpg,.jpeg,.png" multiple class="d-none">
                    <p class="prism-hint mt-2">Maximum 3 files. PDF, JPG, or PNG only. Maximum 5MB per file.</p>
                </div>
                <div class="prism-card-footer justify-content-end border-0 bg-white pt-2">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('officer.incidents.index') }}" class="prism-btn prism-btn-outline">Cancel</a>
                        <button type="submit" class="prism-btn prism-btn-primary">Save Report</button>
                    </div>
                </div>
            </div>
        </form>
    </div></div>
</div>
@endsection
@push('scripts')
<script>
let lookupTimeout = null;
const input = document.getElementById('studentNumberInput');
const idField = document.getElementById('studentId');
const card = document.getElementById('studentCard');
const notFound = document.getElementById('studentNotFound');
const spinner = document.getElementById('lookupSpinner');
const suggestionsDropdown = document.getElementById('suggestionsDropdown');
const suggestionsList = document.getElementById('suggestionsList');

function selectStudent(data) {
    input.value = data.student_number;
    idField.value = data.user_id;
    document.getElementById('studentName').textContent = data.full_name;
    document.getElementById('studentNumber').textContent = data.student_number;
    document.getElementById('studentProgram').textContent = `${data.program} · ${data.year_level}`;
    document.getElementById('studentAvatar').textContent = data.initials;
    card.classList.remove('d-none');
    suggestionsDropdown.classList.add('d-none');
    notFound.classList.add('d-none');
}

input.addEventListener('input', function() {
    clearTimeout(lookupTimeout);
    card.classList.add('d-none');
    notFound.classList.add('d-none');
    suggestionsDropdown.classList.add('d-none');
    idField.value = '';
    if (this.value.trim().length < 1) return;
    lookupTimeout = setTimeout(() => {
        spinner.classList.remove('d-none');
        fetch(`{{ route('officer.students.lookup') }}?student_number=${encodeURIComponent(this.value.trim())}`)
            .then(r => r.json())
            .then(data => {
                spinner.classList.add('d-none');
                if (data.results && data.results.length > 0) {
                    suggestionsList.innerHTML = '';
                    data.results.forEach((result, idx) => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background-color 0.15s;';
                        const nameLine = document.createElement('div');
                        nameLine.style.cssText = 'font-weight:600;font-size:0.9rem;';
                        nameLine.textContent = result.full_name;
                        const metaLine = document.createElement('div');
                        metaLine.className = 'prism-ref-number';
                        metaLine.style.cssText = 'font-size:0.78rem;color:var(--text-muted);';
                        metaLine.textContent = `${result.student_number} · ${result.program}`;
                        item.append(nameLine, metaLine);
                        item.onmouseenter = () => item.style.backgroundColor = 'var(--bg)';
                        item.onmouseleave = () => item.style.backgroundColor = 'white';
                        item.onclick = () => selectStudent(result);
                        suggestionsList.appendChild(item);
                    });
                    suggestionsDropdown.classList.remove('d-none');
                } else {
                    document.getElementById('notFoundMsg').textContent = 'No students found in your assigned department.';
                    notFound.classList.remove('d-none');
                }
            })
            .catch(() => spinner.classList.add('d-none'));
    }, 500);
});

document.addEventListener('click', (e) => {
    if (!e.target.closest('.position-relative')) {
        suggestionsDropdown.classList.add('d-none');
    }
});

const attachmentsInput = document.getElementById('attachmentsInput');
const attachmentsDropzone = document.getElementById('attachmentsDropzone');
const attachmentsFileList = document.getElementById('attachmentsFileList');

function showSelectedAttachments(files) {
    attachmentsFileList.innerHTML = '';
    if (files.length > 0) {
        Array.from(files).forEach(file => {
            const chip = document.createElement('span');
            chip.className = 'prism-dropzone-file';
            chip.textContent = file.name;
            attachmentsFileList.appendChild(chip);
        });
        attachmentsDropzone.classList.add('has-files');
    } else {
        attachmentsDropzone.classList.remove('has-files');
    }
}

attachmentsInput.addEventListener('change', function () {
    showSelectedAttachments(this.files);
});

attachmentsDropzone.addEventListener('click', () => attachmentsInput.click());
attachmentsDropzone.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        attachmentsInput.click();
    }
});
attachmentsDropzone.addEventListener('dragover', e => { e.preventDefault(); attachmentsDropzone.classList.add('drag-over'); });
attachmentsDropzone.addEventListener('dragleave', () => attachmentsDropzone.classList.remove('drag-over'));
attachmentsDropzone.addEventListener('drop', e => {
    e.preventDefault();
    attachmentsDropzone.classList.remove('drag-over');
    if (e.dataTransfer.files.length > 0) {
        attachmentsInput.files = e.dataTransfer.files;
        showSelectedAttachments(attachmentsInput.files);
    }
});
</script>
@endpush
