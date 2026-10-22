@extends('layouts.staff')
@section('title', 'Record Resolution — ' . $case->case_number)
@section('css')
<style>
    .prism-input-spinner::-webkit-inner-spin-button,
    .prism-input-spinner::-webkit-outer-spin-button {
        opacity: 1;
    }

    .resolution-connected-group > .prism-card {
        margin-bottom: 0 !important;
        box-shadow: none;
    }

    .resolution-connected-group > .resolution-component-card {
        border-radius: 10px 10px 0 0;
    }

    .resolution-connected-group > .resolution-notes-card {
        border-top: 0;
        border-radius: 0 0 10px 10px;
    }

    /* Categories 3-5 have no component card above, so the notes card is on its
       own and needs the border and radius the joined version gives up. */
    .resolution-connected-group > .resolution-notes-card--standalone {
        border-top: 1px solid var(--border);
        border-radius: 10px;
    }

    /* Reads as a step marker on the blue header, not a status pill. */
    .resolution-current-step {
        background: rgba(255, 255, 255, .18);
        border: 1px solid rgba(255, 255, 255, .35);
        color: #ffffff;
    }

    .resolution-warning {
        padding: 11px 13px;
        border: 1px solid #f1d58a;
        border-radius: 8px;
        background: #fffaf0;
        color: #7c4a12;
        font-size: .78rem;
        line-height: 1.55;
    }

    .resolution-warning strong {
        color: #713f12;
    }

    .resolution-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    .resolution-actions .prism-btn {
        flex: 0 0 auto;
    }

    @media (max-width: 575.98px) {
        .resolution-adjustment-row {
            grid-template-columns: 1fr !important;
            gap: 3px !important;
        }

        .resolution-summary-grid {
            grid-template-columns: 1fr !important;
        }

        .resolution-summary-grid > div {
            border-right: 0 !important;
            border-bottom: 1px solid #e2e8f0;
        }

        .resolution-summary-grid > div:last-child {
            border-bottom: 0;
        }

        .resolution-actions {
            flex-direction: column-reverse;
            align-items: stretch;
        }

        .resolution-actions .prism-btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>
@endsection
@section('content')
<div class="prism-content">

    <div class="prism-page-header">
        <x-breadcrumbs :items="[
            ['label' => 'Cases', 'url' => route('officer.cases.index')],
            ['label' => $case->case_number, 'url' => route('officer.cases.show', $case)],
            ['label' => 'Record Resolution'],
        ]" />
        <h1 class="prism-page-title">Record Resolution</h1>
        <p class="prism-page-sub">
            {{ $case->student?->full_name }}
            &middot; {{ $case->confirmedOffense?->code }} — {{ $case->confirmedOffense?->title }}
        </p>
    </div>

    <div class="row g-4">

        <div class="col-12 col-lg-4 order-2 order-lg-1">
            <div class="prism-card mb-4">
                <div class="prism-card-header">
                    <h2 class="prism-card-title">Case Summary</h2>
                </div>
                <div class="prism-card-body">
                    <table class="prism-detail-table">
                        <tr>
                            <th>Case No.</th>
                            <td class="prism-ref-number" style="font-size:0.84rem;">
                                {{ $case->case_number }}
                            </td>
                        </tr>
                        <tr>
                            <th>Student</th>
                            <td>{{ $case->student?->list_name }}</td>
                        </tr>
                        <tr>
                            <th>Offense</th>
                            <td>
                                <div style="font-weight:700;font-size:0.8rem;">
                                    {{ $case->confirmedOffense?->code }}
                                </div>
                                <div style="font-size:0.78rem;color:var(--text-secondary);">
                                    {{ $case->confirmedOffense?->title }}
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Occurrence</th>
                            <td>
                                {{ $case->prior_offense_count + 1 }}{{ match($case->prior_offense_count + 1) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' } }}
                                Offense
                            </td>
                        </tr>
                        <tr>
                            <th>Sanction</th>
                            <td>Category {{ $case->assigned_sanction_category }}</td>
                        </tr>
                        @if($case->explanation)
                            <tr>
                                <th>Admission</th>
                                <td>
                                    <strong>{{ ucwords(str_replace('_', ' ',
                                        $case->explanation->admission_type)) }}
                                    </strong>
                                </td>
                            </tr>
                        @endif
                        <tr>
                            <th>Path</th>
                            <td>
                                <strong>{{ $case->upccReferral
                                    ? 'University Panel for Case Conference Resolution'
                                    : 'Direct Resolution at SDO Level' }}</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            @if($case->incidentReport)
                <div class="prism-card mb-4">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Incident Details</h2>
                    </div>
                    <div class="prism-card-body">
                        @include('officer.cases._incident-summary', [
                            'incidentReport' => $case->incidentReport,
                            'incidentSummaryClass' => 'case-incident-summary--flush',
                        ])
                    </div>
                </div>
            @endif

        </div>

        <div class="col-12 col-lg-8 order-1 order-lg-2">
            <form method="POST"
                  action="{{ route('officer.cases.resolution.store', $case) }}"
                  id="resolutionForm"
                  novalidate>
                @csrf

                <div class="prism-card mb-4">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Intervention Category</h2>
                    </div>
                    <div class="prism-card-body">
                        @php
                            $cats = [
                                1 => [
                                    'label' => 'Category 1 — Probation',
                                    'desc'  => 'Conduct probation for 3 academic terms and counseling referral.',
                                ],
                                2 => [
                                    'label' => 'Category 2 — Formative Intervention',
                                    'desc'  => 'University Service, counseling referral, discipline education, evaluation monitoring.',
                                ],
                                3 => [
                                    'label' => 'Category 3 — Non-Readmission',
                                    'desc'  => 'Student may finish the current term but is denied enrollment the following term.',
                                ],
                                4 => [
                                    'label' => 'Category 4 — Exclusion',
                                    'desc'  => 'Student is dropped from the university rolls.',
                                ],
                                5 => [
                                    'label' => 'Category 5 — Expulsion',
                                    'desc'  => 'Permanent removal from the school or university.',
                                ],
                            ];
                            $configured = \App\Models\OffenseCategory::find((int) $case->assigned_sanction_category);
                            if ($configured && isset($cats[$configured->number])) {
                                $cats[$configured->number]['label'] = \App\Models\CaseResolution::interventionLabel($configured->number);
                                $cats[$configured->number]['desc'] = $configured->description ?: $cats[$configured->number]['desc'];
                            }
                            $preselected = $case->assigned_sanction_category;
                        @endphp

                        <input type="hidden" name="intervention_category" value="{{ $case->assigned_sanction_category }}">

                        @php $confirmed = $cats[(int) $preselected] ?? null; @endphp

                        @if($confirmed)
                            <div style="display:flex;align-items:flex-start;gap:12px;
                                        border:2px solid var(--blue);border-radius:var(--radius-sm);
                                        padding:12px 14px;background:#F8FAFC;">
                                <i class="bi bi-check-circle-fill" style="color:var(--blue);margin-top:2px;"></i>
                                <div>
                                    <div style="font-weight:700;font-size:0.875rem;display:flex;
                                                align-items:center;gap:8px;flex-wrap:wrap;">
                                        {{ $confirmed['label'] }}
                                        <span class="prism-badge prism-badge-blue" style="font-size:0.63rem;">Confirmed</span>
                                    </div>
                                    <div style="font-size:0.82rem;color:var(--text-secondary);">{{ $confirmed['desc'] }}</div>
                                </div>
                            </div>
                        @endif

                        {{-- A settled fact by this point, not a choice: shown as the record it is
                             rather than as five radios that only ever refuse the click. --}}
                        <div class="prism-hint" style="margin-top:10px;">
                            @if($case->upccReferral)
                                Approved through the UPCC process. It cannot be changed while recording the resolution.
                            @else
                                Confirmed during sanction classification. It cannot be changed while recording the resolution.
                            @endif
                        </div>
                    </div>
                </div>

                <div class="resolution-connected-group">

                <div id="cat1Fields" class="prism-card mb-4 resolution-component-card"
                     style="{{ (int)$preselected === 1 ? '' : 'display:none;' }}">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">Probation Details</h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-sm-6">
                                <label class="prism-label">
                                    Probation Terms <span class="text-danger">*</span>
                                </label>
                                <input type="number" name="probation_terms"
                                       class="prism-input"
                                       value="{{ old('probation_terms', 3) }}"
                                       min="1" max="9"
                                       placeholder="3">
                                <p class="prism-hint">
                                    Standard is 3 academic terms per the handbook.
                                </p>
                            </div>
                            @php
                                $derivedProbationEnd = \App\Models\AcademicPeriod::endOfTermsFrom(today(), 3);
                            @endphp
                            <div class="col-12 col-sm-6">
                                <label class="prism-label">
                                    Probation End Date
                                    @unless($derivedProbationEnd)<span class="text-danger">*</span>@endunless
                                </label>
                                <input type="date" name="probation_end_date"
                                       class="prism-input"
                                       value="{{ old('probation_end_date', $derivedProbationEnd?->toDateString()) }}"
                                       min="{{ now()->addDay()->format('Y-m-d') }}"
                                       @if($derivedProbationEnd) readonly @endif>
                                <p class="prism-hint">
                                    @if($derivedProbationEnd)
                                        Derived from the academic calendar &mdash; the end of the third term
                                        from today.
                                    @else
                                        The academic calendar does not yet cover three terms ahead. Set the
                                        end date manually.
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div>
                            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                <input type="checkbox" name="counseling_referred"
                                       value="1"
                                       id="counselingToggle1"
                                       onchange="syncCounselingPickers()"
                                       {{ old('counseling_referred') ? 'checked' : '' }}
                                       style="width:16px;height:16px;flex-shrink:0;">
                                <div>
                                    <span style="font-weight:600;font-size:0.875rem;">
                                        Counseling Referral
                                    </span>
                                    <div style="font-size:0.8rem;color:var(--text-secondary);">
                                        Refer student to the Guidance Office as part of the
                                        probation intervention.
                                    </div>
                                </div>
                            </label>
                            <x-guidance-officer-picker :index="1" />
                        </div>
                    </div>
                </div>

                @php
                    $recommendedHours = $sanctionRecommendation['us_hours_recommended'] ?? null;
                    $recommendedMin = $sanctionRecommendation['us_hours_min'] ?? null;
                    $recommendedMax = $sanctionRecommendation['us_hours_max'] ?? null;
                    $showUsHours = old('us_hours_assigned') || $recommendedHours || $recommendedMin;
                    $usHoursFloor = $recommendedHours ? (int) $recommendedHours : ($recommendedMin ? (int) $recommendedMin : 1);
                    $usHoursCeiling = $recommendedHours ? (int) $recommendedHours : ($recommendedMax ? (int) $recommendedMax : 999);
                @endphp

                <div id="cat2Fields" class="prism-card mb-4 resolution-component-card"
                     style="{{ (int)$preselected === 2 ? '' : 'display:none;' }}">
                    <div class="prism-card-header prism-card-header--blue">
                        <h2 class="prism-card-title" style="color:#FFFFFF;">Formative Intervention Components</h2>
                    </div>
                    <div class="prism-card-body">

                        <div style="border:1px solid var(--border);border-radius:var(--radius-sm);
                                    padding:14px;margin-bottom:12px;">
                            <label style="display:flex;align-items:flex-start;
                                          gap:10px;cursor:pointer;margin-bottom:10px;">
                                <input type="checkbox" name="us_hours_toggle"
                                       id="usHoursToggle"
                                       {{ $showUsHours ? 'checked' : '' }}
                                       onchange="toggleUsHours(this)"
                                       style="width:16px;height:16px;flex-shrink:0;margin-top:2px;">
                                <div>
                                    <span style="font-weight:600;font-size:0.875rem;">
                                        University Service
                                    </span>
                                    <div style="font-size:0.8rem;color:var(--text-secondary);">
                                        Assign a number of service hours to be rendered.
                                    </div>
                                </div>
                            </label>
                            <div id="usHoursInput"
                                 style="{{ $showUsHours ? '' : 'display:none;' }}
                                         padding-left:26px;">
                                <label class="prism-label">
                                    Hours Assigned <span class="text-danger">*</span>
                                </label>
                               <input type="number" name="us_hours_assigned"
                                       id="usHoursAssignedInput"
                                       class="prism-input prism-input-spinner"
                                       data-label="Hours"
                                       value="{{ old('us_hours_assigned', $recommendedHours ? (int) $recommendedHours : '') }}"
                                       placeholder="{{ !$recommendedHours && $recommendedMin ? (int) $recommendedMin : '' }}"
                                       min="{{ $usHoursFloor }}" max="{{ $usHoursCeiling }}" step="1"
                                       style="width:70px;">
                                @if($recommendedHours)
                                    <p class="prism-hint mb-0">
                                        Pre-filled from the {{ (int) $recommendedHours }}-hour matrix guideline for this offense.
                                    </p>
                                @elseif($recommendedMin && $recommendedMax)
                                    <p class="prism-hint mb-0">
                                        Must stay within the {{ (int) $recommendedMin }}&ndash;{{ (int) $recommendedMax }}-hour matrix guideline for Category {{ $sanctionRecommendation['sanction_category'] }}.
                                    </p>
                                @elseif($sanctionRecommendation)
                                    <div class="sanction-guidance-panel">
                                        <div class="sanction-guidance-eyebrow">Official base guideline</div>
                                        <div class="sanction-guidance-heading">
                                            Category {{ $sanctionRecommendation['sanction_category'] }}
                                        </div>
                                        <div class="sanction-guidance-intro">
                                            No official hours guideline is recorded for this offense — use professional judgment.
                                        </div>
                                    </div>
                                @endif

                            </div>
                        </div>

                        <div style="border:1px solid var(--border);border-radius:var(--radius-sm);
                                    padding:14px;margin-bottom:12px;">
                            <label style="display:flex;align-items:flex-start;
                                          gap:10px;cursor:pointer;">
                                <input type="checkbox" name="counseling_referred"
                                       value="1"
                                       id="counselingToggle2"
                                       onchange="syncCounselingPickers()"
                                       {{ old('counseling_referred') ? 'checked' : '' }}
                                       style="width:16px;height:16px;flex-shrink:0;margin-top:2px;">
                                <div>
                                    <span style="font-weight:600;font-size:0.875rem;">
                                        Counseling Referral
                                    </span>
                                    <div style="font-size:0.8rem;color:var(--text-secondary);">
                                        Refer student to the Guidance Office for counseling sessions.
                                    </div>
                                </div>
                            </label>
                            <x-guidance-officer-picker :index="2" />
                        </div>

                        <div style="border:1px solid var(--border);border-radius:var(--radius-sm);
                                    padding:14px;margin-bottom:12px;">
                            <label style="display:flex;align-items:flex-start;
                                          gap:10px;cursor:pointer;">
                                <input type="checkbox" name="discipline_education_required"
                                       id="disciplineEducationToggle"
                                       value="1"
                                       {{ old('discipline_education_required') ? 'checked' : '' }}
                                       onchange="toggleDisciplineEducation(this)"
                                       style="width:16px;height:16px;flex-shrink:0;margin-top:2px;">
                                <div>
                                    <span style="font-weight:600;font-size:0.875rem;">
                                        Discipline Education Program
                                    </span>
                                    <div style="font-size:0.8rem;color:var(--text-secondary);">
                                        Require attendance to the Discipline Education Program lectures.
                                    </div>
                                </div>
                            </label>
                            <div id="disciplineEducationSchedule"
                                 style="{{ old('discipline_education_required') ? '' : 'display:none;' }}padding:12px 0 0 26px;">
                                <label class="prism-label" for="discipline_education_session_at" style="color:var(--text-secondary);">
                                    Program Date And Time <span class="text-danger">*</span>
                                </label>
                                <input type="datetime-local"
                                       id="discipline_education_session_at"
                                       name="discipline_education_session_at"
                                       class="prism-input"
                                       value="{{ old('discipline_education_session_at') }}"
                                       min="{{ today()->addDay()->format('Y-m-d\TH:i') }}">
                                <p class="prism-hint mb-0">Choose tomorrow or a later date. The student will receive an in-system notification and email after the resolution is recorded.</p>
                            </div>
                        </div>

                        <div style="border:1px solid var(--border);border-radius:var(--radius-sm);
                                    padding:14px;">
                            <label style="display:flex;align-items:flex-start;
                                          gap:10px;cursor:pointer;">
                                <input type="checkbox" name="evaluation_monitoring_required"
                                       value="1"
                                       onchange="toggleEvaluationMonitoring(this)"
                                       {{ old('evaluation_monitoring_required') ? 'checked' : '' }}
                                       style="width:16px;height:16px;flex-shrink:0;margin-top:2px;">
                                <div>
                                    <span style="font-weight:600;font-size:0.875rem;">
                                        Evaluation Monitoring
                                    </span>
                                    <div style="font-size:0.8rem;color:var(--text-secondary);">
                                        Student placed under evaluation monitoring for the
                                        duration of the intervention period.
                                    </div>
                                </div>
                            </label>
                            <div id="evaluationMonitoringPeriod"
                                 style="{{ old('evaluation_monitoring_required') ? '' : 'display:none;' }}padding:12px 0 0 26px;">
                                <label class="prism-label" for="evaluation_monitoring_end_date" style="color:var(--text-secondary);">
                                    Monitoring End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       id="evaluation_monitoring_end_date"
                                       name="evaluation_monitoring_end_date"
                                       class="prism-input"
                                       value="{{ old('evaluation_monitoring_end_date') }}"
                                       min="{{ now()->addDay()->toDateString() }}">
                                <p class="prism-hint mb-0">Runs from today through this date — the student is notified now, reminded before it ends, then it closes automatically.</p>
                            </div>
                        </div>

                    </div>
                </div>

                @php $standaloneNotes = in_array((int) $preselected, [3, 4, 5]); @endphp
                <div class="prism-card resolution-notes-card @if($standaloneNotes) resolution-notes-card--standalone @endif">
                    {{-- Categories 1-2 join this card to the component card above, whose
                         header already marks the step; only the standalone one leads. --}}
                    <div class="prism-card-header @if($standaloneNotes) prism-card-header--blue @endif">
                        <h2 class="prism-card-title">
                            Resolution Notes
                            <span class="{{ $standaloneNotes ? '' : 'text-muted' }}"
                                  style="font-weight:400;font-size:0.82rem;{{ $standaloneNotes ? 'opacity:.85;' : '' }}">
                                (optional)
                            </span>
                        </h2>
                        @if($standaloneNotes)
                            <span class="prism-badge resolution-current-step">Current Action</span>
                        @endif
                    </div>
                    <div class="prism-card-body" style="display:grid;gap:18px;">
                        {{-- One notice, not two: the severity and the irreversibility were
                             saying the same thing in different colours. --}}
                        <div class="resolution-warning">
                            @if($standaloneNotes)
                                <strong>Final review.</strong>
                                This sanction has serious, lasting consequences. Confirm due process is complete
                                &mdash; after submission, changes require an approved void request.
                            @else
                                <strong>Final review.</strong>
                                Verify the resolution details before continuing. After submission, changes require
                                an approved void request.
                            @endif
                        </div>

                        {{-- maxlength mirrors the controller's max:2000 so a long paste is
                             stopped here rather than bouncing back as a validation error. --}}
                        <textarea name="notes" class="prism-input" rows="3" maxlength="2000"
                                  placeholder="Additional context, conditions, or remarks regarding this resolution...">{{ old('notes') }}</textarea>

                        <div class="resolution-actions">
                            <a href="{{ route('officer.cases.show', $case) }}"
                               class="prism-btn prism-btn-outline">
                                Cancel
                            </a>
                            <button type="button"
                                    id="recordResolutionBtn"
                                    class="prism-btn prism-btn-primary">
                                Record Resolution
                            </button>
                        </div>
                    </div>
                </div>

                </div>

            </form>
        </div>

    </div>
</div>

<div class="modal fade prism-modal" id="confirmResolutionModal" tabindex="-1"
     aria-labelledby="confirmResolutionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div style="color:#64748b;font-size:.66rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;margin-bottom:3px;">
                        Final confirmation
                    </div>
                    <h2 class="modal-title" id="confirmResolutionModalLabel"
                        style="margin:0;color:#0f172a;font-size:1rem;font-weight:700;">
                        Record this resolution?
                    </h2>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p style="margin:0;color:#475569;font-size:.84rem;line-height:1.65;">
                    You are about to finalize
                    <strong class="prism-ref-number">{{ $case->case_number }}</strong>
                    for <strong>{{ $case->student?->full_name }}</strong>.
                </p>

                <div class="resolution-summary-grid" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:16px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
                    <div style="padding:11px 12px;border-right:1px solid #e2e8f0;">
                        <div style="color:#64748b;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Case status</div>
                        <div style="margin-top:3px;color:#1e293b;font-size:.82rem;font-weight:600;">Will be closed</div>
                    </div>
                    <div style="padding:11px 12px;">
                        <div style="color:#64748b;font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;">Decision</div>
                        <div style="margin-top:3px;color:#1e293b;font-size:.82rem;font-weight:600;">Final</div>
                    </div>
                </div>

                <div class="resolution-warning" style="margin-top:16px;">
                    Once recorded, the case will be closed and the sanctions take effect.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="prism-btn prism-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="prism-btn prism-btn-primary" form="resolutionForm">
                    Confirm
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// The category is fixed at classification and posted as a hidden field, so this
// runs once on load rather than on any user choice.
function currentCategory() {
    const field = document.querySelector('[name="intervention_category"]');
    const value = field ? parseInt(field.value, 10) : NaN;

    return Number.isNaN(value) ? null : value;
}

function onCategoryChange(val) {
    document.getElementById('cat1Fields').style.display   = val === 1 ? '' : 'none';
    document.getElementById('cat2Fields').style.display   = val === 2 ? '' : 'none';

    const probationTerms   = document.querySelector('[name="probation_terms"]');
    const probationEndDate = document.querySelector('[name="probation_end_date"]');

    if (probationTerms)   probationTerms.required   = val === 1;
    if (probationEndDate) probationEndDate.required  = val === 1;

    syncCounselingPickers();
}

function toggleUsHours(checkbox) {
    const input = document.getElementById('usHoursInput');
    const field = document.querySelector('[name="us_hours_assigned"]');
    input.style.display  = checkbox.checked ? '' : 'none';
    if (field) field.required = checkbox.checked;
    if (!checkbox.checked && field) field.value = '';
}

function toggleDisciplineEducation(checkbox) {
    const schedule = document.getElementById('disciplineEducationSchedule');
    const field = document.getElementById('discipline_education_session_at');
    if (!schedule || !field) return;
    schedule.style.display = checkbox.checked ? '' : 'none';
    field.required = checkbox.checked;
    if (!checkbox.checked) field.value = '';
}

function toggleEvaluationMonitoring(checkbox) {
    const period = document.getElementById('evaluationMonitoringPeriod');
    const field = document.getElementById('evaluation_monitoring_end_date');
    if (!period || !field) return;
    period.style.display = checkbox.checked ? '' : 'none';
    field.required = checkbox.checked;
    if (!checkbox.checked) field.value = '';
}

// The picker is rendered once per category, so only the one belonging to the active
// category — and only when its Counseling Referral box is ticked — may post a value.
// Disabled fields are omitted from the submission, so the other copy cannot override it.
function syncCounselingPickers() {
    const category = currentCategory();

    [1, 2].forEach(n => {
        const picker = document.getElementById('counselingOfficerPicker' + n);
        const toggle = document.getElementById('counselingToggle' + n);
        const reason = document.getElementById('referralReason' + n);
        if (!picker || !toggle) return;

        const active = category === n && toggle.checked;
        picker.style.display = active ? '' : 'none';
        // Disabling is enough to keep each out of the payload; values are left intact so
        // they survive toggling between categories.
        if (reason) { reason.disabled = !active; reason.required = active; }
        picker.querySelectorAll('input[name="urgency"]').forEach(input => {
            input.disabled = !active;
            input.required = active;
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const category = currentCategory();
    if (category) onCategoryChange(category);

    const usToggle = document.getElementById('usHoursToggle');
    if (usToggle) toggleUsHours(usToggle);
    const disciplineToggle = document.getElementById('disciplineEducationToggle');
    if (disciplineToggle) toggleDisciplineEducation(disciplineToggle);

    const monitoringToggle = document.querySelector('input[name="evaluation_monitoring_required"]');
    if (monitoringToggle) toggleEvaluationMonitoring(monitoringToggle);
    syncCounselingPickers();
});

document.getElementById('resolutionForm').addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        e.preventDefault();
        this.reportValidity();
    }
});

document.getElementById('recordResolutionBtn').addEventListener('click', function () {
    const form = document.getElementById('resolutionForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmResolutionModal')).show();
});
</script>
@endpush
