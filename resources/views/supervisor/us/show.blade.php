@extends('layouts.staff')
@section('title', 'Student US Assignment')
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Assignments', 'url' => route('supervisor.us.index')],
            ['label' => $assignment->student?->full_name ?? 'Assignment'],
        ]" />
        <h1 class="prism-page-title">
            {{ $assignment->student?->full_name }}
        </h1>
        <p class="prism-page-sub">
            {{ $assignment->office_name }}
            &middot; {{ $assignment->requiredDuration() }} required
        </p>
    </div>

    @foreach(['success','error'] as $t)
        @if(session($t))
            <div class="prism-alert prism-alert-{{ $t === 'error' ? 'danger' : 'success' }} mb-4">
                <i class="bi bi-{{ $t === 'success' ? 'check-circle-fill' : 'exclamation-circle-fill' }}"></i>
                <div>{{ session($t) }}</div>
            </div>
        @endif
    @endforeach
    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="row g-4">

        <div class="col-12 col-lg-5">

            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Progress</h2>
                </div>
                <div class="prism-card-body">
                    @php $pct = $assignment->completionPercentage(); @endphp
                    <table class="prism-detail-table mb-4">
                        <tr>
                            <th>Student No.</th>
                            <td class="prism-ref-number">
                                {{ $assignment->student?->studentProfile?->student_number ?? '—' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Target Date</th>
                            <td>{{ $assignment->target_completion_date->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Hours Required</th>
                            <td>{{ $assignment->requiredDuration() }}</td>
                        </tr>
                        <tr>
                            <th>Hours Rendered</th>
                            <td>{{ $assignment->renderedDuration() }}</td>
                        </tr>
                        <tr>
                            <th>Remaining</th>
                            <td><strong>{{ $assignment->remainingDuration() }}</strong></td>
                        </tr>
                    </table>
                    <div style="background:var(--border);border-radius:99px;height:10px;margin-bottom:6px;">
                        <div style="height:10px;border-radius:99px;width:{{ $pct }}%;
                                    background:{{ $assignment->status === 'completed' ? 'var(--green,#16a34a)' : 'var(--blue)' }};
                                    transition:width .3s;"></div>
                    </div>
                    <p style="text-align:center;font-size:0.8rem;color:var(--text-muted);">
                        {{ $pct }}% complete
                    </p>
                </div>
            </div>

            @if(in_array($assignment->status, ['pending','in_progress']))
                <div class="prism-card mb-4">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Log Session</h2>
                    </div>
                    <div class="prism-card-body">
                        <form method="POST"
                              action="{{ route('supervisor.us.log', $assignment) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="prism-label">
                                    Date of Service <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="date_of_service"
                                       class="prism-input"
                                       value="{{ old('date_of_service', now()->format('Y-m-d')) }}"
                                       max="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="prism-label">
                                        Time In <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="time_in"
                                           class="prism-input"
                                           value="{{ old('time_in') }}" required>
                                </div>
                                <div class="col-6">
                                    <label class="prism-label">
                                        Time Out <span class="text-danger">*</span>
                                    </label>
                                    <input type="time" name="time_out"
                                           class="prism-input"
                                           value="{{ old('time_out') }}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="prism-label">Session Duration <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" name="duration_hours" class="prism-input"
                                               value="{{ old('duration_hours', 0) }}" min="0" max="12" step="1"
                                               aria-label="Duration hours" required>
                                        <div class="prism-hint">Hours</div>
                                    </div>
                                    <div class="col-6">
                                        <input type="number" name="duration_minutes" class="prism-input"
                                               value="{{ old('duration_minutes', 0) }}" min="0" max="59" step="1"
                                               aria-label="Duration minutes" required>
                                        <div class="prism-hint">Minutes</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="prism-label">
                                    Tasks Performed <span class="text-danger">*</span>
                                </label>
                                <textarea name="tasks_performed" class="prism-input"
                                          rows="3" required minlength="20" maxlength="2000"
                                          placeholder="Describe tasks the student performed during this session...">{{ old('tasks_performed') }}</textarea>
                            </div>
                            <button type="submit"
                                    class="prism-btn prism-btn-primary w-100">
                                <i class="bi bi-calendar-check"></i> Log Session
                            </button>
                        </form>
                    </div>
                </div>

                <div class="prism-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Record No-Show</h2>
                    </div>
                    <div class="prism-card-body">
                        <form method="POST"
                              action="{{ route('supervisor.us.no-show', $assignment) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="prism-label">
                                    Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="date"
                                       class="prism-input"
                                       value="{{ old('date', now()->format('Y-m-d')) }}"
                                       max="{{ now()->format('Y-m-d') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="prism-label">
                                    Notes <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="notes"
                                       class="prism-input"
                                       value="{{ old('notes') }}"
                                       placeholder="e.g. Student did not arrive at scheduled time."
                                       required minlength="20" maxlength="2000">
                            </div>
                            <button type="submit"
                                    class="prism-btn prism-btn-outline w-100"
                                    style="border-color:var(--orange);color:var(--orange);"
                                    onclick="return confirm('Record a no-show for this date?')">
                                <i class="bi bi-person-x"></i> Record No-Show
                            </button>
                        </form>
                    </div>
                </div>
            @endif

        </div>

        <div class="col-12 col-lg-7">
            <div class="prism-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">
                        Attendance Log
                    </h2>
                </div>
                @if($assignment->attendanceLogs->isEmpty())
                    <div class="prism-table-empty">
                        No sessions logged yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="prism-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Hours</th>
                                    <th>Method</th>
                                    <th>Logged By</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignment->attendanceLogs->sortByDesc('date_of_service') as $log)
                                    <tr style="{{ $log->log_method === 'no_show' || $log->isRejected() ? 'opacity:.6;' : '' }}">
                                        <td>{{ $log->date_of_service->format('M d, Y') }}</td>
                                        <td>{{ $log->time_in
                                            ? \Carbon\Carbon::parse($log->time_in)->format('g:i A')
                                            : '—' }}</td>
                                        <td>{{ $log->time_out
                                            ? \Carbon\Carbon::parse($log->time_out)->format('g:i A')
                                            : '—' }}</td>
                                        @php
                                            $isOpenSession = $log->log_method === 'qr_scan' && is_null($log->time_out);
                                            $isFlaggedScan = $log->log_method === 'qr_scan' && ! $isOpenSession && ! $log->totp_verified;
                                            $isShortSession = ! $isOpenSession
                                                && $log->log_method !== 'no_show'
                                                && $log->hours_rendered !== null
                                                && (float) $log->hours_rendered < 1;
                                        @endphp
                                        <td>
                                            @if($log->isRejected())
                                                <span style="color:#dc2626;font-weight:600;
                                                             font-size:0.78rem;text-decoration:line-through;"
                                                      title="Rejected by {{ $log->rejectedBy?->list_name ?? '—' }} on {{ $log->rejected_at->format('M d, Y') }} — {{ $log->rejection_reason }}">
                                                    {{ $log->renderedDuration() }}
                                                </span>
                                            @elseif($log->log_method === 'no_show')
                                                <span style="color:#dc2626;font-weight:600;
                                                             font-size:0.78rem;">No-Show</span>
                                            @elseif($isOpenSession)
                                                <span style="color:#2563eb;font-weight:600;
                                                             font-size:0.78rem;">In Progress</span>
                                            @else
                                                {{ $log->renderedDuration() }}
                                                @if($isShortSession)
                                                    <i class="bi bi-exclamation-triangle-fill"
                                                       style="color:#d97706;font-size:0.72rem;margin-left:4px;"
                                                       title="{{ $log->override_reason
                                                            ? 'Under 1 hour — Override Reason: '.$log->override_reason
                                                            : 'Under 1 hour — No override reason provided.' }}"></i>
                                                @endif
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badgeClass = match(true) {
                                                    $log->isRejected() => 'prism-badge-red',
                                                    $isOpenSession => 'prism-badge-blue',
                                                    $isFlaggedScan => 'prism-badge-orange',
                                                    $log->log_method === 'qr_scan' => 'prism-badge-green',
                                                    $log->log_method === 'no_show' => 'prism-badge-red',
                                                    default => 'prism-badge-gray',
                                                };
                                                $badgeLabel = match(true) {
                                                    $log->isRejected() => 'Rejected',
                                                    $isOpenSession => 'Open Session',
                                                    $isFlaggedScan => 'Offline Scan',
                                                    $log->log_method === 'qr_scan' => 'QR Verified',
                                                    $log->log_method === 'manual_web' => 'Manual',
                                                    $log->log_method === 'no_show' => 'No-Show',
                                                    default => $log->log_method,
                                                };
                                            @endphp
                                            <span class="prism-badge {{ $badgeClass }}"
                                                  style="font-size:0.65rem;"
                                                  @if($isFlaggedScan) title="Recorded offline/without live TOTP confirmation — review before counting as verified." @endif>
                                                {{ $badgeLabel }}
                                            </span>
                                        </td>
                                        <td class="prism-cell-sub">
                                            {{ $log->loggedBy?->list_name ?? '—' }}
                                            @if($log->verified_by && $log->verified_by !== $log->logged_by)
                                                <div style="margin-top:2px;font-size:.68rem;color:var(--text-muted);">
                                                    Verified by {{ $log->verifiedBy?->list_name ?? '—' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->isRejectable())
                                                <button type="button" class="prism-btn prism-btn-outline"
                                                        style="border-color:#dc2626;color:#dc2626;
                                                               padding:2px 10px;font-size:0.72rem;"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#rejectLogModal{{ $log->id }}">
                                                    Reject
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @foreach($assignment->attendanceLogs as $log)
                        @if($log->isRejectable())
                            <div class="modal fade" id="rejectLogModal{{ $log->id }}" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content sdo-modal">
                                        <div class="modal-header sdo-modal-header">
                                            <h5 class="modal-title">Reject Session</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST"
                                              action="{{ route('supervisor.us.reject-log', [$assignment, $log]) }}">
                                            @csrf
                                            <div class="modal-body">
                                                <p style="font-size:0.85rem;">
                                                    Rejecting the
                                                    {{ $log->date_of_service->format('M d, Y') }}
                                                    session ({{ $log->renderedDuration() }}) removes it from
                                                    the student's rendered hours. The log itself stays on
                                                    record for reference — it isn't deleted.
                                                </p>
                                                <label class="prism-label">
                                                    Reason <span class="text-danger">*</span>
                                                </label>
                                                <textarea name="rejection_reason" class="prism-input"
                                                          rows="3" required minlength="10" maxlength="1000"
                                                          placeholder="e.g. Tasks performed were not consistent with what was actually done, or the student was not present during this window."></textarea>
                                            </div>
                                            <div class="modal-footer sdo-modal-footer">
                                                <button type="button" class="btn sdo-btn-outline sdo-btn-cta"
                                                        data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn sdo-btn-danger sdo-btn-cta">
                                                    Reject Session
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
