@extends('documents.notices._layout', [
    'documentTitle' => 'Notice of Formative Intervention',
    'formNumber' => 'CRK – ACD – SDO – F – 003',
])

@php
    $student = $case->student;
    $profile = $student?->studentProfile;
    $offense = $case->confirmedOffense;
    $content = $notice->additional_content ?? [];

    $usHours = (float) ($content['us_hours_assigned'] ?? 0);
    $counseling = ! empty($content['counseling_referred']);
    $discEduc = ! empty($content['discipline_education_required']);
    $evalMon = ! empty($content['evaluation_monitoring_required']);
    $probTerms = $content['probation_terms'] ?? null;
    $probEnd = $content['probation_end_date'] ?? null;
    $notes = $content['notes'] ?? null;

    $section = $notice->handbook_section ?: 'Section VI.E';
    $page = $notice->handbook_page ?: '__';

    $report = $case->incidentReport;
    $complainant = $report?->reportedByUser?->full_name
        ?? $report?->reported_by_name
        ?? 'the Student Discipline Office';

    // The form numbers the interventions 2.1-2.4; only the ones actually assigned are
    // printed, so the student is not handed a list of measures that do not apply.
    $usAssignment = $case->resolution?->usAssignment;

    $usSchedule = $usAssignment?->start_date && $usAssignment?->target_completion_date
        ? $usAssignment->start_date->format('F d, Y').' to '.$usAssignment->target_completion_date->format('F d, Y')
        : 'as scheduled by the Discipline Office';

    $monitoringEnd = $case->resolution?->sanctions
        ?->firstWhere('sanction_type', 'evaluation_monitoring')?->due_date;
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
                <span class="memo-value">{{ $notedBy?->full_name ?? '________________________' }}</span>
                <div class="memo-sub">Student Discipline Coordinator</div>
            </td>
        </tr>
        <tr>
            <td class="memo-label">Subject</td>
            <td class="memo-colon">:</td>
            <td>
                <span class="memo-value">Notice of Formative Intervention</span>
                <div>
                    Discipline Case No <span class="fill">{{ $case->case_number }}</span>: for violation of
                    <span class="fill">{{ $section }}</span> Page <span class="fill">{{ $page }}</span>
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

    <p class="center-heading">Resolution</p>

    <p class="body-text">
        This letter is to inform you that based on the written complaint submitted to the Discipline Office by
        <span class="fill">{{ $complainant }}</span>; that on
        <span class="fill">{{ $notice->incident_date->format('F d, Y') }}</span>, you
        <span class="fill">{{ $offense?->title ?? '________________________' }}</span>.
    </p>

    <table class="clause">
        <tr>
            <td class="clause-number">1.</td>
            <td>
                That you are responsible for violating <span class="fill">{{ $section }}</span>
                Page <span class="fill">{{ $page }}</span> of National University Student Handbook, which states
                that <span class="fill">{{ $offense?->title ?? '________________________' }}</span> is a
                {{ $case->case_type === 'minor' ? 'minor' : 'major' }} offense.
            </td>
        </tr>
        <tr>
            <td class="clause-number">2.</td>
            <td>
                In view of the foregoing decision, the following formative interventions will be administered
                after this resolution:

                <table class="clause-sub">
                    @if($usHours > 0)
                        <tr>
                            <td class="clause-sub-number">2.1</td>
                            <td>
                                That you will render (<span class="fill">{{ \App\Models\UsAssignment::formatDuration($usHours) }}</span>)
                                of <strong><u>University Service</u></strong> to be accomplished within the
                                scheduled date and time
                                <span class="fill">{{ $usSchedule }}</span>.
                            </td>
                        </tr>
                    @endif
                    @if($counseling)
                        <tr>
                            <td class="clause-sub-number">2.2</td>
                            <td>That you will be referred to the Guidance Services Office.</td>
                        </tr>
                    @endif
                    @if($discEduc)
                        <tr>
                            <td class="clause-sub-number">2.3</td>
                            <td>That you will be attending Discipline Education Lecture/s.</td>
                        </tr>
                    @endif
                    @if($evalMon)
                        <tr>
                            <td class="clause-sub-number">2.4</td>
                            <td>
                                That you will be subjected to Evaluation and Monitoring{{ $monitoringEnd ? ' until '.$monitoringEnd->format('F d, Y') : '' }}.
                            </td>
                        </tr>
                    @endif
                    @if($probTerms)
                        <tr>
                            <td class="clause-sub-number">2.5</td>
                            <td>
                                That you will be placed on probation: {{ $probTerms }}{{ $probEnd ? ', until '.\Carbon\Carbon::parse($probEnd)->format('F d, Y') : '' }}.
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <p class="body-text">
        Failure to comply with the requisites of the disciplinary action and/or failure to complete the formative
        intervention program will be deemed a Major Offense under Section XVI. b. Habitual Disregard of the
        Student Handbook.
    </p>

    @if(filled($notes))
        <p class="body-text">{{ $notes }}</p>
    @endif

    <p class="body-text">Please report to the Discipline Office for further instructions.</p>

    <p class="body-text">This is for strict compliance.</p>

    @include('documents.notices._signatories', [
        'preparedBy' => $notice->issuedBy,
        'preparedByTitle' => 'Discipline Officer',
        'notedBy' => $notedBy,
    ])

@endsection
