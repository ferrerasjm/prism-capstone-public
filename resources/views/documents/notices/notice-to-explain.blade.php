@extends('documents.notices._layout', [
    'documentTitle' => 'Notice to Explain',
    'formNumber' => 'CRK – ACD – SDO – F – 002',
])

@php
    $student = $case->student;
    $profile = $student?->studentProfile;
    $content = $notice->additional_content ?? [];
    $charges = is_array($content) ? ($content['offenses'] ?? []) : [];
    $notes = is_array($content) ? ($content['notes'] ?? null) : $content;
    $offense = $case->confirmedOffense;

    $section = $notice->handbook_section ?: 'Section VI.E';
    $page = $notice->handbook_page ?: '__';

    // The form names the complainant; incident reports carry either a PRISM user or an
    // external reporter name.
    $report = $case->incidentReport;
    $complainant = $report?->reportedByUser?->full_name
        ?? $report?->reported_by_name
        ?? 'the Student Discipline Office';
@endphp

@section('document')

    <table class="memo">
        <tr>
            <td class="memo-label">To</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">Mx. {{ $student?->full_name ?? '________________________' }}</span>
                <div class="memo-sub">
                    {{ $profile?->program ?? 'Program' }}, {{ $profile?->student_number ?? 'Student Number' }}
                </div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">From</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">{{ $notice->issuedBy?->full_name ?? '________________________' }}</span>
                <div class="memo-sub">Discipline Officer</div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">Subject</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">Notice to Explain</span>
                <div>
                    Discipline Case No <span class="fill">{{ $case->case_number }}</span>: for violation of
                    <span class="fill">{{ $section }}</span>. Page <span class="fill">{{ $page }}</span>
                    of the Student Handbook
                </div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">Date</td>
            <td class="memo-colon">:</td>
            <td><span class="memo-value">{{ $notice->issued_at->format('F d, Y') }}</span></td>
        </tr>
    </table>

    <div class="rule"></div>

    <p class="body-text-flush">
        Please be informed that a complaint has been filed against you by
        <span class="fill">{{ $complainant }}</span>, thru the Discipline Office for alleged violation of
        <span class="fill">{{ $section }}</span> Page <span class="fill">{{ $page }}</span> which
        provides that <span class="fill">{{ $offense?->title ?? '________________________' }}</span>.
    </p>

    <p class="body-text-flush">
        It is alleged that on <span class="fill">{{ $notice->incident_date->format('F d, Y') }}</span>, you have
        committed a violation,
        <span class="fill">{{ $offense?->description ?: ($offense?->title ?? '________________________') }}</span>.
    </p>

    {{--
        The printed form says "Attached for your reference are the following
        documents", which assumes a stapled paper packet. PRISM delivers this
        notice in the student portal with nothing attached, so the heading names
        the records this notice concerns instead. The notice itself is not
        listed: it is this document.

        The incident report itself is not something a student can open - there is
        no student-facing route for it, only the discipline office and the
        officer/coordinator side see it. The case it belongs to is what the
        student's portal actually shows, so that is what "you may view" points to.
    --}}
    <p class="body-text-flush">This notice concerns the following records:</p>

    <ol class="attachment-list">
        @if($report)
            <li>
                Incident report identified as:
                <span class="fill">{{ $report->report_number }}</span>
            </li>
        @endif
        @if(! empty($charges))
            <li>
                Offense(s) charged:
                @foreach($charges as $listed)
                    <span class="fill">{{ $listed['code'] ?? '' }} — {{ $listed['title'] ?? '' }}</span>@if(! $loop->last), @endif
                @endforeach
            </li>
        @endif
    </ol>

    <p class="body-text-flush">
        You may review this case, <span class="fill">{{ $case->case_number }}</span>, at any time under
        My Cases in the student portal.
    </p>

    <p class="body-text">
        In view thereof, please explain in writing within five (5) days upon receipt of this notice, on or before
        <span class="fill">{{ $notice->response_deadline?->format('F d, Y') ?? '________________' }}</span>.
        Failure to respond within the period given will be construed as a waiver of your right to be heard and
        investigation on the foregoing matter will proceed.
    </p>

    @if(filled($notes))
        <p class="body-text">{{ $notes }}</p>
    @endif

    <p class="body-text">This is for strict compliance.</p>

    @include('documents.notices._signatories', [
        'preparedBy' => $notice->issuedBy,
        'preparedByTitle' => 'Discipline Officer',
        'notedBy' => $notedBy,
    ])

@endsection
