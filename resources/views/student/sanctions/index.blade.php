@extends('layouts.student')
@section('title', 'My Sanctions')
@section('css')
<style>
    .sanctions-layout { display: grid; grid-template-columns: minmax(0,1.35fr) minmax(390px,.85fr); align-items: stretch; gap: 18px; }
    .sanctions-main { display: flex; min-width: 0; flex-direction: column; align-self: start; }
    .sanctions-main > .prism-card:only-child { display: flex; width: 100%; height: 100%; flex-direction: column; }
    .sanctions-main > .prism-card:only-child .prism-table-empty { display: flex; align-items: center; justify-content: center; flex: 1; }
    .final-decisions-list { display: flex; flex-direction: column; gap: 12px; }
    .final-decisions-label { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; color: #64748b; font-size: 0.78rem; font-weight: 700; line-height: 18px; letter-spacing: 0.02em; text-transform: uppercase; }
    .active-sanctions-list { display: flex; flex: 1; flex-direction: column; gap: 12px; min-height: 0; }
    .active-sanctions-list > .prism-card:only-child { display: flex; height: 100%; flex-direction: column; }
    .active-sanctions-list > .prism-card:only-child > .prism-card-body { flex: 1; }
    .sanctions-qr-panel { min-width: 0; height: auto; align-self: start; padding-bottom: 26px; }
    .sanctions-layout.has-final-decisions .sanctions-qr-panel { margin-top: 34px; }
    .us-attendance-office { color: #172033; font-size: .84rem; font-weight: 700; }
    .us-attendance-detail { margin-top: 3px; color: var(--text-muted); font-size: .72rem; }
    .us-attendance-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .us-attendance-heading .prism-badge { flex: 0 0 auto; margin: 0; }
    .us-attendance-progress { margin-top: 7px; color: #334155; font-size: .76rem; font-weight: 600; }
    .us-attendance-meta { display: grid; gap: 4px; margin-top: 9px; padding-top: 9px; border-top: 1px solid #e8edf4; }
    .us-attendance-meta-row { color: #64748b; font-size: .71rem; line-height: 1.45; }
    .us-attendance-meta-label { color: #475569; font-weight: 700; }
    .us-attendance-empty { color: #94a3b8; }
    .us-attendance-session { min-width: 170px; font-size: .8rem; }
    .us-attendance-times { display: flex; gap: 16px; margin-top: 4px; }
    .us-attendance-times span { white-space: nowrap; }
    .us-attendance-times small { display: block; color: var(--text-muted); font-size: .62rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    /* The list has no card around it, so the pagination row sits on the page
       rather than in a bar of its own. */
    .sdo-content .sanctions-pagination { margin-top: 6px; padding: 14px 2px 0; border: 0; border-radius: 0; background: transparent; }

    .us-qr-picker { padding: 13px 16px 0; }
    .us-qr-picker-label { display: block; margin-bottom: 4px; color: #64748b; font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .us-qr-picker .prism-select { width: 100%; max-width: 100%; min-height: 36px; font-size: .8rem; }
    .us-qr-for { display: flex; flex-direction: column; gap: 2px; }
    .us-qr-for-office { color: #172033; font-size: .86rem; font-weight: 700; }
    .us-qr-for-case { color: var(--text-muted); font-size: .73rem; }
    .us-qr-scheduled { margin: 4px 16px 0; padding: 11px 13px; border: 1px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; }
    .us-qr-scheduled-label { margin-bottom: 6px; color: #64748b; font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .us-qr-scheduled-row { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px; color: #64748b; font-size: .74rem; }
    .us-qr-wrap { display: flex; flex-direction: column; align-items: center; gap: 14px; padding: 18px 16px 10px; text-align: center; }
    .us-qr-image { width: 290px; height: 290px; max-width: 100%; padding: 12px; border: 2px solid var(--border); border-radius: 10px; background: #fff; }
    .us-qr-refresh { color: var(--text-muted); font-size: .69rem; line-height: 1.4; }
    .us-qr-countdown { color: var(--blue); font-weight: 700; }
    .sanction-us-panel { padding: 14px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
    .sanction-us-summary { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 10px; }
    .sanction-us-label { display: block; margin-bottom: 2px; color: #64748b; font-size: .66rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .sanction-us-hours { color: #172033; font-size: .9rem; font-weight: 700; }
    .sanction-us-remaining { color: #475569; font-size: .76rem; font-weight: 600; text-align: right; }
    .sanction-us-progress { height: 7px; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
    .sanction-us-progress-bar { height: 100%; border-radius: inherit; transition: width .4s; }
    .sanction-us-percent { margin-top: 5px; color: #64748b; font-size: .7rem; }
    .sanction-us-details { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); column-gap: 24px; row-gap: 10px; align-items: start; margin-top: 13px; padding-top: 12px; border-top: 1px solid #e2e8f0; }
    .sanction-us-details > div { min-width: 0; }
    .sanction-us-details > div:nth-child(2) { justify-self: center; text-align: left; }
    .sanction-us-details > div:nth-child(3) { justify-self: end; text-align: left; }
    .sanction-us-detail-value { color: #334155; font-size: .78rem; font-weight: 600; line-height: 1.45; overflow-wrap: anywhere; }
    .sanction-us-actions { display: flex; justify-content: flex-end; margin-top: 13px; }
    @media(max-width:1100px) {
        /* Stacked, the QR leads: it is what a student opens this page to present,
           and it would otherwise sit below the whole sanctions list. */
        .sanctions-layout { grid-template-columns: 1fr; }
        .sanctions-main { order: 2; }
        .sanctions-qr-panel { order: 1; }
        .sanctions-layout.has-final-decisions .sanctions-qr-panel { margin-top: 0; }
    }
    @media(max-width:575.98px) {
        .sanction-us-details { grid-template-columns: 1fr; }
        .sanction-us-details > div:nth-child(2),
        .sanction-us-details > div:nth-child(3) { justify-self: start; }
    }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <h1 class="prism-page-title">My Sanctions</h1>
        <p class="prism-page-sub">
            Your assigned interventions and their current status
        </p>
    </div>

    @if($hold && $finalDecisions->isEmpty())
        <div class="prism-card mb-4" style="border:1px solid #dc2626;">
            <div class="prism-card-body">
                <div class="d-flex align-items-start gap-3">
                    <i class="bi bi-lock-fill"
                       style="color:#dc2626;font-size:1.3rem;
                              flex-shrink:0;margin-top:2px;"></i>
                    <div>
                        <p style="font-weight:700;font-size:0.9rem;
                                  color:#dc2626;margin-bottom:4px;">
                            Registration Hold Active
                        </p>
                        <p style="font-size:0.84rem;margin-bottom:0;">
                            Your account has a registration hold. You will not be
                            able to enroll in the next term until all outstanding
                            obligations are fulfilled. Please visit the
                            Student Discipline Office to resolve this matter.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="sanctions-layout {{ $finalDecisions->isNotEmpty() ? 'has-final-decisions' : '' }}">
    <div class="sanctions-main">

    @if($active->isEmpty() && $completed->isEmpty() && $waived->isEmpty() && $finalDecisions->isEmpty())
        <div class="prism-card">
            <div class="prism-card-header">
                <h2 class="prism-card-title">
                    Sanctions Assigned
                </h2>
            </div>
            <div class="prism-table-empty">
                You have no sanctions on record.
            </div>
        </div>

    @else

        @if($finalDecisions->isNotEmpty())
            <div class="final-decisions-list">
                <div class="final-decisions-label">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                    Disciplinary Decisions in Effect
                </div>
                @foreach($finalDecisions as $sanction)
                    @include('student.sanctions._card', ['sanction' => $sanction])
                @endforeach
            </div>
        @endif

        @if($active->isNotEmpty())
            <div class="active-sanctions-list" style="{{ $finalDecisions->isNotEmpty() ? 'margin-top:24px;' : '' }}">
                @foreach($active as $sanction)
                    @include('student.sanctions._card', ['sanction' => $sanction])
                @endforeach
            </div>
        @endif

        @if($completed->isNotEmpty())
            <div class="active-sanctions-list" style="{{ ($finalDecisions->isNotEmpty() || $active->isNotEmpty()) ? 'margin-top:24px;' : '' }}">
                @foreach($completed as $sanction)
                    @include('student.sanctions._card', ['sanction' => $sanction])
                @endforeach
            </div>
        @endif

        @if($waived->isNotEmpty())
            <details id="completedSanctionsDetails" {{ ($active->isEmpty() && $completed->isEmpty()) ? 'open' : '' }}
                      style="{{ ($active->isNotEmpty() || $completed->isNotEmpty() || $finalDecisions->isNotEmpty()) ? 'margin-top:32px;' : '' }}">
                <summary style="cursor:pointer;user-select:none;list-style:none;
                                display:flex;align-items:center;gap:8px;
                                margin-bottom:12px;">
                    <i class="bi bi-chevron-right"
                       style="font-size:0.75rem;transition:transform .2s;"
                       id="completedChevron"></i>
                    <span class="prism-label" style="margin:0;">
                        Waived
                    </span>
                </summary>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach($waived as $sanction)
                        @include('student.sanctions._card', [
                            'sanction' => $sanction,
                            'muted'    => true,
                        ])
                    @endforeach
                </div>
            </details>
        @endif

        <div class="student-pagination sanctions-pagination">
            <x-pagination :paginator="$sanctions" noun="sanctions" />
        </div>

    @endif

    </div>

    <div class="prism-card sanctions-qr-panel">
        <div class="prism-card-header prism-card-header--blue">
            <div>
                <h2 class="prism-card-title">University Service Attendance QR</h2>
                <p class="prism-page-sub" style="margin:3px 0 0;font-size:.75rem;color:rgba(255,255,255,.78);">
                    Present your live QR code to your supervisor for time-in and time-out.
                </p>
            </div>
        </div>

        @if($activeUS->isEmpty() && $scheduledUS->isEmpty())
            <div class="prism-table-empty">
                Your attendance QR will appear after an officer assigns your university service.
            </div>
        @else
            @if($activeUS->count() > 1)
                {{-- Two assignments can be servable at once (an officer starts the second
                     early when the first office is unavailable). Only one QR shows at a
                     time so the student always knows which code they are presenting; a
                     select stays usable however many assignments pile up, and offices
                     repeat across assignments so each option leads with its case. --}}
                <div class="us-qr-picker">
                    <label class="us-qr-picker-label" for="usQrPicker">Serving now</label>
                    <select id="usQrPicker" class="prism-select" data-us-picker>
                        @foreach($activeUS as $assignment)
                            {{-- Case number only: a native option list is sized by its
                                 longest label and cannot wrap, so appending the office
                                 name pushed the dropdown past the screen on mobile. The
                                 office is named under the QR the option selects. --}}
                            <option value="{{ $assignment->id }}">
                                {{ $assignment->caseResolution?->case?->case_number ?? $assignment->office_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @foreach($activeUS as $index => $assignment)
                {{-- Office, contact, progress and deadline all live on the sanction card
                     beside this panel; repeating them here only buried the code. --}}
                <div class="us-qr-panel" data-us-panel="{{ $assignment->id }}" @if($index > 0) hidden @endif>
                    <div class="us-qr-wrap">
                        <div class="us-qr-for">
                            <span class="us-qr-for-office">{{ $assignment->office_name }}</span>
                            @if($case = $assignment->caseResolution?->case)
                                <span class="us-qr-for-case prism-ref-number">{{ $case->case_number }}</span>
                            @endif
                        </div>
                        <img src="{{ route('student.us.qr-image', $assignment) }}"
                             data-qr-url="{{ route('student.us.qr-image', $assignment) }}"
                             alt="Live attendance QR code for {{ $assignment->office_name }}"
                             class="us-qr-image js-live-us-qr">
                        <div class="us-qr-refresh">
                            Refreshes in <span class="us-qr-countdown js-us-qr-countdown">120s</span>
                            &middot; do not share a screenshot
                        </div>
                    </div>
                </div>
            @endforeach

            @if($activeUS->isEmpty())
                <div class="prism-table-empty">
                    Your attendance QR appears once your assignment starts.
                </div>
            @endif

            @if($scheduledUS->isNotEmpty())
                <div class="us-qr-scheduled">
                    <div class="us-qr-scheduled-label">Scheduled next</div>
                    @foreach($scheduledUS as $assignment)
                        <div class="us-qr-scheduled-row">
                            <span class="us-attendance-office">{{ $assignment->office_name }}</span>
                            <span>Starts {{ $assignment->start_date->format('M d, Y') }} &middot; {{ $assignment->requiredDuration() }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>

</div>
@endsection

@push('scripts')
<script>
const details  = document.getElementById('completedSanctionsDetails');
const chevron  = document.getElementById('completedChevron');
if (details && chevron) {
    const update = () => {
        chevron.style.transform = details.open ? 'rotate(90deg)' : 'rotate(0deg)';
    };
    update();
    details.addEventListener('toggle', update);
}

const qrPicker = document.querySelector('[data-us-picker]');
if (qrPicker) {
    qrPicker.addEventListener('change', () => {
        document.querySelectorAll('[data-us-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.usPanel !== qrPicker.value;
        });
    });
}

const qrPeriod = 120;
const qrImages = document.querySelectorAll('.js-live-us-qr');
const qrCountdowns = document.querySelectorAll('.js-us-qr-countdown');

function secondsUntilQrRefresh() {
    const currentSecond = Math.floor(Date.now() / 1000);
    return qrPeriod - (currentSecond % qrPeriod);
}

function updateQrCountdown() {
    const remaining = secondsUntilQrRefresh();
    qrCountdowns.forEach((countdown) => {
        countdown.textContent = remaining + 's';
        countdown.style.color = remaining <= 10 ? '#dc2626' : 'var(--blue)';
    });
}

function refreshAttendanceQrCodes() {
    qrImages.forEach((image) => {
        image.src = image.dataset.qrUrl + '?t=' + Date.now();
    });
}

function scheduleQrRefresh() {
    window.setTimeout(() => {
        refreshAttendanceQrCodes();
        scheduleQrRefresh();
    }, secondsUntilQrRefresh() * 1000);
}

if (qrImages.length) {
    updateQrCountdown();
    window.setInterval(updateQrCountdown, 1000);
    scheduleQrRefresh();
}
</script>
@endpush
