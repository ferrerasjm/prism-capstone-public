@php
    $isFinalizedStep = $progress->isFinalizedStep;
    $isCaseComplete = $progress->isCaseComplete;
@endphp
<div class="prism-card upcc-next-action mb-4">
    <div class="prism-card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <h2 class="prism-card-title mb-0">{{ $isDistributeStep ? 'Record Decision' : $nextActionTitle }}</h2>
        <span class="prism-badge {{ $isDistributeStep || $isCaseComplete ? 'prism-badge-green' : '' }}">
            {{ $isDistributeStep ? 'Concluded' : ($isCaseComplete ? 'Complete' : 'Next Action') }}
        </span>
    </div>
    <div class="prism-card-body">
        @if($isDistributeStep)
            <div class="upcc-action-intro">The UPCC decision has been recorded. Confirm distribution in the dialog to notify the student and open the appeal window.</div>
            @if($referral->decision_summary)
                <div style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;background:#f8fafc;">
                    <div style="color:#64748b;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                        Recorded Decision Summary
                    </div>
                    <div style="color:#1e293b;font-size:.84rem;line-height:1.65;white-space:pre-line;overflow-wrap:anywhere;">{{ trim($referral->decision_summary) }}</div>
                    @if($referral->decision_issued_at)
                        <div style="margin-top:10px;color:#64748b;font-size:.72rem;">
                            Issued {{ $referral->decision_issued_at->format('F d, Y \a\t g:i A') }}
                        </div>
                    @endif
                </div>
            @endif
            <div class="upcc-action-footer">
                <button type="button" class="prism-btn prism-btn-primary" data-bs-toggle="modal" data-bs-target="#distributeDecisionModal">
                    Distribute
                </button>
            </div>
        @else
        @php
            $isScheduleStep = $progress->isScheduleStep;
            $isCouncilActionStep = $progress->isCouncilActionStep;
        @endphp
        @if(! $isFinalizedStep && $nextActionTitle !== 'No Action Required' && ! $canRecordCoordinatorAppeal && ! $isScheduleStep && ! $isCouncilActionStep && ! in_array($referral?->status, ['scheduled', 'in_progress'], true))
            <div class="upcc-action-intro"><i class="bi bi-info-circle me-1"></i>{{ $nextActionCopy }}</div>
        @endif
        @if($isScheduleStep)
            <form method="POST" action="{{ route('coordinator.cases.schedule-upcc', $case) }}" class="upcc-action-form">
                @csrf
                <div class="upcc-action-intro">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ $nextActionCopy }} Schedule the conference 5 to 7 days after receipt of the written explanation.
                </div>
                @php
                    $oldConferenceAt = old('scheduled_conference_at');
                    $oldConferenceDate = old('scheduled_conference_date', is_string($oldConferenceAt) ? substr($oldConferenceAt, 0, 10) : null);
                    $oldConferenceTime = old('scheduled_conference_time', is_string($oldConferenceAt) ? substr($oldConferenceAt, 11, 5) : null);
                @endphp
                <div class="upcc-schedule-grid">
                    <div>
                        <label class="prism-label">Conference Date <span class="text-danger">*</span></label>
                        <input type="date" name="scheduled_conference_date" class="prism-input" value="{{ $oldConferenceDate }}" min="{{ now()->addDay()->toDateString() }}" required>
                    </div>
                    <div>
                        <label class="prism-label">Conference Time <span class="text-danger">*</span></label>
                        <input type="time" name="scheduled_conference_time" class="prism-input" value="{{ $oldConferenceTime }}" required>
                    </div>
                </div>
                <div class="upcc-schedule-grid">
                    <div>
                        <label class="prism-label">Conference Location <span class="text-danger">*</span></label>
                        <input type="text" name="conference_location" class="prism-input" value="{{ old('conference_location') }}" required>
                    </div>
                    <div>
                        <label class="prism-label">Proceedings Type <span class="text-danger">*</span></label>
                        <select name="proceedings_type" class="prism-select" required>
                            <option value="full_hearing" @selected(old('proceedings_type') === 'full_hearing')>Full Hearing</option>
                            <option value="summary" @selected(old('proceedings_type') === 'summary')>Summary Proceedings</option>
                        </select>
                    </div>
                </div>
                <div class="upcc-panel-heading">UPCC Panel Composition</div>
                <div class="upcc-schedule-grid">
                    <div>
                        <label class="prism-label">Student Development and Activities Office Head <span class="text-danger">*</span></label>
                        <input type="text" name="sdao_head_name" class="prism-input" value="{{ old('sdao_head_name') }}" required>
                    </div>
                    <div>
                        <label class="prism-label">College/School Dean and/or Program Chair <span class="text-danger">*</span></label>
                        <input type="text" name="dean_or_program_chair_name" class="prism-input" value="{{ old('dean_or_program_chair_name') }}" required>
                    </div>
                </div>
                <div class="upcc-schedule-grid">
                    <div>
                        <label class="prism-label">Faculty Member (Different Program) <span class="text-danger">*</span></label>
                        <input type="text" name="cross_program_faculty_name" class="prism-input" value="{{ old('cross_program_faculty_name') }}" required>
                    </div>
                    <div>
                        <label class="prism-label">Elected Student Body Representative <span class="text-danger">*</span></label>
                        <input type="text" name="student_representative_name" class="prism-input" value="{{ old('student_representative_name') }}" required>
                    </div>
                </div>
                <div class="upcc-schedule-grid">
                    <div>
                        <label class="prism-label">Head of Student Discipline Office (Presiding Officer)<span class="text-danger">*</span></label>
                        <input type="text" class="prism-input" value="{{ auth()->user()->full_name }}" disabled>
                    </div>
                </div>
                <div class="upcc-action-footer">
                    <button type="submit" class="prism-btn prism-btn-primary">Schedule UPCC Hearing</button>
                </div>
            </form>
        @elseif($referral?->status === 'scheduled')
            @php
                $demoEarlyAttendance = config('prism_demo.allow_early_upcc_attendance');
                $conferenceNotStarted = now()->lt($referral->scheduled_conference_at) && ! $demoEarlyAttendance;
            @endphp
            <form method="POST" action="{{ route('coordinator.cases.record-attendance', $case) }}" class="upcc-action-form">
                @csrf
                <div class="upcc-action-intro">
                    <i class="bi bi-info-circle me-1"></i>
                    @if($demoEarlyAttendance && now()->lt($referral->scheduled_conference_at))
                        Demo mode: you can record attendance before the scheduled conference on
                        {{ $referral->scheduled_conference_at->format('F d, Y \a\t g:i A') }}.
                    @elseif($conferenceNotStarted)
                        You can record attendance once the conference starts on
                        {{ $referral->scheduled_conference_at->format('F d, Y \a\t g:i A') }}.
                    @else
                        Mark whether the student showed up for the conference on
                        {{ $referral->scheduled_conference_at->format('F d, Y \a\t g:i A') }}.
                        The panel then has 10 days to finish.
                    @endif
                </div>
                <div>
                    <label class="prism-label">Student Attendance <span class="text-danger">*</span></label>
                    <div class="upcc-attendance-options">
                        @foreach([
                            ['1', 'Present', 'bi-person-check', 'The student attended the conference. The panel proceeds to deliberation.'],
                            ['0', 'Absent', 'bi-person-x', 'The student did not attend. The panel decides on the case record.'],
                        ] as [$value, $label, $icon, $hint])
                            @php $checked = old('attended') === $value; @endphp
                            <label class="upcc-attendance-option {{ $checked ? 'is-checked' : '' }} {{ $conferenceNotStarted ? 'is-disabled' : '' }}">
                                <input type="radio" name="attended" value="{{ $value }}" {{ $checked ? 'checked' : '' }}
                                       required @disabled($conferenceNotStarted)>
                                <span class="upcc-attendance-option-body">
                                    <span class="upcc-attendance-option-title">
                                        <i class="bi {{ $icon }}"></i>{{ $label }}
                                    </span>
                                    <span class="upcc-attendance-option-hint">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="upcc-action-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-toggle="modal" data-bs-target="#rescheduleUpccModal">
                        Reschedule
                    </button>
                    <button type="submit" class="prism-btn prism-btn-primary" @disabled($conferenceNotStarted)>Record Attendance</button>
                </div>
            </form>
        @elseif($referral?->status === 'in_progress')
            <form method="POST" action="{{ route('coordinator.cases.record-decision', $case) }}" class="upcc-action-form">
                @csrf
                <div class="upcc-action-intro">
                    <i class="bi bi-info-circle me-1"></i>
                    Enter the outcome and decision summary from the UPCC proceedings. The decision and final report
                    are due within 3 days after proceedings end; recording the decision also records submission of
                    the final report.
                </div>
                <div>
                    <label class="prism-label">Hearing Outcome <span class="text-danger">*</span></label>
                    <div class="upcc-attendance-options">
                        @foreach([
                            ['violation_established', 'Violation Established', 'bi-exclamation-triangle', 'The panel found the student committed the offense. Proceeds to President\'s Council approval and sanction.'],
                            ['no_violation', 'No Violation Found', 'bi-shield-check', 'The panel found no basis for the offense. The case closes and the student is cleared — no Council action or sanction.'],
                        ] as [$value, $label, $icon, $hint])
                            @php $checked = old('outcome') === $value; @endphp
                            <label class="upcc-attendance-option {{ $checked ? 'is-checked' : '' }}">
                                <input type="radio" name="outcome" value="{{ $value }}" {{ $checked ? 'checked' : '' }} required>
                                <span class="upcc-attendance-option-body">
                                    <span class="upcc-attendance-option-title">
                                        <i class="bi {{ $icon }}"></i>{{ $label }}
                                    </span>
                                    <span class="upcc-attendance-option-hint">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="prism-label">Decision Summary <span class="text-danger">*</span></label>
                    <textarea name="decision_summary" class="prism-input" rows="5" minlength="50" maxlength="5000" required
                              placeholder="Summarize the panel's findings and decision — the offense established (or not), the evidence relied on, and the sanction or outcome reached.">{{ old('decision_summary') }}</textarea>
                </div>
                <div class="upcc-action-footer">
                    <button type="submit" class="prism-btn prism-btn-primary">Record UPCC Decision</button>
                </div>
            </form>
        @elseif($isCouncilActionStep)
            <form method="POST" action="{{ route('coordinator.cases.record-council-action', $case) }}" class="upcc-action-form">
                @csrf
                <div class="upcc-action-intro">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ $nextActionCopy }}
                    @if($referral->submitted_to_presidents_council_at)
                        Record it by
                        {{ \App\Services\MajorOffenseWorkflow::councilActionDeadline($referral->submitted_to_presidents_council_at)->format('F d, Y \a\t g:i A') }}.
                    @endif
                </div>
                <div>
                    <label class="prism-label">President's Council Action <span class="text-danger">*</span></label>
                    <div class="upcc-attendance-options">
                        @foreach([
                            ['approved', 'Approved', 'bi-check-circle', 'The Council upholds the decision as recorded. It can now be distributed as is.'],
                            ['modified', 'Modified', 'bi-pencil-square', 'The Council changed the decision. Note what was modified below.'],
                        ] as [$value, $label, $icon, $hint])
                            @php $checked = old('action') === $value; @endphp
                            <label class="upcc-attendance-option {{ $checked ? 'is-checked' : '' }}">
                                <input type="radio" name="action" value="{{ $value }}" {{ $checked ? 'checked' : '' }} required>
                                <span class="upcc-attendance-option-body">
                                    <span class="upcc-attendance-option-title">
                                        <i class="bi {{ $icon }}"></i>{{ $label }}
                                    </span>
                                    <span class="upcc-attendance-option-hint">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div>
                    <label class="prism-label">Notes <span class="prism-label-opt">(optional)</span></label>
                    <textarea name="notes" class="prism-input" rows="4" maxlength="2000"
                              placeholder="Add any context on the Council's action, such as what was changed or why.">{{ old('notes') }}</textarea>
                </div>
                <div class="upcc-action-footer">
                    <button type="submit" class="prism-btn prism-btn-primary">Record Council Action</button>
                </div>
            </form>
        @elseif($referral?->status === 'reconsideration_filed')
            <form method="POST" action="{{ route('coordinator.cases.record-reconsideration', $case) }}" class="upcc-action-form">
                @csrf
                <div>
                    <label class="prism-label">Reconsideration Outcome <span class="text-danger">*</span></label>
                    <select name="outcome" class="prism-select" required>
                        <option value="">Select Outcome</option>
                        <option value="original_upheld" @selected(old('outcome') === 'original_upheld')>Original Decision Upheld</option>
                        <option value="decision_modified" @selected(old('outcome') === 'decision_modified')>Decision Modified</option>
                    </select>
                </div>
                <div>
                    <label class="prism-label">Outcome Summary <span class="text-danger">*</span></label>
                    <textarea name="outcome_summary" class="prism-input" rows="5" minlength="50" maxlength="5000" required
                              placeholder="Summarize the grounds raised in the request for reconsideration and the panel's basis for upholding or modifying the original decision.">{{ old('outcome_summary') }}</textarea>
                </div>
                <div class="upcc-action-footer">
                    <button type="submit" class="prism-btn prism-btn-primary">Record Reconsideration</button>
                </div>
            </form>
        @elseif($canRecordCoordinatorAppeal)
            <form method="POST" action="{{ route('coordinator.cases.file-appeal', $case) }}" class="upcc-action-form">
                @csrf
                <div class="prism-note">
                    <i class="bi bi-info-circle me-1"></i>
                    The appeal window is open. Appeals submitted by the student or the reporting party through their portal will appear here automatically. Encode an appeal here only if one was received outside PRISM.
                </div>
                <div>
                    <label class="prism-label">Appeal Notes <span class="prism-label-opt">(optional)</span></label>
                    <textarea name="appeal_notes" class="prism-input" rows="5" maxlength="5000" placeholder="Enter the reasons and grounds stated in the appeal letter, if available.">{{ old('appeal_notes') }}</textarea>
                </div>
                <div class="upcc-action-footer">
                    <button type="submit" class="prism-btn prism-btn-primary">Record</button>
                </div>
            </form>
        @elseif($canFinalizeDecision)
            <div style="display:grid;gap:12px;">
                <form method="POST" action="{{ route('coordinator.cases.finalize-decision', $case) }}"
                      style="display:flex;justify-content:flex-end;">
                    @csrf
                    <button type="submit" class="prism-btn prism-btn-primary">Finalize Decision</button>
                </form>
            </div>
        @elseif($case->status === 'pending_resolution')
            <div class="upcc-action-intro">
                <i class="bi bi-info-circle me-1"></i>
                {{ $nextActionCopy }}
            </div>
            <div class="upcc-action-footer">
                <a href="{{ route('coordinator.cases.resolution', $case) }}" class="prism-btn prism-btn-primary">
                    Open Resolution Form
                </a>
            </div>
        @elseif($case->status === 'resolved' && $case->resolution?->intervention_category === 2 && $nextActionTitle === 'Issue NFI')
            <div class="upcc-action-intro">
                <i class="bi bi-info-circle me-1"></i>
                {{ $nextActionCopy }}
            </div>
            <form method="POST" action="{{ route('coordinator.cases.issue-nfi', $case) }}" id="issueNfiForm">
                @csrf
                <div class="upcc-action-footer">
                    <button type="button" class="prism-btn prism-btn-primary"
                            data-bs-toggle="modal" data-bs-target="#confirmIssueNfiModal">
                        Issue NFI
                    </button>
                </div>
            </form>
        @elseif($case->status === 'resolved')
            <div class="prism-note">
                <i class="bi bi-check-circle me-1"></i>
                {{ $nextActionCopy }}
            </div>
        @else
            <div class="prism-note">
                <i class="bi bi-check-circle me-1"></i>
                {{ $nextActionCopy }}
            </div>
        @endif
        @endif
    </div>
