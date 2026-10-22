@extends('layouts.coordinator')
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

    .case-info-disclosure {
        overflow: hidden;
    }

    .case-info-disclosure-summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        cursor: pointer;
        list-style: none;
    }

    .case-info-disclosure-summary::-webkit-details-marker {
        display: none;
    }

    .case-info-disclosure-summary:focus-visible {
        outline: 2px solid #3156c7;
        outline-offset: -2px;
    }

    .case-info-disclosure-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex: 0 0 auto;
        color: #3156c7;
        font-size: .72rem;
        font-weight: 700;
    }

    .case-info-disclosure-hide {
        display: none;
    }

    .case-info-disclosure-toggle i {
        font-size: .7rem;
        transition: transform .15s ease;
    }

    .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-show {
        display: none;
    }

    .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-hide {
        display: inline;
    }

    .case-info-disclosure[open]>.case-info-disclosure-summary .case-info-disclosure-toggle i {
        transform: rotate(180deg);
    }

    .case-info-disclosure:not([open])>.case-info-disclosure-summary {
        border-bottom: 0;
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
            ['label' => 'UPCC Cases', 'url' => route('coordinator.cases.index')],
            ['label' => $case->case_number, 'url' => route('coordinator.cases.show', $case)],
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
            <details class="prism-card mb-4 case-info-disclosure">
                <summary class="prism-card-header case-info-disclosure-summary">
                    <h2 class="prism-card-title">Case Summary</h2>
                    <span class="case-info-disclosure-toggle" aria-hidden="true">
                        <span class="case-info-disclosure-show">Show Details</span>
                        <span class="case-info-disclosure-hide">Hide Details</span>
                        <i class="bi bi-chevron-down"></i>
                    </span>
                </summary>
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
                                offense
                            </td>
                        </tr>
                        <tr>
                            <th>Officer's Original Category</th>
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
                                <strong>University Panel for Case Conference Resolution</strong>
                            </td>
                        </tr>
                    </table>
                </div>
            </details>

            @if($case->incidentReport)
                <details class="prism-card mb-4 case-info-disclosure">
                    <summary class="prism-card-header case-info-disclosure-summary">
                        <h2 class="prism-card-title">Incident Details</h2>
                        <span class="case-info-disclosure-toggle" aria-hidden="true">
                            <span class="case-info-disclosure-show">Show Details</span>
                            <span class="case-info-disclosure-hide">Hide Details</span>
                            <i class="bi bi-chevron-down"></i>
                        </span>
                    </summary>
                    <div class="prism-card-body">
                        @include('officer.cases._incident-summary', [
                            'incidentReport' => $case->incidentReport,
                            'incidentSummaryClass' => 'case-incident-summary--flush',
                        ])
                    </div>
                </details>
            @endif

        </div>

        <div class="col-12 col-lg-8 order-1 order-lg-2">
            <form method="POST"
                  action="{{ route('coordinator.cases.resolution.store', $case) }}"
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
                            $configuredCategories = \App\Models\OffenseCategory::query()->get()->keyBy('number');
                            foreach ($cats as $number => &$categoryOption) {
                                $configured = $configuredCategories->get($number);
                                if (!$configured?->is_active && (int) $case->assigned_sanction_category !== $number) {
                                    unset($cats[$number]);
                                } elseif ($configured) {
                                    $categoryOption['label'] = "Category {$number} — {$configured->intervention}";
                                    $categoryOption['desc'] = $configured->description ?: $categoryOption['desc'];
                                }
                            }
                            unset($categoryOption);
                            $officerAssigned = $case->assigned_sanction_category;
                            $preselected = (int) old('intervention_category', $officerAssigned);
                        @endphp

                        <div class="prism-note mb-3">
                            <i class="bi bi-info-circle me-1"></i>
                            <div>
                                The officer originally assigned <strong>Category {{ $officerAssigned }}</strong> before UPCC referral.
                                Select the category that reflects the UPCC's actual decision — it may differ from the officer's
                                initial assignment and is not locked to it.
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:8px;">
                            @foreach($cats as $val => $cat)
                                @php $isSelected = $preselected === $val; @endphp
                                <label style="display:flex;align-items:flex-start;gap:12px;
                                              border:2px solid {{ $isSelected ? 'var(--blue)' : 'var(--border)' }};
                                              border-radius:var(--radius-sm);padding:10px 14px;
                                              background:#FFFFFF;
                                              cursor:pointer;transition:border-color .15s;"
                                       id="catLabel{{ $val }}">
                                    <input type="radio" name="intervention_category"
                                           value="{{ $val }}"
                                           {{ $isSelected ? 'checked' : '' }}
                                           onchange="onCategoryChange({{ $val }})"
                                           required
                                           style="margin-top:3px;flex-shrink:0;">
                                    <div>
                                        <div style="font-weight:700;font-size:0.875rem;
                                                    display:flex;align-items:center;gap:8px;">
                                            {{ $cat['label'] }}
                                            @if($val === (int) $officerAssigned)
                                                <span class="prism-badge prism-badge-blue"
                                                      style="font-size:0.63rem;">
                                                    Officer's Original Assignment
                                                </span>
                                            @endif
                                        </div>
                                        <div style="font-size:0.82rem;color:var(--text-secondary);">
                                            {{ $cat['desc'] }}
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="resolution-connected-group">

                <div id="cat1Fields" class="prism-card mb-4 resolution-component-card"
                     style="{{ (int)$preselected === 1 ? '' : 'display:none;' }}">
                    <div class="prism-card-header prism-card-header--blue">
                        <h2 class="prism-card-title" style="color:#FFFFFF;">Probation Details</h2>
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
                            <div class="col-12 col-sm-6">
                                <label class="prism-label">
                                    Probation End Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" name="probation_end_date"
                                       class="prism-input"
                                       value="{{ old('probation_end_date') }}"
                                       min="{{ now()->addDay()->format('Y-m-d') }}">
                                <p class="prism-hint">
                                    Set based on the current academic calendar.
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
                                       min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}">
                                <p class="prism-hint mb-0">The student will receive an in-system notification and email after the resolution is recorded.</p>
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
                                <p class="prism-hint mb-0">Monitoring runs from today until this date. The student is notified now, reminded before it ends, and the period closes on its own unless a new case is opened.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <div id="cat345Fields" class="prism-card mb-4 resolution-component-card"
                     style="{{ in_array((int)$preselected, [3,4,5]) ? '' : 'display:none;' }}">
                    <div class="prism-card-header prism-card-header--blue">
                        <h2 class="prism-card-title" style="color:#FFFFFF;">Sanction Confirmation</h2>
                    </div>
                    <div class="prism-card-body">
                        <div class="prism-alert prism-alert-danger" style="margin:0;">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <div>
                                This sanction category carries serious and lasting consequences
                                for the student. Confirm that all due process requirements have
                                been fulfilled before proceeding.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="prism-card resolution-notes-card">
                    <div class="prism-card-header">
                        <h2 class="prism-card-title">
                            Resolution Notes
                            <span class="text-muted"
                                  style="font-weight:400;font-size:0.82rem;">
                                (optional)
                            </span>
                        </h2>
                    </div>
                    <div class="prism-card-body" style="display:grid;gap:18px;">
                        <div class="resolution-warning">
                            <strong>Final review.</strong>
                            Verify the resolution details before continuing. After submission, changes require
                            an approved void request.
                        </div>

                        {{-- maxlength mirrors the controller's max:2000 so a long paste is
                             stopped here rather than bouncing back as a validation error. --}}
                        <textarea name="notes" class="prism-input" rows="3" maxlength="2000"
                                  placeholder="Additional context, conditions, or remarks regarding this resolution...">{{ old('notes') }}</textarea>

                        <div class="resolution-actions">
                            <a href="{{ route('coordinator.cases.show', $case) }}"
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
function onCategoryChange(val) {
    [1,2,3,4,5].forEach(n => {
        const label = document.getElementById('catLabel' + n);
        if (label) {
            label.style.borderColor = n === val ? 'var(--blue)' : 'var(--border)';
        }
    });

    document.getElementById('cat1Fields').style.display   = val === 1 ? '' : 'none';
    document.getElementById('cat2Fields').style.display   = val === 2 ? '' : 'none';
    document.getElementById('cat345Fields').style.display = [3,4,5].includes(val) ? '' : 'none';

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

function toggleEvaluationMonitoring(checkbox) {
    const period = document.getElementById('evaluationMonitoringPeriod');
    const field = document.getElementById('evaluation_monitoring_end_date');
    if (!period || !field) return;
    period.style.display = checkbox.checked ? '' : 'none';
    field.required = checkbox.checked;
    if (!checkbox.checked) field.value = '';
}

function toggleDisciplineEducation(checkbox) {
    const schedule = document.getElementById('disciplineEducationSchedule');
    const field = document.getElementById('discipline_education_session_at');
    if (!schedule || !field) return;
    schedule.style.display = checkbox.checked ? '' : 'none';
    field.required = checkbox.checked;
    if (!checkbox.checked) field.value = '';
}

// The picker is rendered once per category, so only the one belonging to the active
// category — and only when its Counseling Referral box is ticked — may post a value.
// Disabled fields are omitted from the submission, so the other copy cannot override it.
function syncCounselingPickers() {
    const checked = document.querySelector('[name="intervention_category"]:checked');
    const category = checked ? parseInt(checked.value) : null;

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
    const checked = document.querySelector('[name="intervention_category"]:checked');
    if (checked) onCategoryChange(parseInt(checked.value));

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
