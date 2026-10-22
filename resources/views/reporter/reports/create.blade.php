@extends('layouts.reporting-user')

@section('title', 'Submit Incident Report')

@section('css')
<style>
    .reporter-form-wrap { max-width: 980px; margin-inline: auto; }
    .reporter-notice { border: 1px solid #f4d58d !important; background: #fffdf7; box-shadow: none !important; }
    .reporter-source-card { background: #fbfcfe; }
    .reporter-source-card .prism-card-body { padding-block: 18px; }
    .reporter-form-card { overflow: visible; }
    .reporter-confirmation { padding-top: 10px !important; background: #fbfcfe; }
    .reporter-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; }
    .reporter-submit { min-width: 170px; }
    .prism-dropzone { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:150px; padding:22px 20px; text-align:center; background:#FAFBFF; border:1.5px dashed #AEBCE7; border-radius:12px; cursor:pointer; transition:border-color .18s,background .18s,box-shadow .18s; }
    .prism-dropzone:hover,.prism-dropzone:focus-visible,.prism-dropzone.drag-over { background:#F3F6FF; border-color:#3156C7; box-shadow:0 0 0 3px rgba(49,86,199,.08); outline:none; }
    .prism-dropzone.has-files { background:#F0FDF4; border-color:#4ADE80; }
    .prism-dropzone-icon { color:#6077C9; font-size:2.2rem; }
    .prism-dropzone-label { margin:10px 0 2px; color:#172033; font-size:.88rem; font-weight:650; }
    .prism-dropzone-hint { margin:0; color:#64748B; font-size:.76rem; }
    .prism-dropzone-files { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-top:12px; }
    .prism-dropzone-file { display:inline-block; max-width:100%; padding:5px 10px; overflow:hidden; color:#166534; background:#DCFCE7; border-radius:7px; font-size:.76rem; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }
    @media(max-width:575.98px){.reporter-source-summary{align-items:flex-start!important;flex-direction:column}.reporter-actions{align-items:stretch;flex-direction:column-reverse}.reporter-actions .prism-btn{justify-content:center;width:100%}.reporter-submit{min-width:0}}
</style>
@endsection

@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Dashboard', 'url' => route('reporter.dashboard')],
            ['label' => 'Submit Incident Report'],
        ]" />
        <h1 class="prism-page-title">Submit Incident Report</h1>
        <p class="prism-page-sub">Report a student incident or violation to the Student Discipline Office.</p>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="reporter-form-wrap prism-card mb-4 reporter-notice">
        <div class="prism-card-body">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-shield-exclamation" style="font-size:1.5rem;color:var(--orange);flex-shrink:0;margin-top:2px;"></i>
                <div>
                    <p style="font-size:0.9rem;font-weight:700;margin-bottom:6px;">Before you submit</p>
                    <p style="font-size:0.84rem;color:var(--text-sub);margin:0;line-height:1.6;">
                        Reports submitted through this system are received by the Student Discipline Office.
                        False or malicious reports are a violation of university policy.
                        You will receive status updates on this report, but the SDO's internal assessment notes will not be disclosed.
                        You may not retrieve or delete attachments after submission.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="reporter-form-wrap">
            <form method="POST" action="{{ route('reporter.reports.store') }}"
                  enctype="multipart/form-data">
                @csrf
                <x-submit-token />

                <div class="prism-card mb-4 reporter-source-card">
                    <div class="prism-card-body">
                        <div class="reporter-source-summary d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="prism-label mb-1">Report Source</p>
                                <p style="font-size:0.84rem;color:var(--text-sub);margin:0;line-height:1.6;">
                                    This report will be recorded based on your account affiliation:
                                    <strong>{{ $reportingAffiliation }}</strong>.
                                </p>
                            </div>
                            <span class="prism-badge {{ $sourceType === 'security_report' ? 'prism-badge-navy' : 'prism-badge-violet' }}">
                                {{ $sourceLabel }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="prism-card mb-4 reporter-form-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Incident Details</h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="hidden" name="student_id" id="reportStudentId" value="{{ old('student_id') }}">
                                <label class="prism-label">Student Number or Name <span class="prism-label-opt">(optional)</span></label>
                                <div class="position-relative mb-3">
                                    <input type="text" id="reportStudentLookupInput" class="prism-input"
                                        placeholder="Search student number or name" autocomplete="off">
                                    <div id="reportStudentLookupSpinner"
                                        class="spinner-border spinner-border-sm position-absolute d-none"
                                        style="right:12px;top:50%;margin-top:-0.5rem;color:var(--blue);"></div>

                                    <div id="reportStudentSuggestionsDropdown" class="reporter-student-dropdown position-absolute d-none"
                                        style="top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);max-height:250px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                                        <div id="reportStudentSuggestionsList"></div>
                                    </div>
                                </div>

                                <div id="reportSelectedStudentCard" class="d-none"
                                    style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="prism-avatar prism-avatar-sm" id="reportStudentAvatar">--</div>
                                        <div>
                                            <div id="reportStudentName" style="font-weight:600;font-size:0.9rem;"></div>
                                            <div id="reportStudentMeta" class="prism-cell-sub prism-ref-number"></div>
                                        </div>
                                    </div>
                                </div>

                                <p class="prism-hint">Leave blank if the student's identity is unknown or if multiple students are involved.</p>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="prism-label">Date of Incident <span class="text-danger">*</span></label>
                                <input type="date" name="date_of_incident"
                                    class="prism-input @error('date_of_incident') is-invalid @enderror"
                                    value="{{ old('date_of_incident') }}"
                                    max="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="prism-label">Time <span class="prism-label-opt">(optional)</span></label>
                                <input type="time" name="time_of_incident" class="prism-input"
                                    value="{{ old('time_of_incident') }}">
                            </div>
                            <div class="col-12">
                                <label class="prism-label">Location <span class="prism-label-opt">(optional)</span></label>
                                <input type="text" name="location" class="prism-input"
                                    value="{{ old('location') }}"
                                    placeholder="e.g., Room 301, Canteen, Parking Area">
                            </div>
                            <div class="col-12">
                                <label class="prism-label">Description of Incident <span class="text-danger">*</span></label>
                                <textarea name="description"
                                    class="prism-input @error('description') is-invalid @enderror"
                                    rows="6"
                                    placeholder="Describe the incident in detail. Include what happened, who was involved, and any other relevant information..."
                                    required minlength="30" maxlength="2000">{{ old('description') }}</textarea>
                                <p class="prism-hint">Be as specific as possible.</p>
                            </div>
                        </div>
                    </div>
                    <div class="prism-card-header border-top">
                        <h2 class="prism-card-title">Attachments <span class="prism-label-opt">(optional)</span></h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="prism-dropzone" id="attachmentsDropzone" role="button" tabindex="0" aria-controls="attachmentInput" aria-label="Select or drop attachment files">
                            <i class="bi bi-file-earmark-arrow-up prism-dropzone-icon"></i>
                            <p class="prism-dropzone-label">Choose Files</p>
                            <p class="prism-dropzone-hint">or drag and drop them here</p>
                            <div id="attachmentsFileList" class="prism-dropzone-files"></div>
                        </div>
                        <input type="file" name="attachments[]" id="attachmentInput" accept=".pdf,.jpg,.jpeg,.png" multiple class="d-none">
                        <p class="prism-hint mt-2">Maximum 3 files. PDF, JPG, or PNG only. Maximum 5MB per file.</p>
                        @error('attachments.*')
                            <div class="prism-field-error">{{ $message }}</div>
                        @enderror
                        <div class="prism-note mt-3">
                            <i class="bi bi-info-circle me-1"></i>
                            Attachments cannot be retrieved or deleted after submission. Only SDO staff can access them.
                        </div>
                    </div>
                    <div class="prism-card-body reporter-confirmation">
                        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:20px;">
                            <input type="checkbox" name="disclaimer" value="1" required
                                   style="width:16px;height:16px;margin-top:2px;flex-shrink:0;accent-color:var(--navy);">
                            <span style="font-size:0.84rem;color:var(--text-sub);line-height:1.5;">
                                I confirm that the information provided is truthful and accurate to the best of my knowledge.
                                I understand that filing a false report is a violation of university policy.
                            </span>
                        </label>
                        <div class="reporter-actions">
                            <a href="{{ route('reporter.dashboard') }}"
                               class="prism-btn prism-btn-outline">Cancel</a>
                            <button type="submit" class="prism-btn prism-btn-primary reporter-submit">
                                Submit Report
                            </button>
                        </div>
                    </div>
                </div>

            </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('reportStudentLookupInput');
    const hiddenId = document.getElementById('reportStudentId');
    const spinner = document.getElementById('reportStudentLookupSpinner');
    const dropdown = document.getElementById('reportStudentSuggestionsDropdown');
    const list = document.getElementById('reportStudentSuggestionsList');
    const selectedCard = document.getElementById('reportSelectedStudentCard');

    if (!input || !hiddenId || !dropdown || !list || !selectedCard) {
        return;
    }

    let timer = null;

    function resetSelection() {
        hiddenId.value = '';
        selectedCard.classList.add('d-none');
    }

    function selectStudent(student) {
        hiddenId.value = student.user_id;
        input.value = student.student_number;
        document.getElementById('reportStudentAvatar').textContent = student.initials;
        document.getElementById('reportStudentName').textContent = student.full_name;
        document.getElementById('reportStudentMeta').textContent = `${student.student_number} · ${student.program || 'Program not set'}${student.year_level ? ' · ' + student.year_level : ''}`;
        selectedCard.classList.remove('d-none');
        dropdown.classList.add('d-none');
    }

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        resetSelection();
        dropdown.classList.add('d-none');
        list.innerHTML = '';

        if (q.length < 1) {
            return;
        }

        timer = setTimeout(() => {
            spinner.classList.remove('d-none');
            fetch(`{{ route('reporter.students.lookup') }}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    spinner.classList.add('d-none');
                    const results = data.results || [];
                    if (results.length === 0) {
                        dropdown.classList.add('d-none');
                        return;
                    }

                    list.innerHTML = '';
                    results.forEach(student => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background-color 0.15s;';
                        const nameLine = document.createElement('div');
                        nameLine.style.cssText = 'font-weight:600;font-size:0.9rem;';
                        nameLine.textContent = student.full_name;
                        const metaLine = document.createElement('div');
                        metaLine.className = 'prism-ref-number';
                        metaLine.style.cssText = 'font-size:0.78rem;color:var(--text-muted);';
                        metaLine.textContent = `${student.student_number} · ${student.program || 'Program not set'}`;
                        item.append(nameLine, metaLine);
                        item.onmouseenter = () => item.style.backgroundColor = 'var(--bg)';
                        item.onmouseleave = () => item.style.backgroundColor = 'white';
                        item.onclick = () => selectStudent(student);
                        list.appendChild(item);
                    });
                    dropdown.classList.remove('d-none');
                })
                .catch(() => {
                    spinner.classList.add('d-none');
                });
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.position-relative')) {
            dropdown.classList.add('d-none');
        }
    });
})();

const attachmentInput = document.getElementById('attachmentInput');
const attachmentsDropzone = document.getElementById('attachmentsDropzone');
const attachmentsFileList = document.getElementById('attachmentsFileList');
if (attachmentInput && attachmentsDropzone) {
    const allowedExtensions = attachmentInput.accept
        .split(',')
        .map(ext => ext.trim().toLowerCase())
        .filter(Boolean);
    const isAllowed = file => allowedExtensions.some(ext => file.name.toLowerCase().endsWith(ext));

    const showSelectedAttachments = (files) => {
        attachmentsFileList.innerHTML = '';
        Array.from(files || []).forEach(file => {
            const chip = document.createElement('span');
            chip.className = 'prism-dropzone-file';
            chip.textContent = file.name;
            attachmentsFileList.appendChild(chip);
        });
        attachmentsDropzone.classList.toggle('has-files', files.length > 0);
    };

    // The accept attribute only filters the native file picker - dropped
    // files and an "All Files" picker selection bypass it entirely.
    const applyFiles = (fileList) => {
        const files = Array.from(fileList);
        const accepted = files.filter(isAllowed);
        const rejected = files.filter(file => !isAllowed(file));

        const transfer = new DataTransfer();
        accepted.forEach(file => transfer.items.add(file));
        attachmentInput.files = transfer.files;
        showSelectedAttachments(attachmentInput.files);

        if (rejected.length && window.PrismToast) {
            PrismToast.show(`These files aren't PDF, JPG, or PNG and were not added: ${rejected.map(f => f.name).join(', ')}`, 'warning');
        }
    };

    attachmentInput.addEventListener('change', function () { applyFiles(this.files); });
    attachmentsDropzone.addEventListener('click', () => attachmentInput.click());
    attachmentsDropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); attachmentInput.click(); }
    });
    attachmentsDropzone.addEventListener('dragover', event => { event.preventDefault(); attachmentsDropzone.classList.add('drag-over'); });
    attachmentsDropzone.addEventListener('dragleave', () => attachmentsDropzone.classList.remove('drag-over'));
    attachmentsDropzone.addEventListener('drop', event => {
        event.preventDefault();
        attachmentsDropzone.classList.remove('drag-over');
        if (event.dataTransfer.files.length) { applyFiles(event.dataTransfer.files); }
    });
}
</script>
@endpush
