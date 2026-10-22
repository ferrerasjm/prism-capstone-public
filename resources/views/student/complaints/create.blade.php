@extends('layouts.student')
@section('title', 'Submit Complaint')
@section('css')
<style>
    .complaint-notice { border: 1px solid #fbbf24 !important; background: #fffdf7; box-shadow: none !important; }
    .complaint-form-card { overflow: hidden; }
    .complaint-form-section + .complaint-form-section { border-top: 1px solid #e5e9f2; }
    .complaint-section-head { padding: 14px 20px; border-bottom: 1px solid #e5e9f2; background: #fbfcfe; }
    .complaint-section-body { padding: 20px; }
    .complaint-form-section.complaint-confirmation { padding: 18px 20px; border-top: 0; background: #fbfcfe; }
    .complaint-actions { display: flex; align-items: center; justify-content: flex-end; gap: 9px; margin-top: 18px; }
    .complaint-submit { flex: 0 0 auto; width: auto; min-width: 170px; }
    .prism-dropzone { display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:150px; padding:22px 20px; text-align:center; background:#FAFBFF; border:1.5px dashed #AEBCE7; border-radius:12px; cursor:pointer; transition:border-color .18s,background .18s,box-shadow .18s; }
    .prism-dropzone:hover,.prism-dropzone:focus-visible,.prism-dropzone.drag-over { background:#F3F6FF; border-color:#3156C7; box-shadow:0 0 0 3px rgba(49,86,199,.08); outline:none; }
    .prism-dropzone.has-files { background:#F0FDF4; border-color:#4ADE80; }
    .prism-dropzone-icon { color:#6077C9; font-size:2.2rem; }
    .prism-dropzone-label { margin:10px 0 2px; color:#172033; font-size:.88rem; font-weight:650; }
    .prism-dropzone-hint { margin:0; color:#64748B; font-size:.76rem; }
    .prism-dropzone-files { display:flex; flex-wrap:wrap; gap:6px; justify-content:center; margin-top:12px; }
    .prism-dropzone-file { display:inline-block; max-width:100%; padding:5px 10px; overflow:hidden; color:#166534; background:#DCFCE7; border-radius:7px; font-size:.76rem; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }
    @media(max-width:575.98px){.complaint-actions{align-items:stretch;flex-direction:column-reverse}.complaint-actions .prism-btn{justify-content:center;width:100%}}
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Complaints', 'url' => route('student.complaints.index')],
            ['label' => 'Submit a Complaint'],
        ]" />
        <h1 class="prism-page-title">Submit a Complaint</h1>
        <p class="prism-page-sub">Report an incident to the Student Discipline Office.</p>
    </div>
    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4"><i class="bi bi-exclamation-circle-fill"></i><div>{{ $errors->first() }}</div></div>
    @endif
    <div class="row justify-content-center"><div class="col-12 col-lg-9 col-xl-8">
        <div class="prism-card mb-4 complaint-notice">
            <div class="prism-card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-shield-exclamation" style="font-size:1.5rem;color:var(--orange);flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        <p style="font-size:0.9rem;font-weight:700;margin-bottom:6px;">Important Notice</p>
                        <p style="font-size:0.84rem;color:var(--text-sub);margin:0;line-height:1.6;">Your complaint will be received by the Student Discipline Office. Please ensure the information you provide is accurate and truthful — the SDO may take disciplinary action against students who submit or spread false reports. You will receive status updates, but the SDO's internal decisions will not be disclosed to you.</p>
                    </div>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('student.complaints.store') }}" enctype="multipart/form-data">
            @csrf
            <x-submit-token />
            <div class="prism-card complaint-form-card">
                <section class="complaint-form-section">
                <div class="complaint-section-head"><h2 class="prism-card-title">Complaint Details</h2></div>
                <div class="complaint-section-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-7">
                            <label class="prism-label">Date of Incident <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_incident" class="prism-input @error('date_of_incident') is-invalid @enderror" value="{{ old('date_of_incident') }}" max="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="prism-label">Time <span class="prism-label-opt">(optional)</span></label>
                            <input type="time" name="time_of_incident" class="prism-input" value="{{ old('time_of_incident') }}">
                        </div>
                        <div class="col-12">
                            <label class="prism-label">Location <span class="prism-label-opt">(optional)</span></label>
                            <input type="text" name="location" class="prism-input" value="{{ old('location') }}" placeholder="Where did this happen?">
                        </div>
                        <div class="col-12">
                            <label class="prism-label">Describe the incident <span class="text-danger">*</span></label>
                            <textarea name="description" class="prism-input @error('description') is-invalid @enderror" rows="7" required minlength="50" maxlength="5000" placeholder="Describe what happened in detail — who was involved, what occurred, and any relevant context...">{{ old('description') }}</textarea>
                            <p class="prism-hint">Be as specific as possible.</p>
                        </div>
                    </div>
                </div>
                </section>
                <section class="complaint-form-section">
                <div class="complaint-section-head"><h2 class="prism-card-title">Attachments <span class="prism-label-opt">(optional)</span></h2></div>
                <div class="complaint-section-body">
                    <div class="prism-dropzone" id="attachmentsDropzone" role="button" tabindex="0" aria-controls="attachmentsInput" aria-label="Select or drop attachment files">
                        <i class="bi bi-file-earmark-arrow-up prism-dropzone-icon"></i>
                        <p class="prism-dropzone-label">Choose Files</p>
                        <p class="prism-dropzone-hint">or drag and drop them here</p>
                        <div id="attachmentsFileList" class="prism-dropzone-files"></div>
                    </div>
                    <input type="file" name="attachments[]" id="attachmentsInput" accept=".pdf,.jpg,.jpeg,.png" multiple class="d-none">
                    <p class="prism-hint mt-2">Maximum 3 files. PDF, JPG, or PNG only. Maximum 5MB per file.</p>
                </div>
                </section>
                <section class="complaint-form-section complaint-confirmation">
                    <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin:0;">
                        <input type="checkbox" name="disclaimer" value="1" required style="width:16px;height:16px;margin-top:2px;accent-color:var(--navy);">
                        <span style="font-size:0.84rem;color:var(--text-sub);line-height:1.5;">I confirm that the information I provided is truthful and accurate. I understand that filing a false complaint is a disciplinary violation.</span>
                    </label>
                    <div class="complaint-actions">
                        <a href="{{ route('student.complaints.index') }}" class="prism-btn prism-btn-outline">Cancel</a>
                        <button type="submit" class="prism-btn prism-btn-primary complaint-submit">Submit Complaint</button>
                    </div>
                </section>
            </div>
        </form>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
const attachmentsInput = document.getElementById('attachmentsInput');
const attachmentsDropzone = document.getElementById('attachmentsDropzone');
const attachmentsFileList = document.getElementById('attachmentsFileList');
if (attachmentsInput && attachmentsDropzone) {
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
    attachmentsInput.addEventListener('change', function () { showSelectedAttachments(this.files); });
    attachmentsDropzone.addEventListener('click', () => attachmentsInput.click());
    attachmentsDropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); attachmentsInput.click(); }
    });
    attachmentsDropzone.addEventListener('dragover', event => { event.preventDefault(); attachmentsDropzone.classList.add('drag-over'); });
    attachmentsDropzone.addEventListener('dragleave', () => attachmentsDropzone.classList.remove('drag-over'));
    attachmentsDropzone.addEventListener('drop', event => {
        event.preventDefault();
        attachmentsDropzone.classList.remove('drag-over');
        if (event.dataTransfer.files.length) { attachmentsInput.files = event.dataTransfer.files; showSelectedAttachments(attachmentsInput.files); }
    });
}
</script>
@endpush
