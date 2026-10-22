@extends('layouts.staff')
@section('title', 'US Assignment')

@section('css')
<style>
    .us-attendance-scroll {
        /* The page caps how many rows exist; this only bites when the matched card
           height is shorter than one page of sessions. */
        max-height: 520px;
        overflow-y: auto;
        scrollbar-color: #94a3b8 #eef2f7;
        scrollbar-width: thin;
    }

    .us-attendance-scroll::-webkit-scrollbar {
        width: 8px;
    }

    .us-attendance-scroll::-webkit-scrollbar-track {
        background: #eef2f7;
    }

    .us-attendance-scroll::-webkit-scrollbar-thumb {
        border: 2px solid #eef2f7;
        border-radius: 999px;
        background: #94a3b8;
    }

    .us-attendance-scroll .prism-table thead th {
        position: sticky;
        top: 0;
        z-index: 1;
        background: #F8FAFC;
    }

    /* Side by side, the log card is pinned to the height of the Assignment
       Details column beside it: the absolute card contributes no height of its
       own, so the row is sized by that column and the table scrolls inside. */
    @media (min-width: 992px) {
        /* The details card carries a bottom margin that the log card, being pinned to
           the column, would otherwise overhang by. Drop it so the two cards end level. */
        .us-detail-col > .prism-card:last-child {
            margin-bottom: 0 !important;
        }

        .us-log-col {
            position: relative;
        }

        .us-log-card {
            position: absolute;
            top: 0;
            bottom: 0;
            left: calc(var(--bs-gutter-x, 1.5rem) * .5);
            right: calc(var(--bs-gutter-x, 1.5rem) * .5);
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .us-log-card > .prism-card-header {
            flex: 0 0 auto;
        }

        .us-attendance-scroll {
            flex: 1 1 auto;
            max-height: none;
            min-height: 0;
        }

        .us-log-card > .us-attendance-pagination {
            flex: 0 0 auto;
        }
    }

    .us-attendance-pagination {
        padding: 12px 16px;
        border-top: 1px solid #E5E9F2;
        background: #FFFFFF;
    }
</style>
@endsection

@section('content')
<div class="prism-content">

    <div class="prism-page-header d-flex align-items-start
                justify-content-between flex-wrap gap-2">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Sanctions', 'url' => route('officer.sanctions.index')],
                ['label' => $assignment->caseResolution?->case?->case_number ?? 'Assignment'],
            ]" />
            <h1 class="prism-page-title">
                {{ $assignment->student?->full_name }}
            </h1>
            <p class="prism-page-sub">
                {{ $assignment->office_name }}
                &middot; {{ $assignment->requiredDuration() }} required
                @if($case = $assignment->caseResolution?->case)
                    &middot;
                    <a href="{{ route('officer.cases.show', $case) }}" class="prism-ref-number" style="color:inherit;text-decoration:underline;">
                        Case {{ $case->case_number }}
                    </a>
                @endif
            </p>
        </div>

        @if($assignment->status !== 'completed')
            <div class="d-flex flex-wrap gap-2">
                @if($assignment->isOverdue() && $assignment->status !== 'non_compliant')
                    {{-- Overdue is the only state where this action applies, so the button
                         only exists then; the notice below stays purely informational.
                         Secondary styling and left position keep it subordinate to Extend
                         Deadline, the recommended action. --}}
                    <form method="POST" action="{{ route('officer.us.non-compliant', $assignment) }}" id="ncForm">
                        @csrf
                        <input type="hidden" name="reason" id="ncReason"
                               value="Target completion date passed with {{ $assignment->remainingDuration() }} remaining.">
                        <button type="submit" class="prism-btn prism-btn-blue-outline"
                                onclick="return confirm('Mark as non-compliant and raise registration hold?')">
                            Mark Non-Compliant
                        </button>
                    </form>
                @endif

                <button type="button" class="prism-btn prism-btn-primary"
                        data-bs-toggle="modal" data-bs-target="#extendDeadlineModal">
                    Extend Deadline
                </button>
            </div>
        @endif
    </div>

    @if($assignment->status !== 'completed')
        <div class="modal fade prism-modal" id="extendDeadlineModal" tabindex="-1"
             aria-labelledby="extendDeadlineModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('officer.us.extend-deadline', $assignment) }}">
                        @csrf
                        <div class="modal-header">
                            <div>
                                <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                                    University service
                                </div>
                                <h2 class="modal-title" id="extendDeadlineModalLabel"
                                    style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                                    Extend the target completion date?
                                </h2>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if($assignment->status === 'non_compliant')
                                <div style="margin-bottom:16px;padding:12px 13px;border:1px solid #f1d58a;border-radius:8px;background:#fffaf0;color:#7c4a12;font-size:.78rem;line-height:1.55;">
                                    Use this if the delay isn't the student's fault — e.g. the office was unavailable.
                                    Extending preserves earned hours and restores Pending or In Progress status. The registration hold is lifted only if no other sanction requires it.
                                </div>
                            @endif
                            <label class="prism-label" for="extendTargetDate">New Target Completion Date <span class="text-danger">*</span></label>
                            <input type="date" id="extendTargetDate" name="target_completion_date" class="prism-input mb-3"
                                   min="{{ $assignment->target_completion_date->copy()->addDay()->max(today())->format('Y-m-d') }}" required>
                            <label class="prism-label" for="extendReason">Reason <span class="text-danger">*</span></label>
                            <textarea id="extendReason" name="reason" class="prism-input" rows="3"
                                      minlength="20" maxlength="1000"
                                      placeholder="Why is this deadline being extended — e.g. the office had no supervisor available this month."
                                      required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="prism-btn prism-btn-primary">Extend Deadline</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if($assignment->status === 'non_compliant')
        <div class="prism-card mb-4" style="border:1px solid #dc2626;">
            <div class="prism-card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-exclamation-triangle-fill"
                       style="color:#dc2626;font-size:1.3rem;flex-shrink:0;"></i>
                    <div class="flex-grow-1">
                        <p style="font-weight:700;margin-bottom:4px;">
                            Non-Compliant — Registration Hold Active
                        </p>
                        <p style="font-size:0.84rem;margin-bottom:12px;">
                            This student has an active registration hold.
                            Coordinate with the Registrar to block enrollment in NUIS.
                            Completing this service or receiving an approved extension can clear
                            this requirement. The hold remains if another sanction still requires it.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($assignment->isOverdue() && $assignment->status !== 'non_compliant')
        <div class="prism-alert prism-alert-warning mb-4">
            <i class="bi bi-clock-fill"></i>
            <div><strong>Deadline needs review</strong> &middot; {{ $assignment->remainingDuration() }} remaining.
                Extend the deadline or mark as non-compliant.</div>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-4 us-detail-col">

            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Assignment Details</h2>
                </div>
                <div class="prism-card-body">
                    @php
                        $pct = $assignment->completionPercentage();
                        $statusMap = [
                            'pending'       => ['Pending',       'prism-badge-orange'],
                            'in_progress'   => ['In Progress',   'prism-badge-blue'],
                            'completed'     => ['Completed',     'prism-badge-green'],
                            'non_compliant' => ['Non-Compliant', 'prism-badge-red'],
                        ];
                        [$label, $badge] = $statusMap[$assignment->status];
                    @endphp
                    <table class="prism-detail-table mb-4">
                        <tr>
                            <th>Status</th>
                            <td><span class="prism-badge {{ $badge }}">{{ $label }}</span></td>
                        </tr>
                        <tr>
                            <th>Office</th>
                            <td>{{ $assignment->office_name }}</td>
                        </tr>
                        <tr>
                            <th>Supervising Contact</th>
                            <td>
                                {{ $assignment->supervisor_display_name }}
                                @if($assignment->contact_person_email)
                                    <div class="text-muted small">{{ $assignment->contact_person_email }}</div>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Start Date</th>
                            <td>{{ $assignment->start_date->format('M d, Y') }}</td>
                        </tr>
                        <tr>
                            <th>Target Date</th>
                            <td>
                                {{ $assignment->target_completion_date->format('M d, Y') }}
                            </td>
                        </tr>
                    </table>

                    <div style="margin-bottom:8px;">
                        <div style="display:flex;justify-content:space-between;
                                    font-size:0.82rem;font-weight:600;margin-bottom:6px;">
                            <span>{{ $assignment->renderedDuration() }} rendered</span>
                            <span>{{ $assignment->requiredDuration() }} required</span>
                        </div>
                        <div style="background:var(--border);border-radius:99px;height:10px;">
                            <div style="height:10px;border-radius:99px;
                                        width:{{ $pct }}%;
                                        background:{{ $assignment->status === 'completed' ? 'var(--green,#16a34a)' : ($assignment->status === 'non_compliant' ? '#dc2626' : 'var(--blue)') }};
                                        transition:width .3s;"></div>
                        </div>
                        <div style="text-align:center;font-size:0.78rem;
                                    color:var(--text-muted);margin-top:4px;">
                            {{ $pct }}% complete
                            &middot; {{ $assignment->remainingDuration() }} remaining
                        </div>
                    </div>

                    @if(in_array($assignment->status, ['pending','in_progress']))
                    <div style="margin-top:16px;padding-top:16px;
                                border-top:1px solid var(--border);">
                        <p style="font-size:0.78rem;font-weight:600;
                                  margin-bottom:4px;">QR Security</p>
                        <p style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;">
                            Regenerate the QR code if the current code
                            has been shared or compromised. The previous QR code becomes
                            immediately invalid.
                            @if($assignment->totp_secret_generated_at)
                                Last generated
                                {{ $assignment->totp_secret_generated_at->format('M d, Y \a\t g:i A') }}.
                            @endif
                        </p>
                        <form method="POST"
                              action="{{ route('officer.us.regenerate-qr', $assignment) }}"
                              id="regenerateQrForm"
                              style="display:flex;justify-content:flex-end;"
                              >
                            @csrf
                            <button type="button"
                                    class="prism-btn prism-btn-outline"
                                    data-bs-toggle="modal"
                                    data-bs-target="#confirmRegenerateQrModal"
                                    style="font-size:0.78rem;padding:5px 14px;
                                           border-color:var(--orange);color:var(--orange);">
                                <i class="bi bi-qr-code-scan"></i> Regenerate QR Code
                            </button>
                        </form>

                        <div class="modal fade prism-modal" id="confirmRegenerateQrModal" tabindex="-1"
                             aria-labelledby="confirmRegenerateQrModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <div>
                                            <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                                                QR security
                                            </div>
                                            <h2 class="modal-title" id="confirmRegenerateQrModalLabel"
                                                style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                                                Regenerate the QR code?
                                            </h2>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                                            A new secure QR code will be generated for
                                            <strong>{{ $assignment->student?->full_name }}</strong>.
                                        </p>
                                        <div style="margin-top:16px;padding:12px 13px;border:1px solid #f1d58a;border-radius:8px;background:#fffaf0;color:#7c4a12;font-size:.78rem;line-height:1.55;">
                                            The student’s current QR code will stop working immediately. Use this action only if the existing code has been shared or may be compromised.
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="prism-btn prism-btn-primary" form="regenerateQrForm">
                                            Regenerate QR Code
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </div>
        <div class="col-12 col-lg-8 us-log-col">

            <div class="prism-card us-log-card">
                <div class="prism-card-header prism-card-header--blue d-flex align-items-center justify-content-between gap-2">
                    <h2 class="prism-card-title">
                        Attendance Log
                    </h2>
                    @if($assignment->attendance_logs_count > 0)
                        <span class="prism-cell-sub" style="color:rgba(255,255,255,.78);">{{ $assignment->attendance_logs_count }} {{ Str::plural('session', $assignment->attendance_logs_count) }}</span>
                    @endif
                </div>
                @if($logs->isEmpty())
                    <div class="prism-table-empty">
                        No sessions logged yet.
                    </div>
                @else
                    <div class="table-responsive us-attendance-scroll">
                        <table class="prism-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Hours</th>
                                    <th>Method</th>
                                    <th>Logged By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr style="{{ $log->log_method === 'no_show' ? 'opacity:.6;' : '' }}">
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
                                            @if($log->log_method === 'no_show')
                                                <span style="color:#dc2626;font-weight:600;
                                                             font-size:0.78rem;">
                                                    No-Show
                                                </span>
                                            @elseif($isOpenSession)
                                                <span style="color:#2563eb;font-weight:600;
                                                             font-size:0.78rem;">
                                                    In Progress
                                                </span>
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
                                                    $isOpenSession => 'prism-badge-blue',
                                                    $isFlaggedScan => 'prism-badge-orange',
                                                    $log->log_method === 'qr_scan' => 'prism-badge-green',
                                                    $log->log_method === 'no_show' => 'prism-badge-red',
                                                    default => 'prism-badge-gray',
                                                };
                                                $badgeLabel = match(true) {
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="us-attendance-pagination prism-pagination">
                        <x-pagination :paginator="$logs" per-page-key="logs_per_page" noun="sessions" />
                    </div>
                @endif
            </div>

        </div>
    </div>
</div>
@endsection
