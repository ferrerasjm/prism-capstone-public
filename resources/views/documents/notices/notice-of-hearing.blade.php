@extends('documents.notices._layout', [
    'documentTitle' => 'Notice of Hearing',
    'formNumber' => 'CRK – ACD – SDO – F – 016',
])

@php
    $student = $case->student;
    $profile = $student?->studentProfile;
    $offense = $case->confirmedOffense;
    $content = $notice->additional_content ?? [];
    $thru = ($referral ?? null)?->dean_or_program_chair_name ?: ($content['thru'] ?? null);

    $section = $notice->handbook_section ?: 'Section VI.E';
    $page = $notice->handbook_page ?: '__';
@endphp

@section('document')

    <table class="memo">
        <tr>
            <td class="memo-label">Date</td>
            <td class="memo-colon">:</td>
            <td><span class="memo-value">{{ $notice->issued_at->format('F d, Y') }}</span></td>
        </tr>
        <tr>
            <td class="memo-label">To</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">{{ $student?->full_name ?? '________________________' }}</span>
                <div class="memo-sub">
                    {{ $profile?->program ?? 'Program' }}, {{ $profile?->student_number ?? 'Student ID' }}
                </div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">Thru</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">{{ $thru ?: '________________________' }}</span>
                <div class="memo-sub">Dean/Program Chair</div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">Subject</td>
            <td class="memo-colon">:</td>
            <td><span class="memo-value">NOTICE OF CONFERENCE</span></td>
        </tr>
        <tr>
            <td class="memo-label">From</td>
            <td class="memo-colon">:</td>
            <td><span class="memo-value">STUDENT DISCIPLINE OFFICE</span></td>
        </tr>
    </table>

    <div class="rule"></div>

    <p class="center-heading">Notice of Hearing/Conference</p>

    <p class="body-text">
        Please be informed that the hearing/conference pertaining to your pending case for the violation stated in
        the <strong>NATIONAL UNIVERSITY STUDENT HANDBOOK, <span class="fill">{{ $section }}</span>, page/s <span class="fill">{{ $page }}</span>
        &ldquo;<span class="fill">{{ $offense?->title ?? '________________________' }}</span>&rdquo;. You have been
        subjected to disciplinary proceedings under Discipline Case No.
        <span class="fill">{{ $case->case_number }}</span>, on
        <span class="fill">{{ $notice->incident_date->format('F d, Y') }}</span>.
    </p>

    <p class="body-text">
        It is scheduled on
        <span class="fill">{{ $notice->scheduled_at?->format('F d, Y') ?? '________________' }}</span>, at
        <span class="fill">{{ $notice->scheduled_at?->format('g:i A') ?? '________' }}</span>, at
        <span class="fill">{{ $notice->scheduled_location ?? '________________________' }}</span> of the NU Clark
        Building.
    </p>

    <p class="body-text">
        This hearing/conference will give you an opportunity to further explain your side to the issues involved.
        You may bring your parent/guardian or a counsel for this purpose.
    </p>

    <p class="body-text">
        Failure to attend the hearing/conference would mean that you are waiving your right to present further
        evidence in your defense.
    </p>

    <p class="body-text">Therefore, your presence is highly requested.</p>

    {{-- The printed form has two signatories (an Assistant who prepares, a Coordinator
         who notes); PRISM records only one issuer for this notice, so one signature is
         shown rather than the same person appearing twice under different titles. --}}
    <table class="sig">
        <tr>
            <td class="sig-role-label">Issued by:</td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="sig-name" style="text-transform:uppercase;">
                    {{ $notice->issuedBy?->full_name ?? '________________________' }}
                </div>
                <div class="sig-title">Student Discipline Coordinator</div>
            </td>
            <td></td>
        </tr>
    </table>

    <table class="ack">
        <tr>
            <td class="ack-label">Acknowledged by:</td>
            <td></td>
        </tr>
        <tr>
            <td>
                <div class="ack-line"></div>
                <div class="ack-caption">Student&rsquo;s Signature over Printed Name</div>
            </td>
            <td></td>
        </tr>
    </table>

@endsection
