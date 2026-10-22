@extends('layouts.student')

@section('title', 'Student Dashboard')

@section('css')
<style>
    .student-dashboard { display: grid; gap: 14px; }
    .student-dashboard .dashboard-announcements { margin-bottom: 0; }
    .student-heading { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
    .student-date { display: flex; align-items: center; gap: 10px; flex-shrink: 0; padding: 9px 12px; border: 1px solid #dbe3f2; border-radius: 9px; background: #fff; white-space: nowrap; }
    .student-date-icon { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 8px; background: #edf3ff; color: #3156c7; }
    .student-date-label { color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; }
    .student-date-value { margin-left: 4px; color: #0f172a; font-size: 12px; font-weight: 700; }
    .student-stats { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 12px; }
    .student-stat { position: relative; min-height: 104px; padding: 17px 18px; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .student-stat-icon { position: absolute; top: 16px; right: 16px; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; color: var(--stat-color); background: var(--stat-soft); }
    .student-stat-value { color: #0f172a; font-size: 1.55rem; font-weight: 750; line-height: 1; }
    .student-stat-label { margin-top: 9px; color: #64748b; font-size: .78rem; font-weight: 600; }
    .student-stat-note { margin-top: 3px; color: #94a3b8; font-size: .68rem; }
    .student-stat.blue { --stat-color:#3156c7; --stat-soft:#edf3ff; } .student-stat.amber { --stat-color:#c2410c; --stat-soft:#fff1e8; }
    .student-stat.violet { --stat-color:#7c3aed; --stat-soft:#f3e8ff; } .student-stat.green { --stat-color:#15803d; --stat-soft:#ecfdf5; }
    .student-heading-title { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; }
    .student-hold-pill { gap: 7px; padding: 7px 15px; font-size: .82rem; vertical-align: middle; }
    .student-hold-pill i { font-size: .95rem; }
    .student-grid { display: grid; grid-template-columns: minmax(0,1.4fr) minmax(340px,.72fr); gap: 16px; align-items: stretch; }
    .student-bottom-grid { display: grid; grid-template-columns: minmax(0,1.4fr) minmax(340px,.72fr); gap: 16px; align-items: start; }
    .student-panel { overflow: hidden; border: 1px solid #e1e7f0; border-radius: 13px; background: #fff; box-shadow: 0 3px 12px rgba(15,23,42,.035); }
    .student-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 62px; padding: 14px 18px; border-bottom: 1px solid #edf1f6; }
    .student-panel-title { margin: 0; color: #172033; font-size: .95rem; font-weight: 700; } .student-panel-sub { margin-top: 3px; color: #94a3b8; font-size: .72rem; }
    .student-link { color: #3156c7; font-size: .74rem; font-weight: 700; text-decoration: none; white-space: nowrap; }
    .student-actions { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 9px; padding: 12px; }
    .student-action { display: grid; grid-template-columns: 34px minmax(0,1fr) 12px; align-items: center; gap: 10px; min-height: 66px; padding: 10px 11px; border: 1px solid #e5eaf2; border-radius: 9px; color: #334155; background: #fff; text-decoration: none; transition: border-color .15s ease, background-color .15s ease, transform .15s ease; }
    .student-action:hover { border-color: #cbd8f4; color: #2448b5; background: #f8faff; transform: translateY(-1px); }
    .student-action-icon { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 8px; color: #3156c7; background: #edf3ff; font-size: .9rem; }
    .student-action-title { display: block; color: #1e293b; font-size: .72rem; font-weight: 700; line-height: 1.3; }
    .student-action-note { display: block; margin-top: 2px; overflow: hidden; color: #94a3b8; font-size: .61rem; line-height: 1.3; text-overflow: ellipsis; white-space: nowrap; }
    .student-action-chevron { color: #a8b3c5; font-size: .65rem; }
    .student-case-list { padding: 8px 12px 12px; }
    .student-case { display: grid; grid-template-columns: 38px minmax(0,1fr) auto 14px; align-items: center; gap: 11px; min-height: 64px; padding: 10px 11px; border: 1px solid transparent; border-radius: 9px; color: inherit; text-decoration: none; transition: border-color .15s ease, background-color .15s ease; }
    .student-case + .student-case { margin-top: 3px; }
    .student-case:hover { border-color: #e1e7f0; background: #f8faff; }
    .student-case-icon { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 9px; color: #3156c7; background: #edf3ff; }
    .student-case-status { display: flex; align-items: center; justify-content: flex-end; }
    .student-case-chevron { color: #a8b3c5; font-size: .7rem; }
    .student-case:last-child { border-bottom: 0; } .student-case-number { color: #1e293b; font-size: .76rem; font-weight: 700; } .student-case-meta { margin-top: 3px; color: #94a3b8; font-size: .66rem; }
    .student-service { padding: 14px 16px; border-top: 1px solid #edf1f6; } .student-service:first-child { border-top: 0; }
    .student-service-head { display: flex; align-items: center; gap: 12px; } .student-service-icon { display: grid; place-items: center; width: 36px; height: 36px; flex: 0 0 36px; border-radius: 9px; color: #3156c7; background: #edf3ff; }
    .student-progress { height: 5px; margin-top: 11px; overflow: hidden; border-radius: 99px; background: #e2e8f0; } .student-progress > span { display: block; height: 100%; border-radius: inherit; background: #3156c7; }
    .student-service-action { margin-left: auto; flex: 0 0 auto; padding: 6px 11px !important; border: 1px solid #9db5ee !important; color: #3156c7 !important; background: #fff !important; box-shadow: none !important; }
    .student-service-action:hover, .student-service-action:focus { border-color: #6f8fdf !important; color: #2448b5 !important; background: #f5f8ff !important; }
    .student-qr-modal { position: fixed; inset: 0; z-index: 1050; display: none; place-items: center; padding: 20px; background: rgba(15,23,42,.48); }
    .student-qr-modal.is-open { display: grid; }
    .student-qr-dialog { width: min(440px, 100%); overflow: hidden; border: 1px solid #dbe3f2; border-radius: 14px; background: #fff; box-shadow: 0 20px 55px rgba(15,23,42,.2); }
    .student-qr-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; padding: 17px 20px; border-bottom: 1px solid #edf1f6; }
    .student-qr-modal-title { margin: 0; color: #172033; font-size: .98rem; font-weight: 750; }
    .student-qr-modal-sub { margin: 4px 0 0; color: #64748b; font-size: .72rem; }
    .student-qr-close { display: grid; place-items: center; width: 30px; height: 30px; padding: 0; border: 0; border-radius: 7px; color: #64748b; background: transparent; font-size: 1.05rem; }
    .student-qr-close:hover { color: #172033; background: #f1f5f9; }
    .student-qr-modal-body { display: grid; justify-items: center; gap: 12px; padding: 20px; text-align: center; }
    .student-qr-image { width: 250px; height: 250px; max-width: 100%; padding: 10px; border: 2px solid #dbe3f2; border-radius: 10px; background: #fff; }
    .student-qr-refresh { color: #64748b; font-size: .72rem; }
    .student-qr-countdown { color: #3156c7; font-weight: 700; }
    .student-qr-security { width: 100%; padding: 10px 12px; border: 1px solid #f59e0b; border-radius: 8px; color: #92400e; background: #fff7ed; font-size: .72rem; line-height: 1.45; text-align: left; }
    .student-empty { padding: 28px 18px; color: #94a3b8; font-size: .78rem; text-align: center; }
    .student-handbook-panel { width: 100%; }
    .student-handbook { display: grid; grid-template-columns: 122px minmax(0,1fr); align-items: center; gap: 24px; padding: 22px; }
    .student-handbook-cover { display: block; width: 122px; height: auto; border: 1px solid #425ca8; border-radius: 7px; box-shadow: 0 10px 22px rgba(15,23,42,.16); }
    .student-handbook-content { min-width: 0; }
    .student-handbook-eyebrow { margin-bottom: 5px; color: #3156c7; font-size: .65rem; font-weight: 750; letter-spacing: .07em; text-transform: uppercase; }
    .student-handbook-title { margin: 0; color: #172033; font-size: 1.05rem; font-weight: 750; }
    .student-handbook-copy { max-width: 620px; margin: 7px 0 0; color: #64748b; font-size: .77rem; line-height: 1.65; }
    .student-handbook-meta { display: flex; align-items: center; gap: 6px; margin-top: 10px; color: #94a3b8; font-size: .66rem; }
    .student-handbook-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 15px; }
    .student-handbook-action { width: fit-content; justify-content: center; }
    @media(max-width:1050px){.student-stats{grid-template-columns:repeat(2,1fr)}.student-grid,.student-bottom-grid{grid-template-columns:1fr}}
    @media(max-width:767.98px){.student-handbook{grid-template-columns:108px minmax(0,1fr);gap:18px}.student-handbook-cover{width:108px}}
    @media(max-width:575.98px){.student-heading{align-items:flex-start;flex-direction:column;gap:14px}.student-date{width:auto}.student-stats{grid-template-columns:1fr}.student-actions{grid-template-columns:1fr}.student-case{grid-template-columns:36px minmax(0,1fr) 12px}.student-case-status{grid-column:2;justify-content:flex-start}.student-case-chevron{grid-column:3;grid-row:1}.student-handbook{grid-template-columns:1fr;text-align:center}.student-handbook-cover{width:132px;margin:auto}.student-handbook-meta{justify-content:center}.student-handbook-actions{justify-content:center}}
</style>
@endsection

@section('content')
<div class="prism-content student-dashboard">
    <header class="student-heading">
        <div>
            <h1 class="prism-page-title student-heading-title">
                Welcome, {{ auth()->user()->first_name }}
                @if($hold)
                    <span class="prism-badge prism-badge-red student-hold-pill" role="status" title="Outstanding obligations must be fulfilled before next-term enrollment.">
                        <i class="bi bi-lock-fill"></i>
                        Registration Hold
                    </span>
                @endif
            </h1>
            <p class="prism-page-sub">Stay informed about your cases, obligations, and important SDO updates.</p>
        </div>
        <div class="student-date" aria-label="Today's date"><span class="student-date-icon"><i class="bi bi-calendar3"></i></span><span><span class="student-date-label">Today</span><span class="student-date-value">{{ now()->format('F j, Y') }}</span></span></div>
    </header>

    @include('partials.dashboard-announcements')

    <section class="student-stats" aria-label="Student account summary">
        <div class="student-stat blue"><span class="student-stat-icon"><i class="bi bi-folder2-open"></i></span><div class="student-stat-value">{{ $stats['active_cases'] }}</div><div class="student-stat-label">Active cases</div><div class="student-stat-note">Cases currently in progress</div></div>
        <div class="student-stat amber"><span class="student-stat-icon"><i class="bi bi-award"></i></span><div class="student-stat-value">{{ $stats['active_sanctions'] }}</div><div class="student-stat-label">Active obligations</div><div class="student-stat-note">Pending sanctions or interventions</div></div>
        <div class="student-stat violet"><span class="student-stat-icon"><i class="bi bi-person-workspace"></i></span><div class="student-stat-value">{{ $stats['service_assignments'] }}</div><div class="student-stat-label">Service assignments</div><div class="student-stat-note">Active university service work</div></div>
        <div class="student-stat green"><span class="student-stat-icon"><i class="bi bi-flag"></i></span><div class="student-stat-value">{{ $stats['complaints'] }}</div><div class="student-stat-label">My complaints</div><div class="student-stat-note">Reports you submitted to SDO</div></div>
    </section>

    <div class="student-grid">
        <section class="student-panel student-handbook-panel">
            <div class="student-handbook">
                <img class="student-handbook-cover"
                     src="{{ asset('assets/student-handbook-2025-cover.png') }}"
                     alt="NU Clark Student Handbook 2025 Edition cover">
                <div class="student-handbook-content">
                    <div class="student-handbook-eyebrow">Student Resource</div>
                    <h2 class="student-handbook-title">{{ $currentHandbook?->title ?? 'NU Clark Student Handbook' }}</h2>
                    @if($currentHandbook)
                        <p class="student-handbook-copy">Your official guide to university policies, student responsibilities, conduct standards, campus services, and the resources available throughout your studies.</p>
                        <div class="student-handbook-meta">
                            <i class="bi bi-file-earmark-pdf"></i>
                            <span>
                                PDF · {{ $currentHandbook->edition }}
                                @if($currentHandbook->effective_date)
                                    · Effective {{ $currentHandbook->effective_date->format('M j, Y') }}
                                @endif
                                @if($currentHandbook->valid_until)
                                    · Valid Until {{ $currentHandbook->valid_until->format('M j, Y') }}
                                @endif
                                · Uploaded by {{ $currentHandbook->uploadedBy?->list_name ?? 'System migration' }}
                            </span>
                        </div>
                        <div class="student-handbook-actions">
                            <a href="{{ route('student.handbook.view') }}"
                               class="prism-btn prism-btn-primary student-handbook-action"
                               target="_blank" rel="noopener">
                                View
                            </a>
                            <a href="{{ route('student.handbook.download') }}"
                               class="prism-btn prism-btn-outline student-handbook-action">
                                Download PDF
                            </a>
                        </div>
                    @else
                        <p class="student-handbook-copy">The current handbook is temporarily unavailable. Please contact the Student Discipline Office.</p>
                    @endif
                </div>
            </div>
        </section>

        <section class="student-panel">
            <div class="student-panel-head"><div><h2 class="student-panel-title">Quick Actions</h2><div class="student-panel-sub">Common student portal tasks</div></div></div>
            <div class="student-actions">
                <a class="student-action" href="{{ route('student.cases.index') }}"><span class="student-action-icon"><i class="bi bi-folder2-open"></i></span><span><span class="student-action-title">View my cases</span><span class="student-action-note">Case status and documents</span></span><i class="bi bi-chevron-right student-action-chevron"></i></a>
                <a class="student-action" href="{{ route('student.sanctions.index') }}"><span class="student-action-icon"><i class="bi bi-award"></i></span><span><span class="student-action-title">View obligations</span><span class="student-action-note">Sanctions and interventions</span></span><i class="bi bi-chevron-right student-action-chevron"></i></a>
                <a class="student-action" href="{{ route('student.complaints.create') }}"><span class="student-action-icon"><i class="bi bi-flag"></i></span><span><span class="student-action-title">Submit complaint</span><span class="student-action-note">Report an incident to SDO</span></span><i class="bi bi-chevron-right student-action-chevron"></i></a>
                <a class="student-action" href="{{ route('dashboard.profile') }}"><span class="student-action-icon"><i class="bi bi-person-circle"></i></span><span><span class="student-action-title">View profile</span><span class="student-action-note">Review account information</span></span><i class="bi bi-chevron-right student-action-chevron"></i></a>
            </div>
        </section>
    </div>

    <div class="student-bottom-grid">
    <section class="student-panel">
        <div class="student-panel-head"><div><h2 class="student-panel-title">Recent Cases</h2><div class="student-panel-sub">Your latest disciplinary case updates</div></div><a class="student-link" href="{{ route('student.cases.index') }}">View all cases</a></div>
        @forelse($recentCases as $case)
            @if($loop->first)<div class="student-case-list">@endif
            @php
                $state = $case->studentWorkflowState();
                [$stateLabel, $stateClass] = \App\Models\DisciplinaryCase::studentStateBadge($state);
            @endphp
            <a class="student-case" href="{{ route('student.cases.show',$case) }}"><span class="student-case-icon"><i class="bi bi-folder2-open"></i></span><span><span class="student-case-number prism-ref-number d-block">{{ $case->case_number }}</span><span class="student-case-meta d-block">Updated {{ $case->updated_at->diffForHumans() }}</span></span><span class="student-case-status"><span class="prism-badge {{ $stateClass }}">{{ $stateLabel }}</span></span><i class="bi bi-chevron-right student-case-chevron"></i></a>
            @if($loop->last)</div>@endif
        @empty<div class="student-empty">You have no case records.</div>@endforelse
    </section>

    @if($activeUS->isNotEmpty())
        <section class="student-panel"><div class="student-panel-head"><div><h2 class="student-panel-title">University Service Progress</h2><div class="student-panel-sub">Track active assignments and access attendance QR codes</div></div>
            @if($activeUS->count() === 1)
                <button type="button" class="prism-btn prism-btn-secondary prism-btn-sm student-service-action js-open-student-qr" data-modal-target="#student-us-qr">Show QR</button>
            @else
                {{-- Multiple assignments have separate QRs, so route to the list instead of guessing which office's code to show. --}}
                <a href="{{ route('student.sanctions.index') }}" class="prism-btn prism-btn-secondary prism-btn-sm student-service-action">View & scan QR codes</a>
            @endif
        </div>
        @foreach($activeUS as $us)<div class="student-service"><div class="student-service-head"><span class="student-service-icon"><i class="bi bi-person-workspace"></i></span><div class="flex-grow-1"><div class="student-case-number">{{ $us->office_name }}</div><div class="student-case-meta">{{ $us->renderedDuration() }} of {{ $us->requiredDuration() }} · Due {{ $us->target_completion_date->format('M j, Y') }}</div></div></div><div class="student-progress"><span style="width:{{ $us->completionPercentage() }}%"></span></div></div>@endforeach
            @if($activeUS->count() === 1)
            <div class="student-qr-modal js-student-qr-modal" id="student-us-qr" role="dialog" aria-modal="true" aria-labelledby="student-us-qr-title">
                <div class="student-qr-dialog">
                    <div class="student-qr-modal-head">
                        <div><h2 class="student-qr-modal-title" id="student-us-qr-title">University Service Attendance QR</h2><p class="student-qr-modal-sub">{{ $activeUS->first()->office_name }}</p></div>
                        <button type="button" class="student-qr-close js-close-student-qr" aria-label="Close QR code"><i class="bi bi-x-lg"></i></button>
                    </div>
                    <div class="student-qr-modal-body">
                        <img class="student-qr-image js-student-qr-image" src="{{ route('student.us.qr-image', $activeUS->first()) }}" data-qr-url="{{ route('student.us.qr-image', $activeUS->first()) }}" alt="Live attendance QR code">
                        <div class="student-qr-refresh">QR refreshes in <span class="student-qr-countdown js-student-qr-countdown">120s</span></div>
                        <div class="student-qr-security">This QR code is unique to you and changes every 2 minutes. Do not share a screenshot—it will be invalid by the time your supervisor scans it.</div>
                    </div>
                </div>
            </div>
            @endif
        </section>
    @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.js-open-student-qr').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.querySelector(button.dataset.modalTarget);
        if (modal) { modal.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    });
});
document.querySelectorAll('.js-student-qr-modal').forEach((modal) => {
    const close = () => { modal.classList.remove('is-open'); document.body.style.overflow = ''; };
    modal.querySelector('.js-close-student-qr').addEventListener('click', close);
    modal.addEventListener('click', (event) => { if (event.target === modal) close(); });
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') document.querySelectorAll('.js-student-qr-modal.is-open').forEach((modal) => modal.querySelector('.js-close-student-qr').click());
});
const studentQrPeriod = 120;
const refreshStudentQr = () => {
    const remaining = studentQrPeriod - (Math.floor(Date.now() / 1000) % studentQrPeriod);
    document.querySelectorAll('.js-student-qr-countdown').forEach((counter) => counter.textContent = remaining + 's');
    if (remaining === studentQrPeriod) document.querySelectorAll('.js-student-qr-image').forEach((image) => image.src = image.dataset.qrUrl + '?t=' + Date.now());
};
refreshStudentQr();
setInterval(refreshStudentQr, 1000);
</script>
@endpush
