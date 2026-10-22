@extends('layouts.staff')

@section('title', $report->report_number)

@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Incident Reports', 'url' => route('officer.incidents.index')],
            ['label' => $report->report_number],
        ]" />
        <div class="d-flex align-items-start justify-content-between">
            <div>
                <h1 class="prism-page-title">{{ $report->report_number }}</h1>
                <p class="prism-page-sub">
                    Submitted {{ $report->created_at->format('F d, Y \a\t g:i A') }}
                </p>
            </div>
            <div class="d-flex gap-2 mt-2">
                @if($report->cases->isNotEmpty())
                    @if($report->cases->count() === 1)
                        <a href="{{ route('officer.cases.show', $report->cases->first()) }}"
                           class="prism-btn prism-btn-primary">
                            Go to Case
                        </a>
                    @else
                        <div class="dropdown">
                            <button class="prism-btn prism-btn-primary dropdown-toggle"
                                    type="button"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                Go to Case
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-1" style="min-width:170px;">
                                @foreach($report->cases as $case)
                                    <a class="dropdown-item rounded-2"
                                       href="{{ route('officer.cases.show', $case) }}">
                                        {{ $case->case_number }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif

                @if(!in_array($report->status, ['converted_to_case', 'dismissed']))
                    <button type="button" class="prism-btn prism-btn-blue-outline"
                            data-bs-toggle="modal" data-bs-target="#dismissModal">
                        Dismiss Report
                    </button>

                    @if($report->student_id || $report->subjects->count() > 0)
                        <button type="button" class="prism-btn prism-btn-primary"
                                data-bs-toggle="modal" data-bs-target="#convertToCaseModal">
                            Convert to Case
                        </button>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="row g-4">

        <div class="col-12 col-lg-7">

            <div class="prism-card {{ $report->attachments->isNotEmpty() ? 'mb-4' : 'h-100' }}">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Report Details</h2>
                    @php
                        $sourceLabels = [
                            'violation_slip'   => ['label' => 'Violation Slip',    'class' => 'prism-badge-orange'],
                            'student_complaint'=> ['label' => 'Student Complaint', 'class' => 'prism-badge-blue'],
                            'faculty_report'   => ['label' => 'Faculty Report',    'class' => 'prism-badge-violet'],
                            'security_report'  => ['label' => 'Security Report',   'class' => 'prism-badge-navy'],
                            'direct_encoding'  => ['label' => 'Direct Encoding',   'class' => 'prism-badge-gray'],
                        ];
                        $src = $sourceLabels[$report->source_type] ?? ['label' => $report->source_type, 'class' => 'prism-badge-gray'];
                    @endphp
                    <span class="prism-badge {{ $src['class'] }}">{{ $src['label'] }}</span>
                </div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Report No.</th>
                            <td class="prism-ref-number">{{ $report->report_number }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if($report->status === 'pending_review')
                                    <span class="prism-badge prism-badge-orange">Pending Review</span>
                                @elseif($report->status === 'under_review')
                                    <span class="prism-badge prism-badge-blue">Under Review</span>
                                @elseif($report->status === 'converted_to_case')
                                    <span class="prism-badge prism-badge-green">Converted to Case</span>
                                @else
                                    <span class="prism-badge prism-badge-gray">Dismissed</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Reported By</th>
                            <td>
                                @if($report->reportedByUser)
                            {{ $report->reportedByUser->list_name }}
                                    <span class="prism-cell-sub">
                                        ({{ ucwords(str_replace('_', ' ', $report->reportedByUser->role)) }})
                                    </span>
                                @elseif($report->reported_by_name)
                                    {{ $report->reported_by_name }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Incident Date</th>
                            <td>
                                {{ \Carbon\Carbon::parse($report->date_of_incident)->format('F d, Y') }}
                                @if($report->time_of_incident)
                                    at {{ \Carbon\Carbon::parse($report->time_of_incident)->format('g:i A') }}
                                @endif
                            </td>
                        </tr>
                        @if($report->location)
                            <tr>
                                <th>Location</th>
                                <td>{{ $report->location }}</td>
                            </tr>
                        @endif
                        @if($report->violation_type)
                            <tr>
                                <th>Violation Type</th>
                                <td style="font-size:0.84rem;">{{ $report->violation_type }}</td>
                            </tr>
                        @endif
                        @if($report->status === 'dismissed' && $report->dismissal_reason)
                            <tr>
                                <th>Dismissal Reason</th>
                                <td style="color:var(--red);font-size:0.84rem;">
                                    {{ $report->dismissal_reason }}
                                </td>
                            </tr>
                        @endif
                    </table>

                    <hr class="prism-divider">

                    <p class="prism-label mb-2">Incident Description</p>
                    <div style="font-size:0.875rem;color:var(--text-primary);line-height:1.7;white-space:pre-line;overflow-wrap:anywhere;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;">{{ $report->description }}</div>
                </div>
            </div>

            @if($report->attachments->isNotEmpty())
                <div class="prism-card mb-4">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">
                            Attachments
                        </h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="d-flex flex-column gap-2">
                            @foreach($report->attachments as $att)
                                <div class="d-flex align-items-center justify-content-between"
                                     style="padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);">
                                    <div class="d-flex align-items-center gap-2">
                                        @php
                                            $iconMap = ['pdf' => 'bi-file-earmark-pdf text-danger', 'jpg' => 'bi-file-earmark-image text-primary', 'jpeg' => 'bi-file-earmark-image text-primary', 'png' => 'bi-file-earmark-image text-primary'];
                                            $icon = $iconMap[$att->file_type] ?? 'bi-file-earmark';
                                        @endphp
                                        <i class="bi {{ $icon }}" style="font-size:1.1rem;"></i>
                                        <div>
                                            <div style="font-size:0.84rem;font-weight:600;">{{ $att->file_name }}</div>
                                            <div style="font-size:0.75rem;color:var(--text-muted);">
                                                {{ strtoupper($att->file_type) }} &middot;
                                                {{ round($att->file_size_bytes / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button"
                                       class="prism-btn prism-btn-outline"
                                       style="padding:6px 10px;font-size:0.76rem;"
                                       data-attachment-preview
                                       data-attachment-url="{{ route('officer.incidents.attachments.view', [$report, $att]) }}"
                                       data-attachment-name="{{ $att->file_name }}"
                                       data-attachment-type="{{ $att->file_type }}">
                                        View
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>

        <div class="col-12 col-lg-5 d-flex flex-column">

            <div class="prism-card order-2">
                <div class="prism-card-header d-flex align-items-center justify-content-between">
                    <h2 class="prism-card-title">Student Subject(s)</h2>
                    @if(!in_array($report->status, ['converted_to_case', 'dismissed']))
                        <button type="button" class="prism-btn prism-btn-blue-outline prism-btn-sm"
                                style="min-height:32px;padding:6px 10px;"
                                data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                            <i class="bi bi-plus-lg"></i> Add Subject
                        </button>
                    @endif
                </div>
                <div class="prism-card-body">
                    @php
                        $subjectRows = collect();
                        if ($report->student) {
                            $subjectRows->push([
                                'student' => $report->student,
                                'subject_id' => null,
                                'is_primary' => true,
                            ]);
                        }

                        foreach ($report->subjects as $subject) {
                            if (! $subject->student) {
                                continue;
                            }

                            $alreadyListed = $subjectRows->contains(function ($row) use ($subject) {
                                return (int) $row['student']->id === (int) $subject->student->id;
                            });

                            if (! $alreadyListed) {
                                $subjectRows->push([
                                    'student' => $subject->student,
                                    'subject_id' => $subject->id,
                                    'is_primary' => false,
                                ]);
                            }
                        }
                    @endphp

                    @if($subjectRows->isNotEmpty())
                        @foreach($subjectRows as $row)
                            @php
                                $student = $row['student'];
                                $canRemove = ! $row['is_primary'] && ! in_array($report->status, ['converted_to_case', 'dismissed'], true);
                            @endphp
                            <div class="d-flex align-items-center justify-content-between gap-3 {{ !$loop->last ? 'mb-3' : '' }}">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="prism-avatar prism-avatar-sm">
                                        {{ strtoupper(substr($student->first_name,0,1).substr($student->last_name,0,1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight:600;font-size:0.875rem;">{{ $student->list_name }}</div>
                                        <div class="prism-cell-sub prism-ref-number">{{ $student->studentProfile?->student_number }}</div>
                                        <div class="prism-cell-sub">
                                            {{ $student->studentProfile?->program ?? 'Program not set' }}
                                            @if($student->studentProfile?->year_level)
                                                &middot; {{ $student->studentProfile->year_level }}
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($canRemove)
                                    <button type="button" class="prism-btn prism-btn-outline"
                                            style="padding:6px 10px;font-size:0.76rem;"
                                            data-bs-toggle="modal" data-bs-target="#removeSubjectModal"
                                            data-subject-id="{{ $row['subject_id'] }}"
                                            data-subject-name="{{ $student->list_name }}">
                                        Remove
                                    </button>
                                @elseif($row['is_primary'])
                                    <span class="prism-badge prism-badge-gray" style="font-size:0.66rem;">Primary</span>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-3">
                            <p style="font-size:0.84rem;color:var(--text-muted);margin:0;">
                                No student subject identified in this report.
                            </p>
                            @if(!in_array($report->status, ['converted_to_case', 'dismissed']))
                                <p style="font-size:0.78rem;color:var(--text-muted);margin-top:6px;">
                                    A subject must be identified before this report can be converted to a case.
                                </p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <div class="prism-card mb-4 order-1">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Review Trail</h2>
                </div>
                <div class="prism-card-body">
                    @if($reviewTrail->isEmpty())
                        <p class="mb-0" style="font-size:0.84rem;color:var(--text-muted);">No review activity logged yet.</p>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach($reviewTrail as $entry)
                                @php
                                    $newStatus = $entry->new_values['status'] ?? null;
                                    $eventText = match ($entry->action) {
                                        'create' => 'Report created',
                                        'update' => $newStatus ? 'Status updated to ' . str_replace('_', ' ', ucfirst($newStatus)) : 'Report updated',
                                        default => ucfirst(str_replace('_', ' ', $entry->action)),
                                    };
                                @endphp
                                <div style="padding:10px 12px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);">
                                    <div style="font-size:0.84rem;font-weight:600;color:var(--text-primary);">{{ $eventText }}</div>
                                    <div class="prism-cell-sub">
                                        {{ $entry->user?->list_name ?? 'System' }}
                                        &middot;
                                        {{ $entry->created_at?->format('M d, Y \a\t g:i A') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>

</div>

@if(!in_array($report->status, ['converted_to_case', 'dismissed']) && ($report->student_id || $report->subjects->count() > 0))
<div class="modal fade prism-modal" id="convertToCaseModal" tabindex="-1"
     aria-labelledby="convertToCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="convertToCaseModalLabel"
                    style="font-weight:700;letter-spacing:-0.01em;">Convert Report to Case</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('officer.incidents.convert', $report) }}">
                @csrf
                <div class="modal-body">
                    <div class="d-flex align-items-start gap-3">
                        <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:42px;height:42px;border-radius:50%;background:#eef3ff;color:var(--blue);font-size:1.1rem;">
                            <i class="bi bi-folder2-open" aria-hidden="true"></i>
                        </div>
                        <div>
                            <p style="margin:0;color:var(--text-primary);font-size:0.9rem;line-height:1.65;">
                                You are about to convert
                                <strong class="prism-ref-number">{{ $report->report_number }}</strong>
                                into a formal disciplinary case.
                            </p>
                            <p style="margin:8px 0 0;color:var(--text-sub);font-size:0.82rem;line-height:1.6;">
                                The report details, identified student subject{{ $report->subjects->count() > 1 ? 's' : '' }},
                                and supporting attachments will be carried over to the new case.
                            </p>
                        </div>
                    </div>

                    <div style="margin-top:18px;padding:13px 14px;border:1px solid #e7c878;border-radius:var(--radius-sm);background:#fff9ec;">
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-triangle-fill"
                               style="margin-top:2px;color:#9a6500;" aria-hidden="true"></i>
                            <div style="font-size:0.8rem;line-height:1.65;color:#6f4d0b;">
                                <strong>This action cannot be undone.</strong>
                                Confirm that the report has been reviewed and is ready for formal case processing.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="prism-btn prism-btn-primary">
                        Convert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="modal fade prism-modal" id="dismissModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dismiss Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('officer.incidents.dismiss', $report) }}">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <p style="font-size:0.875rem;color:var(--text-sub);margin-bottom:16px;">
                        Dismissing <strong>{{ $report->report_number }}</strong> will close it without creating a case.
                        The dismissal reason is for internal reference only and will not be shown to the reporter.
                    </p>
                    <label class="prism-label">Reason for Dismissal <span class="text-danger">*</span></label>
                    <textarea name="dismissal_reason" class="prism-input" rows="4"
                        placeholder="Provide a detailed reason..."
                        required minlength="20" maxlength="2000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger">
                        Dismiss
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal" id="removeSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Remove Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="removeSubjectForm">
                @csrf
                @method('DELETE')
                <div class="modal-body">
                    <p style="font-size:0.875rem;color:var(--text-sub);margin-bottom:16px;">
                        Remove <strong id="removeSubjectName"></strong> as a subject of this report?
                        This action is recorded in the report's audit trail.
                    </p>
                    <label class="prism-label">Reason for Removal <span class="text-danger">*</span></label>
                    <textarea name="removal_reason" class="prism-input" rows="4"
                        placeholder="Provide a detailed reason..."
                        required minlength="20" maxlength="1000"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-danger">
                        Remove
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade prism-modal" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="overflow:visible;">
            <div class="modal-header" style="border-radius:var(--radius-md) var(--radius-md) 0 0;">
                <h5 class="modal-title">Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('officer.incidents.subjects.store', $report) }}" id="addSubjectForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="subjectStudentId" value="">

                    <label class="prism-label">Student Number or Name <span class="text-danger">*</span></label>
                    <div class="position-relative mb-3">
                        <input type="text" id="subjectLookupInput" class="prism-input"
                               placeholder="Search student number or name" autocomplete="off">
                        <div id="subjectLookupSpinner"
                             class="spinner-border spinner-border-sm position-absolute d-none"
                                style="right:12px;top:50%;margin-top:-0.5rem;color:var(--blue);"></div>

                        <div id="subjectSuggestionsDropdown" class="position-absolute d-none"
                             style="top:100%;left:0;right:0;background:white;border:1px solid var(--border);border-top:none;border-radius:0 0 var(--radius-sm) var(--radius-sm);max-height:250px;overflow-y:auto;z-index:1000;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
                            <div id="subjectSuggestionsList"></div>
                        </div>
                    </div>

                    <div id="selectedSubjectCard" class="d-none"
                         style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px 14px;">
                        <div class="d-flex align-items-center gap-3">
                            <div class="prism-avatar prism-avatar-sm" id="subjectAvatar">--</div>
                            <div>
                                <div id="subjectName" style="font-weight:600;font-size:0.9rem;color:#0f172a;"></div>
                                <div id="subjectMeta" class="prism-cell-sub prism-ref-number"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-radius:0 0 var(--radius-md) var(--radius-md);">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary" id="addSubjectSubmit" disabled>
                        <i class="bi bi-person-plus"></i> Add Subject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('partials.attachment-preview-modal')

@push('scripts')
<script>
(() => {
    const removeModalEl = document.getElementById('removeSubjectModal');
    const removeForm = document.getElementById('removeSubjectForm');
    const removeNameEl = document.getElementById('removeSubjectName');
    const removeBaseAction = @json(route('officer.incidents.subjects.destroy', [$report, '__SUBJECT__']));

    if (removeModalEl && removeForm && removeNameEl) {
        removeModalEl.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) {
                return;
            }
            const subjectId = trigger.getAttribute('data-subject-id');
            const subjectName = trigger.getAttribute('data-subject-name');
            removeForm.action = removeBaseAction.replace('__SUBJECT__', subjectId);
            removeNameEl.textContent = subjectName;
        });
    }
})();

(() => {
    const input = document.getElementById('subjectLookupInput');
    const hiddenId = document.getElementById('subjectStudentId');
    const spinner = document.getElementById('subjectLookupSpinner');
    const dropdown = document.getElementById('subjectSuggestionsDropdown');
    const list = document.getElementById('subjectSuggestionsList');
    const selectedCard = document.getElementById('selectedSubjectCard');
    const submitBtn = document.getElementById('addSubjectSubmit');
    const modalEl = document.getElementById('addSubjectModal');

    if (!input || !hiddenId || !dropdown || !list || !submitBtn || !modalEl) {
        return;
    }

    let timer = null;

    function resetSelection() {
        hiddenId.value = '';
        submitBtn.disabled = true;
        selectedCard.classList.add('d-none');
    }

    function selectStudent(student) {
        hiddenId.value = student.user_id;
        input.value = student.student_number;
        document.getElementById('subjectAvatar').textContent = student.initials;
        document.getElementById('subjectName').textContent = student.full_name;
        document.getElementById('subjectMeta').textContent = `${student.student_number} · ${student.program || 'Program not set'}${student.year_level ? ' · ' + student.year_level : ''}`;
        selectedCard.classList.remove('d-none');
        submitBtn.disabled = false;
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
            fetch(`{{ route('officer.students.lookup') }}?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(data => {
                    spinner.classList.add('d-none');
                    const results = data.results || [];
                    list.innerHTML = '';

                    if (results.length === 0) {
                        const empty = document.createElement('div');
                        empty.style.cssText = 'padding:12px 16px;font-size:0.84rem;color:var(--text-muted);';
                        empty.textContent = 'No matching students found in your department.';
                        list.appendChild(empty);
                        dropdown.classList.remove('d-none');
                        return;
                    }

                    results.forEach(student => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding:12px 16px;border-bottom:1px solid var(--border);cursor:pointer;transition:background-color 0.15s;';
                        const nameLine = document.createElement('div');
                        nameLine.style.cssText = 'font-weight:600;font-size:0.9rem;color:#0f172a;';
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
        if (!e.target.closest('#addSubjectModal .position-relative')) {
            dropdown.classList.add('d-none');
        }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        input.value = '';
        list.innerHTML = '';
        dropdown.classList.add('d-none');
        resetSelection();
    });
})();
</script>
@endpush
@endsection
