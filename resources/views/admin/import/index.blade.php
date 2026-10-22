@extends('layouts.admin')

@section('title', 'Bulk Student Import')


@section('css')
<style>
    .sdo-import-page { max-width: 1280px; margin-inline: auto; }
    .sdo-import-page .sdo-card { height: 100%; }
    .sdo-import-page .sdo-import-guide { margin-bottom: 0; }
    .sdo-import-page .sdo-card-header { color: #111827; padding: 15px 18px; }
    .sdo-import-step { display: inline-flex; align-items: center; justify-content: center; width: 25px; height: 25px; margin-right: 9px; border-radius: 50%; background: #e8edff; color: #2f4fbf; font-size: .76rem; font-weight: 700; }
    .sdo-import-section-copy { margin: 0 0 18px; color: #64748b; font-size: .83rem; line-height: 1.55; }
    .sdo-import-page .sdo-dropzone { min-height: 210px; padding: 34px 24px; background: #fafbff; border: 1.5px dashed #aebce7; border-radius: 12px; transition: border-color .18s, background .18s, box-shadow .18s; }
    .sdo-import-page .sdo-dropzone:hover,
    .sdo-import-page .sdo-dropzone:focus-visible,
    .sdo-import-page .sdo-dropzone.drag-over { background: #f3f6ff; border-color: #2f4fbf; box-shadow: 0 0 0 3px rgba(47,79,191,.08); outline: none; }
    .sdo-import-page .sdo-dropzone.has-file { background: #f0fdf4; border-color: #4ade80; }
    .sdo-import-page .sdo-dropzone-icon { color: #6077c9; font-size: 2.5rem; }
    .sdo-import-page .sdo-dropzone-label { margin: 12px 0 3px; color: #172033; font-size: .9rem; font-weight: 650; }
    .sdo-import-page .sdo-dropzone-hint,
    .sdo-import-page .sdo-hint-text { color: #64748b; font-size: .76rem; }
    .sdo-import-page .sdo-dropzone-filename { display: inline-block; max-width: 100%; margin-top: 12px; padding: 6px 10px; overflow: hidden; color: #166534; background: #dcfce7; border-radius: 7px; font-size: .78rem; font-weight: 600; text-overflow: ellipsis; white-space: nowrap; }
    .sdo-import-safety { display: flex; gap: 9px; margin-top: 16px; padding: 11px 12px; color: #44516a; background: #f8fafc; border: 1px solid #e5eaf1; border-radius: 9px; font-size: .76rem; line-height: 1.45; }
    .sdo-import-safety i { flex: 0 0 auto; color: #2f4fbf; }
    .sdo-import-upload { display: flex; flex-direction: column; }
    .sdo-import-upload .sdo-card-body { display: flex; flex: 1; flex-direction: column; }
    .sdo-import-upload #importForm { display: flex; flex: 1; flex-direction: column; }
    .sdo-import-upload .sdo-dropzone { display: flex; flex: 1; min-height: 310px; flex-direction: column; align-items: center; justify-content: center; }
    .sdo-import-submit { align-self: center; width: auto; min-width: 210px; }
    .sdo-column-list { overflow: hidden; border: 1px solid #e4e9f1; border-radius: 10px; }
    .sdo-column-row { display: grid; grid-template-columns: minmax(120px, 1fr) 86px 1.25fr; gap: 12px; align-items: center; padding: 10px 12px; border-bottom: 1px solid #edf0f5; font-size: .78rem; }
    .sdo-column-row:last-child { border-bottom: 0; }
    .sdo-column-name { color: #253354; font-family: monospace; font-weight: 700; }
    .sdo-field-tag { width: fit-content; padding: 3px 7px; border-radius: 99px; font-size: .67rem; font-weight: 700; }
    .sdo-field-required { color: #9f1239; background: #ffe4e6; }
    .sdo-field-optional { color: #475569; background: #f1f5f9; }
    .sdo-column-description { color: #64748b; }
    .sdo-import-example { margin-top: 16px; padding: 13px 14px; background: #f7f8fc; border: 1px solid #e4e9f1; border-radius: 10px; }
    .sdo-import-example-title { margin-bottom: 8px; color: #34415c; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    /* Direct child only: a nested inline <code> was being turned into a block and pushed
       onto its own line mid-sentence. */
    .sdo-import-example > code {
        display: block;
        overflow-x: auto;
        color: #284183;
        font-family: ui-monospace, SFMono-Regular, "Cascadia Mono", Menlo, Consolas, monospace;
        font-size: .8rem;
        line-height: 1.9;
        white-space: nowrap;
    }
    .sdo-email-rule { margin: 12px 0 0; color: #64748b; font-size: .76rem; line-height: 1.5; }
    .sdo-email-rule code { color: #284183; background: #edf1ff; padding: 2px 5px; border-radius: 4px; }
    .sdo-import-page .sdo-history-meta { color: #64748b; font-size: .75rem; }
    .sdo-import-page .sdo-history-meta code { color: #284183; }
    .sdo-import-page .sdo-info-box { background: #eef4ff; border-color: #d6e2ff; color: #344b91; }
    .sdo-import-history { margin-top: 24px; }
    .sdo-import-page .sdo-empty-state { color: #64748b !important; }
    .sdo-import-page .sdo-link-sm { color: #2f4fbf; }
    .sdo-history-file { display: flex; align-items: center; gap: 9px; min-width: 180px; }
    .sdo-history-file-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex: 0 0 auto; color: #15803d; background: #ecfdf3; border-radius: 7px; }
    @media (max-width: 767.98px) {
        .sdo-column-row { grid-template-columns: 1fr auto; }
        .sdo-column-description { grid-column: 1 / -1; }
    }
</style>
@endsection

@section('content')
<div class="sdo-info-box mb-3"><span>This import creates new student accounts. To apply Registrar-confirmed enrollment or departure changes to existing accounts, use <a href="{{ route('admin.student-statuses.index') }}">Update Student Statuses</a>.</span></div>
<div class="sdo-page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <x-breadcrumbs link-class="sdo-back-link" :items="[
            ['label' => 'User Management', 'url' => route('admin.users.index')],
            ['label' => 'Bulk Student Import'],
        ]" />
        <h1 class="sdo-page-title mb-0">Bulk Student Import</h1>
        <p class="sdo-page-subtitle mb-0">Create student accounts from a CSV file</p>
    </div>
    {{-- download: the file arrives without navigating away, so pagehide never fires and the
         progress bar would spin forever. initPageLoader skips links carrying this attribute. --}}
    <a href="{{ route('admin.import.template') }}" download class="btn sdo-btn-primary sdo-btn-cta">
        <i class="bi bi-download me-1"></i>Download Blank CSV
    </a>
</div>

<div class="sdo-import-page">
<div class="row g-4">
<div class="col-12 col-lg-7">
<div class="sdo-card sdo-import-guide">
    <div class="sdo-card-header">
        <span class="sdo-import-step">1</span>Prepare your CSV file
    </div>
    <div class="sdo-card-body">
        <p class="sdo-import-section-copy">Use these exact column names in the first row. Keep one student per row.</p>

        <div class="sdo-column-list">
            <div class="sdo-column-row">
                <span class="sdo-column-name">student_number</span>
                <span class="sdo-field-tag sdo-field-required">Required</span>
                <span class="sdo-column-description">The student&rsquo;s unique ID number</span>
            </div>
            <div class="sdo-column-row">
                <span class="sdo-column-name">last_name</span>
                <span class="sdo-field-tag sdo-field-required">Required</span>
                <span class="sdo-column-description">Student&rsquo;s family name</span>
            </div>
            <div class="sdo-column-row">
                <span class="sdo-column-name">first_name</span>
                <span class="sdo-field-tag sdo-field-required">Required</span>
                <span class="sdo-column-description">Student&rsquo;s given name</span>
            </div>
            <div class="sdo-column-row">
                <span class="sdo-column-name">email</span>
                <span class="sdo-field-tag sdo-field-required">Required</span>
                <span class="sdo-column-description">Official @students.nu-clark.edu.ph address</span>
            </div>
            <div class="sdo-column-row">
                <span class="sdo-column-name">middle_name</span>
                <span class="sdo-field-tag sdo-field-optional">Optional</span>
                <span class="sdo-column-description">Leave blank when not applicable</span>
            </div>
        </div>

        <div class="sdo-import-example">
            <div class="sdo-import-example-title">CSV example</div>
            <code>
                student_number,last_name,first_name,email,middle_name<br>
                2099-0000001,Student,Demo One,demo-user-001@students.nu-clark.edu.ph,<br>
                2099-0000002,Student,Demo Two,demo-user-002@students.nu-clark.edu.ph,
            </code>
            <p class="sdo-history-meta mt-2 mb-0">
                Use the student&rsquo;s actual registered address; PRISM no longer guesses email formats.
            </p>
        </div>

        <div class="sdo-info-box mt-3">
            <i class="bi bi-arrow-repeat me-2"></i>
            <span class="sdo-import-note">
                Import is <strong>additive only</strong> — existing student numbers are skipped, never overwritten.
                Each created account receives a queued one-time activation link.
            </span>
        </div>
    </div>
</div>
</div>

    <div class="col-12 col-lg-5">
        <div class="sdo-card sdo-import-upload">
            <div class="sdo-card-header">
                <span class="sdo-import-step">2</span>Upload and import
            </div>
            <div class="sdo-card-body">
                <p class="sdo-import-section-copy">
                    Select your completed CSV file. Activation emails are queued after successful account creation.
                </p>

                @if($errors->any())
                    <div class="sdo-alert sdo-alert-danger mb-3">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>{{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.import.store') }}"
                      enctype="multipart/form-data" id="importForm">
                    @csrf

                    <div class="sdo-dropzone" id="dropzone" role="button" tabindex="0"
                         aria-controls="csvFileInput" aria-label="Select or drop a CSV file">
                        <i class="bi bi-file-earmark-spreadsheet sdo-dropzone-icon"></i>
                        <p class="sdo-dropzone-label">Choose a CSV file</p>
                        <p class="sdo-dropzone-hint">or drag and drop it here</p>
                        <span id="selectedFileName" class="sdo-dropzone-filename d-none"></span>
                    </div>

                    <input type="file" name="csv_file" id="csvFileInput"
                           accept=".csv,.txt" class="d-none" required>

                    <p class="sdo-hint-text mt-2 mb-0">CSV files only &middot; Maximum file size: 5 MB</p>

                    <button type="submit" class="btn sdo-btn-primary sdo-btn-cta sdo-import-submit mt-3" id="submitBtn" disabled>
                        <i class="bi bi-cloud-upload"></i>Import Students
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

    <div class="sdo-card sdo-import-history">
            <div class="sdo-card-header sdo-card-header--blue d-flex align-items-center justify-content-between">
                <span>Import history</span>
            </div>
            <div class="sdo-card-body p-0">
                @if($history->isEmpty())
                    <div class="text-center py-5 sdo-empty-state">
                        No import batches yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="sdo-table">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Imported By</th>
                                    <th class="text-center">Created</th>
                                    <th class="text-center">Skipped</th>
                                    <th class="text-center">Errors</th>
                                    <th>Date</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($history as $log)
                                    <tr>
                                        <td>
                                            <div class="sdo-history-file">
                                                <span class="sdo-history-file-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
                                                <span style="font-size:.82rem;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->file_name }}">{{ $log->file_name }}</span>
                                            </div>
                                        </td>
                                        <td style="font-size:0.83rem;">{{ $log->importedBy?->list_name ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="sdo-badge-success">{{ $log->created_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="sdo-badge-neutral">{{ $log->skipped_count }}</span>
                                        </td>
                                        <td class="text-center">
                                            @if($log->error_count > 0)
                                                <span class="sdo-badge-danger">{{ $log->error_count }}</span>
                                            @else
                                                <span class="sdo-badge-neutral">0</span>
                                            @endif
                                        </td>
                                        <td style="font-size:0.8rem;color:#94A3B8;white-space:nowrap;">
                                            {{ $log->created_at->format('M d, Y \a\t g:i A') }}
                                        </td>
                                        <td>
                                            <a href="{{ route('admin.import.show', $log->import_batch_id) }}"
                                               class="sdo-link-sm">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="sdo-table-pagination prism-pagination"><x-pagination :paginator="$history" /></div>
                @endif
            </div>
        </div>
</div>
@endsection

@push('scripts')
<script>
const input      = document.getElementById('csvFileInput');
const dropzone   = document.getElementById('dropzone');
const fileName   = document.getElementById('selectedFileName');
const submitBtn  = document.getElementById('submitBtn');

const showSelectedFile = file => {
    fileName.textContent = file.name;
    fileName.classList.remove('d-none');
    dropzone.classList.add('has-file');
    submitBtn.disabled = false;
};

input.addEventListener('change', function () {
    if (this.files.length > 0) {
        showSelectedFile(this.files[0]);
    }
});

dropzone.addEventListener('click', () => input.click());
dropzone.addEventListener('keydown', event => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        input.click();
    }
});
dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file && (file.name.toLowerCase().endsWith('.csv') || file.type === 'text/csv')) {
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showSelectedFile(file);
    }
});

document.getElementById('importForm').addEventListener('submit', function() {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
});
</script>
@endpush