</div>

@if($isDistributeStep)
    <div class="modal fade prism-modal" id="distributeDecisionModal" tabindex="-1"
         aria-labelledby="distributeDecisionModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                            Next Action
                        </div>
                        <h2 class="modal-title" id="distributeDecisionModalLabel"
                            style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                            Distribute this decision?
                        </h2>
                    </div>
                </div>
                <div class="modal-body">
                    <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                        Send the recorded decision to the student and open the appeal window.
                    </p>
                    @if($referral?->decision_summary)
                        <div style="margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;padding:12px 14px;background:#f8fafc;">
                            <div style="color:#64748b;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px;">
                                Recorded Decision Summary
                            </div>
                            <div style="color:#1e293b;font-size:.84rem;line-height:1.65;white-space:pre-line;overflow-wrap:anywhere;">{{ trim($referral->decision_summary) }}</div>
                            @if($referral->decision_issued_at)
                                <div style="margin-top:10px;color:#64748b;font-size:.72rem;">
                                    Issued {{ $referral->decision_issued_at->format('F d, Y \a\t g:i A') }}
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="{{ route('coordinator.cases.distribute-decision', $case) }}">
                        @csrf
                        <button type="submit" class="prism-btn prism-btn-primary">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modalEl = document.getElementById('distributeDecisionModal');
            if (modalEl && window.bootstrap) {
                new bootstrap.Modal(modalEl).show();
            }
        });
    </script>
