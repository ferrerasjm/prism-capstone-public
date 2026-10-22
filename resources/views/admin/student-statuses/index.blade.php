@extends('layouts.admin')
@php($activeNav = 'users')
@section('title', 'Update Student Statuses')
@section('css')
<style>
    .status-upload { position:relative; display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:150px; padding:22px 20px; text-align:center; background:#FAFBFF; border:1.5px dashed #AEBCE7; border-radius:12px; transition:border-color .18s,background .18s,box-shadow .18s; }
    .status-upload:hover,.status-upload:focus-within,.status-upload.drag-over { background:#F3F6FF; border-color:#3156C7; box-shadow:0 0 0 3px rgba(49,86,199,.08); }
    .status-upload.has-files { background:#F0FDF4; border-color:#4ADE80; }
    .status-upload input { position:absolute; inset:0; width:100%; height:100%; opacity:0; cursor:pointer; }
    .status-upload-icon { color:#6077C9; font-size:2.2rem; }
    .status-upload-label { margin:10px 0 2px; color:#172033; font-size:.88rem; font-weight:650; }
    .status-upload-hint { margin:0; color:#64748B; font-size:.76rem; }
    .status-upload-file { display:inline-block; max-width:100%; margin-top:12px; padding:5px 10px; overflow:hidden; color:#166534; background:#DCFCE7; border-radius:7px; font-size:.76rem; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }
</style>
@endsection
@section('content')
<div class="sdo-page-header mb-4">
    <x-breadcrumbs link-class="sdo-back-link" :items="[['label' => 'User Management', 'url' => route('admin.users.index')], ['label' => 'Update Student Statuses']]" />
    <h1 class="sdo-page-title">Update Student Statuses</h1>
    <p class="sdo-page-subtitle">Upload Registrar-confirmed statuses, review the changes, then confirm once.</p>
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="sdo-card"><div class="sdo-card-header">Before You Start</div><div class="sdo-card-body">
            <p class="small text-muted">Get the confirmed statuses from the Registrar, upload a CSV, then preview and confirm the changes.</p>
            <p class="sdo-form-label">CSV Example</p>
            <pre class="small bg-light border rounded p-3">student_number,student_status
2099-0000002,enrolled
2099-0000003,graduated</pre>
            <p class="small text-muted mb-2">Accepted values: <code>enrolled</code>, <code>graduated</code>, <code>transferred</code>, <code>withdrawn</code>, <code>not_enrolled</code>.</p>
            <p class="small text-muted mb-0">Only listed students are updated. Departures disable access; separate suspensions and disciplinary records are preserved.</p>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="sdo-card"><div class="sdo-card-header">{{ $student ? 'Update '.$student->list_name : 'Upload Student Statuses' }}</div><div class="sdo-card-body">
            @if($errors->any())<div class="sdo-alert sdo-alert-danger mb-3" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.student-statuses.store') }}" enctype="multipart/form-data" id="statusUploadForm">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label for="period" class="sdo-form-label">Academic Period <span class="text-danger">*</span></label><select id="period" name="academic_period_id" class="sdo-form-select" required><option value="">Select a term</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(old('academic_period_id') == $period->id)>{{ $period->label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label for="date" class="sdo-form-label">Effective Date <span class="text-danger">*</span></label><input id="date" name="effective_on" type="date" class="sdo-form-control" max="{{ today()->toDateString() }}" value="{{ old('effective_on', today()->toDateString()) }}" required><p class="small text-muted mt-2 mb-0">Registrar-confirmed date. Access changes when you confirm.</p></div>
                    <div class="col-12"><label for="source" class="sdo-form-label">Registrar Source <span class="text-danger">*</span></label><input id="source" name="source_reference" class="sdo-form-control" maxlength="255" value="{{ old('source_reference') }}" placeholder="e.g., Registrar email dated September 10, 2026" required></div>
                    @if($student)
                        <input type="hidden" name="student_number" value="{{ $student->studentProfile?->student_number }}">
                        <div class="col-12"><label for="student-status" class="sdo-form-label">Student Status <span class="text-danger">*</span></label><select id="student-status" name="student_status" class="sdo-form-select" required><option value="">Select confirmed status</option>@foreach(\App\Models\User::STUDENT_STATUSES as $value => $label)<option value="{{ $value }}" @selected(old('student_status') === $value)>{{ $label }}</option>@endforeach</select></div>
                    @else
                        <div class="col-12">
                            <label for="csv" class="sdo-form-label">Student Status File (CSV) <span class="text-danger">*</span></label>
                            <div class="status-upload" id="status-upload">
                                <i class="bi bi-file-earmark-arrow-up status-upload-icon" aria-hidden="true"></i>
                                <p class="status-upload-label">Choose CSV File</p>
                                <p class="status-upload-hint">or drag and drop it here</p>
                                <span id="csv-name" class="status-upload-file d-none" aria-live="polite"></span>
                                <input id="csv" type="file" name="csv_file" accept=".csv,.txt" aria-describedby="csv-help" required>
                            </div>
                            <p id="csv-help" class="small text-muted mt-2 mb-0">Maximum 5 MB and 20,000 students. Unknown student numbers are flagged for checking.</p>
                        </div>
                    @endif
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-top mt-3 pt-3"><span class="small text-muted">Review proposed changes first. Accounts change only after confirmation.</span><button class="btn sdo-btn-primary sdo-btn-cta" type="submit" id="statusUploadSubmit">Preview Changes</button></div>
            </form>
        </div></div>
    </div>
</div>

<div class="sdo-card"><div class="sdo-card-header">Update History</div>
    <div class="table-responsive"><table class="sdo-table"><thead><tr><th>Period</th><th>Effective Date</th><th>Source</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($batches as $batch)<tr><td>{{ $batch->period->label }}</td><td>{{ $batch->effective_on->format('M d, Y') }}</td><td>{{ $batch->source_reference }}</td><td><span class="{{ $batch->applied_at ? 'sdo-badge-success' : 'sdo-badge-warning' }}">{{ $batch->applied_at ? 'Applied' : 'Awaiting Confirmation' }}</span></td><td><a class="sdo-link-sm" href="{{ route('admin.student-statuses.show', $batch) }}">View</a></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-5">No student status updates yet.</td></tr>@endforelse
    </tbody></table></div><div class="sdo-table-pagination prism-pagination"><x-pagination :paginator="$batches" :show-per-page="false" noun="updates" /></div>
</div>
@endsection
@push('scripts')
<script>
(() => {
    const input = document.getElementById('csv');
    if (!input) return;
    const zone = document.getElementById('status-upload');
    const name = document.getElementById('csv-name');
    const update = () => {
        const file = input.files[0];
        const valid = file && /\.(csv|txt)$/i.test(file.name) && file.size <= 5 * 1024 * 1024;
        input.setCustomValidity(file && !valid ? 'Choose a CSV file no larger than 5 MB.' : '');
        name.textContent = file ? file.name : '';
        name.classList.toggle('d-none', !file);
        zone.classList.toggle('has-files', Boolean(valid));
        if (file && !valid) input.reportValidity();
    };
    input.addEventListener('change', update);
    zone.addEventListener('dragover', event => { event.preventDefault(); zone.classList.add('drag-over'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', event => {
        event.preventDefault();
        zone.classList.remove('drag-over');
        if (event.dataTransfer.files.length !== 1) {
            input.setCustomValidity('Choose one CSV file at a time.');
            input.reportValidity();
            return;
        }
        input.files = event.dataTransfer.files;
        update();
    });
})();

/* A rejected upload used to redirect back and reload the whole page just to show a message
 * the server already knew before the click. Submitting through fetch keeps the selected file
 * and form values in place and surfaces the same validation messages as toasts instead. */
(() => {
    const form = document.getElementById('statusUploadForm');
    if (!form) return;
    const submitBtn = document.getElementById('statusUploadSubmit');
    const submitBtnDefaultLabel = submitBtn.innerHTML;

    const setSubmitting = (submitting) => {
        submitBtn.disabled = submitting;
        submitBtn.innerHTML = submitting
            ? '<span class="spinner-border spinner-border-sm me-2"></span>Validating...'
            : submitBtnDefaultLabel;
    };

    const showErrors = (errors) => {
        Object.values(errors || {}).flat().forEach((message) => {
            window.PrismToast.show(message, 'danger');
        });
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        setSubmitting(true);

        let response;
        try {
            response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: new FormData(form),
            });
        } catch (networkError) {
            // Fetch itself failed (offline, blocked) - fall back to a real submit rather than
            // leaving the click looking like it did nothing.
            form.submit();
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            showErrors(data.errors || {});
            setSubmitting(false);
            return;
        }

        if (!response.ok) {
            form.submit();
            return;
        }

        // Success: the server redirected to the preview page, so follow it there.
        window.location.href = response.url || form.action;
    });
})();
</script>
@endpush
