@extends('layouts.reporting-user')
@section('title', $report->report_number)
@section('css')
<style>
    .reporter-receipt-wrap { max-width: 980px; margin-inline: auto; }
    .reporter-receipt-card { overflow: hidden; }
    .reporter-description { padding: 15px 17px; border: 1px solid #e2e8f0; border-radius: 9px; background: #f8fafc; color: #334155; font-size: .84rem; line-height: 1.75; overflow-wrap: anywhere; white-space: pre-line; }
    .reporter-attachment { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 9px; background: #f8fafc; }
    .reporter-attachment-name { color: #1e293b; font-size: .82rem; font-weight: 600; overflow-wrap: anywhere; }
    .reporter-attachment-meta { margin-top: 2px; color: #64748b; font-size: .72rem; }
    .reporter-attachment-view { min-height: 34px !important; padding: 6px 11px !important; font-size: .74rem !important; }
    @media(max-width:575.98px){.reporter-attachment{align-items:flex-start;flex-direction:column}.reporter-attachment-view{justify-content:center;width:100%}}
    .student-appeal-dropzone {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 28px 20px;
        border: 1.5px dashed #c7d2e8;
        border-radius: 14px;
        background: #f8faff;
        text-align: center;
        cursor: pointer;
        transition: border-color .15s ease, background-color .15s ease;
    }
    .student-appeal-dropzone:hover,
    .student-appeal-dropzone:focus-visible { border-color: #93b4f4; background: #f2f6ff; }
    .student-appeal-dropzone.is-dragover { border-color: #3156c7; background: #eef3ff; }
    .student-appeal-dropzone.has-files { border-style: solid; border-color: #93b4f4; }
    .student-appeal-dropzone-icon {
        display: grid;
        place-items: center;
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: #e7ecfb;
        color: #3156c7;
        font-size: 1.15rem;
    }
    .student-appeal-dropzone-text { max-width: 100%; overflow-wrap: anywhere; font-size: .86rem; color: #475569; }
    .student-appeal-dropzone-link { color: #3156c7; font-weight: 700; }
    .student-appeal-dropzone-hint { font-size: .74rem; color: #94a3b8; }
    .student-appeal-dropzone-files { display: none; align-items: center; gap: 6px; max-width: 100%; color: #1e293b; font-size: .8rem; font-weight: 600; }
    .student-appeal-dropzone-files span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .student-appeal-dropzone.has-files .student-appeal-dropzone-files { display: flex; }
    .student-appeal-dropzone.has-files .student-appeal-dropzone-text,
    .student-appeal-dropzone.has-files .student-appeal-dropzone-hint { display: none; }
</style>
@endsection
@section('content')
@php
    $statusLabels = [
        'pending_review' => ['label' => 'Pending Review', 'class' => 'prism-badge-orange'],
        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-blue'],
        'converted_to_case' => ['label' => 'Processed', 'class' => 'prism-badge-green'],
        'dismissed' => ['label' => 'Closed', 'class' => 'prism-badge-gray'],
    ];
    $sourceLabels = [
        'faculty_report' => ['label' => 'Faculty Report', 'class' => 'prism-badge-violet'],
        'security_report' => ['label' => 'Security Report', 'class' => 'prism-badge-navy'],
    ];
    $status = $statusLabels[$report->status] ?? ['label' => 'Closed', 'class' => 'prism-badge-gray'];
    $source = $sourceLabels[$report->source_type] ?? ['label' => ucwords(str_replace('_', ' ', $report->source_type)), 'class' => 'prism-badge-gray'];
@endphp

<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Dashboard', 'url' => route('reporter.dashboard')],
            ['label' => $report->report_number],
        ]" />
        <h1 class="prism-page-title">{{ $report->report_number }}</h1>
        <p class="prism-page-sub">Report submission receipt</p>
    </div>

    <div class="reporter-receipt-wrap">
            <div class="prism-card mb-4 reporter-receipt-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Report Details</h2>
                </div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Report No.</th>
                            <td class="prism-ref-number">{{ $report->report_number }}</td>
                        </tr>
                        <tr>
                            <th>Current Status</th>
                            <td><span class="prism-badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Report Source</th>
                            <td><span class="prism-badge {{ $source['class'] }}">{{ $source['label'] }}</span></td>
                        </tr>
                        <tr>
                            <th>Student Reported</th>
                            <td>
                                @if($report->student)
                                    {{ $report->student->list_name }}
                                    @if($report->student->studentProfile?->student_number)
                                        <span class="prism-ref-number" style="margin-left:6px;">{{ $report->student->studentProfile->student_number }}</span>
                                    @endif
                                @else
                                    <span class="text-muted-cell">Not identified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Incident Date and Time</th>
                            <td>
                                {{ $report->date_of_incident->format('F d, Y') }}
                                @if($report->time_of_incident)
                                    at {{ \Carbon\Carbon::parse($report->time_of_incident)->format('g:i A') }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Location</th>
                            <td>{{ $report->location ?: 'Not provided' }}</td>
                        </tr>
                    </table>

                    <hr class="prism-divider">

                    <p class="prism-label mb-2">Description</p>
                    <div class="reporter-description">{{ $report->description }}</div>
                </div>
            </div>

            <div class="prism-card mb-4 reporter-receipt-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Attachments</h2>
                </div>
                <div class="prism-card-body">
                    @if($report->attachments->isEmpty())
                        <div class="text-muted-cell" style="font-size:0.88rem;">No attachments submitted.</div>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($report->attachments as $attachment)
                                @php
                                    $iconMap = [
                                        'pdf' => 'bi-file-earmark-pdf text-danger',
                                        'jpg' => 'bi-file-earmark-image text-primary',
                                        'jpeg' => 'bi-file-earmark-image text-primary',
                                        'png' => 'bi-file-earmark-image text-primary',
                                    ];
                                    $icon = $iconMap[$attachment->file_type] ?? 'bi-file-earmark';
                                @endphp
                                <div class="reporter-attachment">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi {{ $icon }}" style="font-size:1.1rem;"></i>
                                        <div>
                                            <div class="reporter-attachment-name">{{ $attachment->file_name }}</div>
                                            <div class="reporter-attachment-meta">
                                                {{ strtoupper($attachment->file_type) }} &middot;
                                                {{ round($attachment->file_size_bytes / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button"
                                       class="prism-btn prism-btn-outline reporter-attachment-view"
                                       data-attachment-preview
                                       data-attachment-url="{{ route('reporter.reports.attachments.view', [$report, $attachment]) }}"
                                       data-attachment-name="{{ $attachment->file_name }}"
                                       data-attachment-type="{{ $attachment->file_type }}">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if($report->cases->isNotEmpty())
                @php
                    $caseStatusLabels = [
                        'open' => ['label' => 'Open', 'class' => 'prism-badge-blue'],
                        'pending_nte' => ['label' => 'Pending NTE', 'class' => 'prism-badge-orange'],
                        'awaiting_explanation' => ['label' => 'Awaiting Eval', 'class' => 'prism-badge-violet'],
                        'under_review' => ['label' => 'Under Review', 'class' => 'prism-badge-teal'],
                        'pending_upcc' => ['label' => 'Pending UPCC', 'class' => 'prism-badge-navy'],
                        'pending_resolution' => ['label' => 'Pending Resolution', 'class' => 'prism-badge-amber'],
                        'resolved' => ['label' => 'Resolved', 'class' => 'prism-badge-green'],
                        'dismissed' => ['label' => 'Dismissed', 'class' => 'prism-badge-gray'],
                        'voided' => ['label' => 'Voided', 'class' => 'prism-badge-red'],
                    ];
                @endphp
                @foreach($report->cases as $case)
                    @php
                        $referral = $case->upccReferral;
                        $caseStatus = $caseStatusLabels[$case->status] ?? ['label' => ucwords(str_replace('_', ' ', $case->status)), 'class' => 'prism-badge-gray'];
                    @endphp
                    <div class="prism-card mb-4 reporter-receipt-card">
                        <div class="prism-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                            <h2 class="prism-card-title">Related Case &mdash; {{ $case->case_number }}</h2>
                            <span class="prism-badge {{ $caseStatus['class'] }}">{{ $caseStatus['label'] }}</span>
                        </div>
                        @if(! $referral?->decision_distributed_at)
                            <div class="prism-card-body">
                                <div class="prism-note">
                                    <i class="bi bi-hourglass-split"></i>
                                    <div>This case is still being processed. The UPCC decision and any appeal window will appear here once a decision has been distributed.</div>
                                </div>
                            </div>
                        @else
                            <div class="prism-card-body">
                                <table class="prism-detail-table mb-3">
                                    <tr>
                                        <th>Distributed</th>
                                        <td>{{ $referral->decision_distributed_at->format('F d, Y \a\t g:i A') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Appeal Deadline</th>
                                        <td>
                                            {{ $referral->appeal_window_end?->format('F d, Y') ?? 'Not specified' }}
                                            @if($referral->isAppealWindowOpen())
                                                <span style="font-size:0.78rem;color:var(--orange);font-weight:700;margin-left:6px;">Open</span>
                                            @elseif($referral->appeal_window_end)
                                                <span style="font-size:0.78rem;color:var(--text-muted);margin-left:6px;">Closed</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($referral->appeal_filed)
                                        <tr>
                                            <th>Appeal Submitted</th>
                                            <td>{{ $referral->appeal_filed_at?->format('F d, Y \a\t g:i A') ?? 'Recorded' }}</td>
                                        </tr>
                                    @endif
                                </table>

                                <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:12px;">
                                    <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Decision Summary</div>
                                    <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $referral->decision_summary ?: 'The decision summary has not been provided.' }}</div>
                                </div>

                                @if($referral->appeal_filed)
                                    <div class="prism-note mb-3">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        <div>Your appeal has been recorded and is awaiting reconsideration review.</div>
                                    </div>
                                    @if($referral->appeal_notes)
                                        @php $appealNotesParts = $referral->appealNotesParts(); @endphp
                                        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;">
                                            <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">Appeal Notes</div>
                                            <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $appealNotesParts['original'] }}</div>
                                            @if($appealNotesParts['reconsideration_summary'])
                                                <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);">
                                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px;">
                                                        <span style="font-size:0.7rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;">Reconsideration Outcome</span>
                                                        <span style="font-size:0.72rem;color:var(--text-muted);">{{ $appealNotesParts['reconsideration_date'] }}</span>
                                                    </div>
                                                    <div style="font-size:0.875rem;line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;">{{ $appealNotesParts['reconsideration_summary'] }}</div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                    @if($referral->appealAttachments->isNotEmpty())
                                        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-top:12px;">
                                            <div style="font-size:0.74rem;font-weight:700;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-bottom:8px;">Supporting Attachments</div>
                                            @foreach($referral->appealAttachments as $attachment)
                                                <div style="display:flex;align-items:center;gap:8px;{{ !$loop->last ? 'margin-bottom:8px;' : '' }}">
                                                    <i class="bi bi-paperclip"></i>
                                                    <button type="button"
                                                       class="prism-action-link"
                                                       style="background:none;border:0;padding:0;font:inherit;"
                                                       data-attachment-preview
                                                       data-attachment-url="{{ route('reporter.cases.appeal-attachments.view', [$case, $attachment]) }}"
                                                       data-attachment-name="{{ $attachment->file_name }}"
                                                       data-attachment-type="{{ $attachment->file_type }}">{{ $attachment->file_name }}</button>
                                                    <span class="text-muted-cell" style="font-size:0.76rem;">{{ number_format($attachment->file_size_bytes / 1024, 1) }} KB</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @elseif($referral->isAppealWindowOpen())
                                    <div class="prism-note" style="background:#fffaf0;border-color:#f1d58a;color:#7c4a12;">
                                        <i class="bi bi-info-circle"></i>
                                        <div>
                                            If you intend to appeal, submit your appeal before
                                            <strong>{{ $referral->appeal_window_end?->format('F d, Y') }}</strong>.
                                        </div>
                                    </div>
                                    <form method="POST"
                                          action="{{ route('reporter.cases.appeal.store', $case) }}"
                                          enctype="multipart/form-data"
                                          class="mt-3"
                                          onsubmit="return confirm('Submit this appeal for reconsideration? You cannot submit another appeal for this decision.');">
                                        @csrf
                                        <label class="prism-label">
                                            Appeal Grounds / Reason <span class="text-danger">*</span>
                                        </label>
                                        <textarea name="appeal_notes"
                                                  class="prism-input"
                                                  rows="5"
                                                  minlength="20"
                                                  maxlength="5000"
                                                  required
                                                  placeholder="State the grounds for your appeal or request for reconsideration. Include the specific part of the decision you are appealing and any supporting context.">{{ old('appeal_notes') }}</textarea>
                                        @error('appeal_notes')
                                            <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                        @enderror
                                        <div class="mt-3">
                                            <label class="prism-label">
                                                Supporting Attachments
                                                <span class="text-muted" style="font-weight:400;">(optional)</span>
                                            </label>
                                            <label for="appealAttachments" class="student-appeal-dropzone" id="appealDropzone">
                                                <div class="student-appeal-dropzone-icon"><i class="bi bi-file-earmark-arrow-up"></i></div>
                                                <div class="student-appeal-dropzone-text">
                                                    <span class="student-appeal-dropzone-link">Click here</span> to upload your files or drag and drop.
                                                </div>
                                                <div class="student-appeal-dropzone-hint">PDF, JPG, PNG, DOC, or DOCX · Up to 3 files, 5 MB each</div>
                                                <div class="student-appeal-dropzone-files">
                                                    <i class="bi bi-paperclip"></i>
                                                    <span id="appealFileNames"></span>
                                                </div>
                                            </label>
                                            <input id="appealAttachments"
                                                   type="file"
                                                   name="attachments[]"
                                                   class="visually-hidden"
                                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                                   multiple>
                                            @error('attachments')
                                                <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                            @enderror
                                            @error('attachments.*')
                                                <div style="font-size:0.78rem;color:var(--red);margin-top:6px;">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="d-flex justify-content-end mt-3">
                                            <button type="submit" class="prism-btn prism-btn-primary">
                                                Submit Appeal
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <div class="prism-note">
                                        <i class="bi bi-lock"></i>
                                        <div>The appeal window for this decision is no longer open.</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            @endif
    </div>
</div>

@include('partials.attachment-preview-modal')
@endsection

@push('scripts')
<script>
(() => {
    const appealDropzone = document.getElementById('appealDropzone');
    const appealFileInput = document.getElementById('appealAttachments');
    const appealFileNames = document.getElementById('appealFileNames');

    if (appealDropzone && appealFileInput && appealFileNames) {
        const showSelectedFiles = () => {
            const files = Array.from(appealFileInput.files ?? []);
            appealDropzone.classList.toggle('has-files', files.length > 0);
            appealFileNames.textContent = files.map((file) => file.name).join(', ');
        };

        appealFileInput.addEventListener('change', showSelectedFiles);

        ['dragenter', 'dragover'].forEach((eventName) => {
            appealDropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                appealDropzone.classList.add('is-dragover');
            });
        });

        ['dragleave', 'dragend', 'drop'].forEach((eventName) => {
            appealDropzone.addEventListener(eventName, (event) => {
                event.preventDefault();
                appealDropzone.classList.remove('is-dragover');
            });
        });

        appealDropzone.addEventListener('drop', (event) => {
            const droppedFiles = event.dataTransfer?.files;
            if (droppedFiles?.length) {
                appealFileInput.files = droppedFiles;
                showSelectedFiles();
            }
        });
    }
})();
</script>
@endpush