@endif

@if($referral?->status === 'scheduled')
    <div class="modal fade prism-modal" id="rescheduleUpccModal" tabindex="-1"
         aria-labelledby="rescheduleUpccModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('coordinator.cases.reschedule-upcc', $case) }}">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title" id="rescheduleUpccModalLabel"
                                style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                                Reschedule Conference
                            </h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" style="display:grid;gap:14px;">
                        <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                            Currently scheduled for
                            <strong>{{ $referral->scheduled_conference_at->format('F d, Y \a\t g:i A') }}</strong>
                            at <strong>{{ $referral->conference_location }}</strong>.
                            The prior notice of hearing will be voided and a corrected one issued to the student.
                        </p>
                        <div class="upcc-schedule-grid">
                            <div>
                                <label class="prism-label">New Conference Date <span class="text-danger">*</span></label>
                                <input type="date" name="scheduled_conference_date" class="prism-input" min="{{ now()->addDay()->toDateString() }}" required>
                            </div>
                            <div>
                                <label class="prism-label">New Conference Time <span class="text-danger">*</span></label>
                                <input type="time" name="scheduled_conference_time" class="prism-input" required>
                            </div>
                        </div>
                        <div>
                            <label class="prism-label">Conference Location <span class="text-danger">*</span></label>
                            <input type="text" name="conference_location" class="prism-input" value="{{ $referral->conference_location }}" required>
                        </div>
                        <div>
                            <label class="prism-label">Reason for Rescheduling <span class="text-danger">*</span></label>
                            <textarea name="reschedule_reason" class="prism-input" rows="3" minlength="20" maxlength="2000" required
                                placeholder="e.g. panel member conflict, venue unavailable, force majeure..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="prism-btn prism-btn-primary">Reschedule Conference</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

@if($case->status === 'resolved' && $case->resolution?->intervention_category === 2 && $nextActionTitle === 'Issue NFI')
    <div class="modal fade prism-modal" id="confirmIssueNfiModal" tabindex="-1"
         aria-labelledby="confirmIssueNfiModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                            Final confirmation
                        </div>
                        <h2 class="modal-title" id="confirmIssueNfiModalLabel"
                            style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                            Issue the formative intervention notice?
                        </h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                        The Notice of Formative Intervention for
                        <strong class="prism-ref-number">{{ $case->case_number }}</strong>
                        will be issued to
                        <strong>{{ $case->student?->full_name }}</strong>.
                    </p>
                    <div style="margin-top:16px;padding:12px 13px;border:1px solid #f1d58a;border-radius:8px;background:#fffaf0;color:#7c4a12;font-size:.78rem;line-height:1.55;">
                        Issuing this notice activates the recorded Category 2 interventions. The issued notice cannot
                        be edited or withdrawn without the formal void process.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="prism-btn prism-btn-primary" form="issueNfiForm">
                        Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
