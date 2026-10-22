@extends('layouts.student')
@section('title', 'Submit Explanation — ' . $case->case_number)
@section('css')
<style>
    .explanation-workspace {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
        gap: 24px 28px;
        align-items: stretch;
    }

    .explanation-main,
    .explanation-side {
        display: contents;
    }

    .notice-details-card {
        grid-column: 1;
        grid-row: 1;
    }

    .response-deadline-card {
        grid-column: 2;
        grid-row: 1;
    }

    .explanation-form-shell {
        grid-column: 1;
        grid-row: 2;
        display: flex;
    }

    .writing-guide-card {
        grid-column: 2;
        grid-row: 2;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .notice-details-card,
    .response-deadline-card,
    .writing-guide-card {
        overflow: hidden;
        border: 1px solid #dfe5ef;
        border-radius: 10px;
        box-shadow: none;
    }

    .notice-details-card > .prism-card-header {
        border-bottom-color: #3156C7;
        background: #3156C7;
    }

    .notice-details-card > .prism-card-header .prism-card-title {
        color: #ffffff;
    }

    .notice-offense-summary {
        padding-bottom: 16px;
        border-bottom: 1px solid #e5e9f2;
    }

    .notice-offense-topline {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 7px;
    }

    .notice-offense-code {
        color: #334155;
        font-size: .82rem;
        font-weight: 800;
    }

    .notice-offense-title {
        color: #172033;
        font-size: .95rem;
        font-weight: 700;
        line-height: 1.45;
    }

    .notice-offense-reference {
        margin-top: 5px;
        color: #64748b;
        font-size: .75rem;
    }

    .notice-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        border-bottom: 1px solid #e5e9f2;
    }

    .notice-meta-item {
        padding: 13px 0;
    }

    .notice-meta-item + .notice-meta-item {
        padding-left: 18px;
        border-left: 1px solid #e5e9f2;
    }

    .notice-meta-label,
    .notice-description-label {
        display: block;
        margin-bottom: 4px;
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .notice-meta-value {
        color: #1e293b;
        font-size: .82rem;
        font-weight: 600;
    }

    .notice-description {
        margin-top: 16px;
    }

    .notice-description + .notice-description {
        margin-top: 10px;
    }

    .notice-description-text {
        padding: 12px 14px;
        border-radius: 7px;
        background: #f8fafc;
        color: #334155;
        font-size: .82rem;
        line-height: 1.65;
        white-space: pre-line;
    }

    .response-deadline-card {
        display: flex;
        flex-direction: column;
    }

    .response-deadline-card .prism-card-body {
        display: grid;
        align-content: start;
        gap: 14px;
        flex: 1;
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    }

    .deadline-primary {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        align-items: center;
        gap: 14px;
        padding: 14px;
        border: 1px solid #dbeafe;
        border-radius: 8px;
        background: #eff6ff;
    }

    .deadline-date-label {
        color: #3156c7;
        font-size: .7rem;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .deadline-date-value {
        margin-top: 4px;
        color: #0f172a;
        font-size: 1.15rem;
        font-weight: 800;
    }

    .deadline-remaining {
        min-width: 108px;
        padding: 10px 12px;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        background: #ffffff;
        color: #3156c7;
        font-size: 1.55rem;
        font-weight: 800;
        line-height: 1;
        text-align: right;
        box-shadow: 0 6px 16px rgba(49, 86, 199, .08);
    }

    .deadline-remaining small {
        display: block;
        margin-top: 5px;
        color: #64748b;
        font-size: .66rem;
        font-weight: 700;
        line-height: 1.25;
    }

    .deadline-secondary {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        color: #64748b;
        font-size: .74rem;
    }

    .deadline-secondary span {
        padding: 10px 12px;
        border: 1px solid #e5e9f2;
        border-radius: 8px;
        background: #ffffff;
    }

    .deadline-secondary strong {
        display: block;
        margin-top: 2px;
        color: #334155;
        font-size: .82rem;
    }

    .writing-guide-intro {
        margin: 0 0 4px;
        color: #64748b;
        font-size: .8rem;
        line-height: 1.5;
    }

    .writing-guide-list {
        display: grid;
    }

    .writing-guide-card .prism-card-body {
        display: grid;
        align-content: start;
        gap: 12px;
        flex: 1;
    }

    .writing-guide-item {
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr);
        gap: 10px;
        padding: 11px 0;
        border-bottom: 1px solid #edf0f5;
    }

    .writing-guide-item:last-child {
        border-bottom: 0;
    }

    .writing-guide-number {
        display: grid;
        place-items: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #edf3ff;
        color: #3156c7;
        font-size: .68rem;
        font-weight: 800;
    }

    .writing-guide-title {
        color: #1e293b;
        font-size: .8rem;
        font-weight: 700;
    }

    .writing-guide-description {
        margin-top: 2px;
        color: #64748b;
        font-size: .75rem;
        line-height: 1.45;
    }

    .deadline-warning {
        padding: 11px 12px;
        border-radius: 7px;
        background: #fffbeb;
        color: #92400e;
        font-size: .74rem;
        line-height: 1.5;
    }

    .explanation-form-card .prism-card-body {
        display: grid;
        gap: 16px;
        flex: 1;
    }

    .explanation-form-card {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: 100%;
    }

    .explanation-guidance {
        padding: 12px 14px;
        border-radius: 7px;
        background: #fffbeb;
        color: #92400e;
        font-size: .8rem;
        line-height: 1.6;
    }

    .explanation-field textarea {
        min-height: 300px;
        resize: vertical;
    }

    .explanation-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-top: 6px;
    }

    .explanation-submit-area {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 18px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
    }

    .explanation-submit-warning {
        max-width: 620px;
        color: #64748b;
        font-size: .75rem;
        line-height: 1.5;
    }

    .explanation-submit-warning strong {
        color: #334155;
    }

    .explanation-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-shrink: 0;
    }

    @media (max-width: 767.98px) {
        .explanation-workspace {
            grid-template-columns: 1fr;
        }

        .notice-details-card,
        .response-deadline-card,
        .explanation-form-shell,
        .writing-guide-card {
            grid-column: 1;
            grid-row: auto;
        }

        .notice-meta-grid {
            grid-template-columns: 1fr;
        }

        .notice-meta-item + .notice-meta-item {
            padding-left: 0;
            border-top: 1px solid #e5e9f2;
            border-left: 0;
        }

        .deadline-primary,
        .deadline-secondary {
            grid-template-columns: 1fr;
        }

        .deadline-remaining {
            width: 100%;
            text-align: left;
        }

        .explanation-submit-area {
            align-items: stretch;
            flex-direction: column;
        }

        .explanation-actions {
            justify-content: stretch;
        }

        .explanation-actions .prism-btn {
            flex: 1;
        }
    }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'My Cases', 'url' => route('student.cases.index')],
            ['label' => $case->case_number, 'url' => route('student.cases.show', $case)],
            ['label' => 'Submit Explanation'],
        ]" />
        <h1 class="prism-page-title">Submit Your Explanation</h1>
        <p class="prism-page-sub">Written response to the Notice to Explain issued for {{ $case->case_number }}</p>
    </div>

    @if($errors->any())
        <div class="prism-alert prism-alert-danger mb-4">
            <i class="bi bi-exclamation-circle-fill"></i>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    <div class="explanation-workspace">
        <div class="explanation-main">

            <div class="prism-card notice-details-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Notice Details</h2>
                </div>
                <div class="prism-card-body">

                    @if($case->confirmedOffense)
                        <div class="notice-offense-summary">
                            <span class="notice-meta-label">Confirmed Offense</span>
                            <div class="notice-offense-topline">
                                <span class="notice-offense-code">
                                    {{ $case->confirmedOffense->code }}
                                </span>
                                <span class="prism-badge {{ $case->case_type === 'minor' ? 'prism-badge-blue' : ($case->case_type === 'major' ? 'prism-badge-red' : 'prism-badge-gray') }}">
                                    {{ $case->case_type ? ucfirst($case->case_type) . ' Offense' : 'Unclassified' }}
                                </span>
                            </div>
                            <div class="notice-offense-title">{{ $case->confirmedOffense->title }}</div>
                            @if($activeNte)
                                <div class="notice-offense-reference">
                                    {{ $activeNte->handbook_section }}, Page {{ $activeNte->handbook_page }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="notice-meta-grid">
                        @if($activeNte)
                            <div class="notice-meta-item">
                                <span class="notice-meta-label">Date of Incident</span>
                                <div class="notice-meta-value">
                                    {{ \Carbon\Carbon::parse($activeNte->incident_date)->format('F d, Y') }}
                                </div>
                            </div>
                        @endif
                        @if($case->incidentReport)
                            <div class="notice-meta-item">
                                <span class="notice-meta-label">Report Type</span>
                                <div class="notice-meta-value">
                                    {{ ucwords(str_replace('_', ' ', $case->incidentReport->source_type)) }}
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($case->incidentReport?->description)
                        <div class="notice-description">
                            <span class="notice-description-label">Incident Description</span>
                            <div class="notice-description-text">{{ trim($case->incidentReport->description) }}</div>
                        </div>
                    @endif

                    @php
                        $nteContent = $activeNte?->additional_content;
                        $nteAdditionalNote = is_array($nteContent)
                            ? ($nteContent['notes'] ?? null)
                            : $nteContent;
                    @endphp
                    @if(filled($nteAdditionalNote))
                        <div class="notice-description">
                            <span class="notice-description-label">Additional Note from Discipline Officer</span>
                            <div class="notice-description-text">{{ $nteAdditionalNote }}</div>
                        </div>
                    @endif

                </div>
            </div>

            <form method="POST" action="{{ route('student.cases.explanation.store', $case) }}" class="explanation-form-shell">
                @csrf

                <div class="prism-card explanation-form-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Your Written Explanation</h2>
                    </div>
                    <div class="prism-card-body">

                        <div class="explanation-field">
                            <label class="prism-label">
                                Explanation <span class="text-danger">*</span>
                            </label>
                            <textarea name="explanation_text"
                                      class="prism-input @error('explanation_text') is-invalid @enderror"
                                      id="explanationText"
                                      rows="12"
                                      required
                                      minlength="50"
                                      maxlength="5000"
                                      placeholder="Write your explanation here...">{{ old('explanation_text') }}</textarea>
                        </div>

                        <div class="explanation-submit-area">
                            <div class="explanation-submit-warning">
                            <strong>Once submitted, your explanation cannot be edited or retracted.</strong>
                            Review carefully before submitting.
                            </div>
                            <div class="explanation-actions">
                                <a href="{{ route('student.cases.show', $case) }}"
                                   class="prism-btn prism-btn-outline">Cancel</a>
                                <button type="submit" class="prism-btn prism-btn-primary">Submit</button>
                            </div>
                        </div>

                    </div>
                </div>

            </form>

        </div>

        <div class="explanation-side">

            @if($activeNte)
                @php
                    $daysLeft    = $activeNte->responseDaysRemaining();
                    $isOverdue   = $activeNte->isOverdue();
                    $deadlineColor = $isOverdue ? '#dc2626' : ($daysLeft <= 1 ? 'var(--orange)' : 'var(--text-primary)');
                @endphp
                <div class="prism-card response-deadline-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Response Deadline</h2>
                    </div>
                    <div class="prism-card-body">

                        <div class="deadline-primary">
                            <div>
                                <div class="deadline-date-label">Submit by</div>
                                <div class="deadline-date-value" style="color:{{ $deadlineColor }};">
                                    {{ $activeNte->response_deadline->format('F d, Y') }}
                                </div>
                            </div>
                            <div class="deadline-remaining" style="color:{{ $deadlineColor }};">
                                @if($isOverdue)
                                    Overdue
                                @elseif($daysLeft === 0)
                                    Today
                                @else
                                    {{ $daysLeft }}
                                @endif
                                <small>
                                    {{ $isOverdue ? 'Submit immediately' : 'day' . ($daysLeft === 1 ? '' : 's') . ' remaining' }}
                                </small>
                            </div>
                        </div>
                        <div class="deadline-secondary">
                            <span>Issued <strong>{{ $activeNte->issued_at->format('M d, Y') }}</strong></span>
                            <span>Case <strong class="prism-ref-number">{{ $case->case_number }}</strong></span>
                        </div>
                        <div class="deadline-warning">
                            Failure to respond by the deadline may waive your right to be heard.
                            The case may proceed using the available evidence.
                        </div>

                    </div>
                </div>
            @endif

            <div class="prism-card writing-guide-card">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">What to Address</h2>
                </div>
                <div class="prism-card-body">
                    <div class="explanation-guidance">
                        Be honest and specific; false information may be treated as a separate handbook violation.
                    </div>

                    <p class="writing-guide-intro">Use these points to organize your explanation:</p>

                    @php
                        $prompts = [
                            ['bi-calendar-event',  'What happened',
                             'Describe the incident in your own words — what occurred, when, and where.'],
                            ['bi-person-fill',      'Your involvement',
                             'Explain your role in the incident. Be specific about what you personally did or did not do.'],
                            ['bi-chat-square-text', 'Your side of the story',
                             'If there are circumstances or context the Discipline Officer may not be aware of, explain them here.'],
                            ['bi-shield-check',     'Admission or denial',
                             'State clearly whether you admit to, partially admit to, or deny the alleged offense, and why.'],
                            ['bi-arrow-counterclockwise', 'Steps taken or offered',
                             'If you have already taken steps to address the situation, or are willing to, state them.'],
                        ];
                    @endphp

                    <div class="writing-guide-list">
                        @foreach($prompts as [$icon, $title, $desc])
                            <div class="writing-guide-item">
                                <span class="writing-guide-number">{{ $loop->iteration }}</span>
                                <div>
                                    <div class="writing-guide-title">{{ $title }}</div>
                                    <div class="writing-guide-description">{{ $desc }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

