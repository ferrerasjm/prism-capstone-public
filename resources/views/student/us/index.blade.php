@extends('layouts.student')
@section('title', 'University Service Progress')
@section('css')
<style>
    .us-detail-grid { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(320px,.85fr); gap:18px; align-items:start; }
    .us-history-panel { order:1; }
    .us-qr-panel-col { order:2; }
    .us-history-table th, .us-history-table td { white-space:nowrap; }
    .us-history-table td.us-history-task { white-space:normal; color:var(--text-muted); font-size:.78rem; }
    .us-detail-meta { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1px; overflow:hidden; border:1px solid var(--border); border-radius:8px; background:var(--border); }
    .us-detail-meta > div { min-width:0; padding:14px; background:#f8fafc; }
    .us-detail-label { display:block; margin-bottom:4px; color:#64748b; font-size:.66rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .us-detail-value { color:#172033; font-size:.82rem; font-weight:650; overflow-wrap:anywhere; }
    .us-qr-wrap { display:flex; flex-direction:column; align-items:center; gap:12px; padding:24px; text-align:center; }
    .us-qr-image { width:300px; height:300px; max-width:100%; padding:12px; border:2px solid var(--border); border-radius:10px; background:#fff; }
    .us-qr-refresh { color:var(--text-muted); font-size:.72rem; }
    .us-qr-refresh-row { display:flex; align-items:center; gap:8px; }
    .us-qr-countdown-bar { width:190px; height:6px; overflow:hidden; border-radius:999px; background:#e2e8f0; }
    .us-qr-countdown-fill { height:100%; border-radius:inherit; background:var(--blue); }
    .us-qr-countdown { color:var(--blue); font-size:.78rem; font-weight:700; }
    .us-qr-note { padding:10px 13px; border:1px solid var(--orange); border-radius:8px; background:#fff7ed; color:#92400e; font-size:.75rem; line-height:1.5; text-align:left; }
    .us-session { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:13px 0; border-bottom:1px solid var(--border); }
    .us-session:last-child { border-bottom:0; }
    .us-session-date { color:#172033; font-size:.82rem; font-weight:700; }
    .us-session-time { margin-top:3px; color:var(--text-muted); font-size:.74rem; }
    @media(max-width:900px) {
        .us-detail-grid { grid-template-columns:1fr; }
        .us-detail-meta { grid-template-columns:repeat(2,minmax(0,1fr)); }
        /* Stacked, the QR leads: it is what a student opens this page to present. */
        .us-history-panel { order:2; }
        .us-qr-panel-col { order:1; }
    }
    @media(max-width:575.98px) { .us-detail-meta { grid-template-columns:1fr; } }
</style>
@endsection
@section('content')
<div class="prism-content">
    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Sanctions', 'url' => route('student.sanctions.index')],
            ['label' => 'University Service Progress'],
        ]" />
        <h1 class="prism-page-title">University Service Progress</h1>
        <p class="prism-page-sub">Assignment details, attendance QR, and service history in one place.</p>
    </div>

    @php
        $assignments = $assignments ?? collect();
        $statusMap = [
            'pending' => ['Pending', 'prism-badge-orange'],
            'in_progress' => ['In Progress', 'prism-badge-blue'],
            'completed' => ['Completed', 'prism-badge-green'],
            'non_compliant' => ['Non-Compliant', 'prism-badge-red'],
        ];
    @endphp
    @if(! $assignment && $assignments->count() > 1)
        <div class="prism-card">
            <div class="prism-card-header"><h2 class="prism-card-title">Your University Service assignments</h2></div>
            <div class="prism-card-body">
                <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:14px;">
                    You have {{ $assignments->count() }} University Service assignments from separate cases. Select one to view its details, attendance history, and attendance QR.
                </p>
                @foreach($assignments as $a)
                    @php
                        [$aLabel, $aBadge] = $statusMap[$a->status] ?? [ucfirst($a->status), 'prism-badge-gray'];
                    @endphp
                    <a href="{{ route('student.us.show', $a) }}" class="us-session" style="text-decoration:none;color:inherit;">
                        <div>
                            <div class="us-session-date">{{ $a->office_name }}</div>
                            <div class="us-session-time">{{ $a->caseResolution?->case?->case_number ?? 'University Service assignment' }} &middot; {{ $a->renderedDuration() }} of {{ $a->requiredDuration() }}</div>
                        </div>
                        <span class="prism-badge {{ $aBadge }}">{{ $aLabel }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @elseif(! $assignment)
        <div class="prism-card"><div class="prism-table-empty">You have no University Service assignments.</div></div>
    @else
        @php
            [$statusLabel, $statusBadge] = $statusMap[$assignment->status] ?? [ucfirst($assignment->status), 'prism-badge-gray'];
            $canShowQr = in_array($assignment->status, ['pending', 'in_progress'], true);
        @endphp

        <div class="prism-card mb-4">
            <div class="prism-card-header prism-card-header--blue d-flex align-items-center justify-content-between">
                <div>
                    <h2 class="prism-card-title" style="color:#fff;">{{ $assignment->office_name }}</h2>
                    <div style="margin-top:3px;color:rgba(255,255,255,.78);font-size:.74rem;">{{ $assignment->caseResolution?->case?->case_number ?? 'University Service assignment' }}</div>
                </div>
                <span class="prism-badge {{ $statusBadge }}">{{ $statusLabel }}</span>
            </div>
            <div class="prism-card-body">
                <div class="us-detail-meta">
                    <div><span class="us-detail-label">Time Completed</span><span class="us-detail-value">{{ $assignment->renderedDuration() }} of {{ $assignment->requiredDuration() }}</span></div>
                    <div><span class="us-detail-label">Time Remaining</span><span class="us-detail-value">{{ $assignment->remainingDuration() }}</span></div>
                    <div><span class="us-detail-label">Supervising Contact</span><span class="us-detail-value">{{ $assignment->supervisor_display_name }}</span></div>
                    <div><span class="us-detail-label">Completion Deadline</span><span class="us-detail-value">{{ $assignment->target_completion_date?->format('M d, Y') ?? 'Not set' }}</span></div>
                </div>
            </div>
        </div>

        <div class="us-detail-grid">
            <div class="prism-card us-history-panel">
                <div class="prism-card-header prism-card-header--blue"><h2 class="prism-card-title">Attendance History</h2></div>
                @if($attendanceLogs->isEmpty())
                    <div class="prism-table-empty" style="padding:30px 12px;">No attendance sessions have been recorded yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="prism-table us-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Hours</th>
                                    <th>Task</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attendanceLogs as $log)
                                    <tr>
                                        <td>{{ $log->date_of_service->format('M d, Y') }}</td>
                                        <td>{{ $log->time_in ? \Carbon\Carbon::parse($log->time_in)->format('g:i A') : '—' }}</td>
                                        <td>{{ $log->time_out ? \Carbon\Carbon::parse($log->time_out)->format('g:i A') : 'Open session' }}</td>
                                        <td style="color:var(--green,#16a34a);font-weight:700;">
                                            {{ $log->hours_rendered !== null ? '+'.$log->renderedDuration() : '—' }}
                                        </td>
                                        <td class="us-history-task">{{ $log->tasks_performed ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="student-pagination"><x-pagination :paginator="$attendanceLogs" noun="attendance sessions" /></div>
                @endif
            </div>

            <div class="prism-card us-qr-panel-col">
                <div class="prism-card-header">
                    <div><h2 class="prism-card-title">Attendance QR</h2><p style="margin:3px 0 0;color:var(--text-muted);font-size:.73rem;">Present this code to your supervisor for time-in and time-out.</p></div>
                </div>
                @if($canShowQr)
                    <div class="us-qr-wrap">
                        <img id="qrImage" src="{{ route('student.us.qr-image', $assignment) }}" alt="Live attendance QR code" class="us-qr-image">
                        <div class="us-qr-refresh">QR refreshes in</div>
                        <div class="us-qr-refresh-row"><div class="us-qr-countdown-bar"><div id="countdownFill" class="us-qr-countdown-fill"></div></div><span id="countdownText" class="us-qr-countdown">120s</span></div>
                        <div class="us-qr-note">This QR code changes every 2 minutes. Do not share a screenshot; it will expire before it can be reused.</div>
                    </div>
                @else
                    <div class="prism-table-empty">The attendance QR is unavailable because this assignment is {{ strtolower($statusLabel) }}.</div>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection

@if($assignment && in_array($assignment->status, ['pending', 'in_progress'], true))
@push('scripts')
<script>
const qrPeriod = 120;
const qrImage = document.getElementById('qrImage');
const qrFill = document.getElementById('countdownFill');
const qrText = document.getElementById('countdownText');
const secondsUntilRefresh = () => qrPeriod - (Math.floor(Date.now() / 1000) % qrPeriod);
function updateQrCountdown() {
    const remaining = secondsUntilRefresh();
    qrText.textContent = remaining + 's';
    qrFill.style.width = ((remaining / qrPeriod) * 100) + '%';
    const color = remaining <= 10 ? '#dc2626' : 'var(--blue)';
    qrText.style.color = color;
    qrFill.style.background = color;
}
function refreshQrImage() {
    qrImage.src = "{{ route('student.us.qr-image', $assignment) }}?t=" + Date.now();
}
function scheduleQrRefresh() {
    window.setTimeout(() => {
        refreshQrImage();
        scheduleQrRefresh();
    }, secondsUntilRefresh() * 1000);
}
updateQrCountdown();
window.setInterval(updateQrCountdown, 1000);
scheduleQrRefresh();

// Mobile browsers throttle or suspend the timer above while this tab is backgrounded
// or the screen is locked -- exactly where a student's QR page sits between scans. If
// an officer regenerates the code while that's the case, the stale (now-dead) image can
// sit on screen indefinitely since the scheduled refresh never fires. Force a refetch the
// moment the tab becomes visible/focused again instead of waiting on the drifted timer.
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
        refreshQrImage();
    }
});
window.addEventListener('pageshow', refreshQrImage);
</script>
@endpush
@endif
