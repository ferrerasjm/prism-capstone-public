@extends($layout ?? 'layouts.staff')
@php
    $reportsRoutePrefix = $reportsRoutePrefix ?? 'officer';
    $highCriticalCount = (int) (($bandCounts['high'] ?? 0) + ($bandCounts['critical'] ?? 0));
    $movementImproved = (int) ($movementCounts['improved'] ?? 0);
    $movementStable = (int) ($movementCounts['stable'] ?? 0);
    $movementIncreased = (int) ($movementCounts['worsened'] ?? 0);
    $movementNew = (int) ($movementCounts['new'] ?? 0);
    $unsupportedHighRisk = (int) ($interventionCoverage['high_risk_without_active_support'] ?? 0);
    $highRiskTotal = (int) ($interventionCoverage['high_risk_total'] ?? 0);
    $highRiskWithSupport = (int) ($interventionCoverage['high_risk_with_active_support'] ?? 0);
    $supportCoveragePct = $highRiskTotal > 0 ? round(($highRiskWithSupport / $highRiskTotal) * 100) : 0;
    $completedSupportTotal = (int) $outcomeSummary->sum();
    $hasSupportData = $highRiskTotal > 0
        || ($interventionCoverage['created_in_period'] ?? 0) > 0
        || ($interventionCoverage['overdue_follow_ups'] ?? 0) > 0;

    $bandLabels = [
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'critical' => 'Critical',
    ];
    $bandBadges = [
        'low' => 'prism-badge-green',
        'moderate' => 'prism-badge-orange',
        'high' => 'prism-badge-red',
        'critical' => 'prism-badge-red',
    ];
    $bandColors = [
        'low' => '#16a34a',
        'moderate' => '#f59e0b',
        'high' => '#dc2626',
        'critical' => '#991b1b',
    ];
@endphp
@section('title', 'Risk Analytics Report')
@section('content')
@include('officer.reports._print_header', ['reportTitle' => "Risk Analytics Report - {$from} to {$to}"])

<style>
    .risk-report-shell {
        display: grid;
        gap: 16px;
    }

    .risk-report-brief {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 0;
        overflow: hidden;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .risk-summary-item {
        min-width: 0;
        padding: 16px 18px;
        border-right: 1px solid var(--border);
    }

    .risk-summary-item:last-child {
        border-right: 0;
    }

    .risk-summary-value {
        color: #0f172a;
        font-size: 1.4rem;
        font-weight: 800;
        line-height: 1;
    }

    .risk-summary-label {
        margin-top: .5rem;
        color: #475569;
        font-size: .78rem;
        font-weight: 600;
        line-height: 1.4;
    }

    .risk-summary-note {
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .72rem;
        line-height: 1.4;
    }

    .risk-summary-item.attention .risk-summary-value,
    .risk-summary-item.attention .risk-summary-label {
        color: {{ $unsupportedHighRisk > 0 ? '#dc2626' : '#15803d' }};
    }

    .risk-summary-item.high-risk .risk-summary-value,
    .risk-summary-item.high-risk .risk-summary-label { color: #b91c1c; }

    .risk-summary-item.increased .risk-summary-value,
    .risk-summary-item.increased .risk-summary-label { color: #d97706; }

    .risk-summary-item.assessed .risk-summary-value,
    .risk-summary-item.assessed .risk-summary-label { color: #1d4ed8; }

    .risk-action-panel,
    .risk-evidence-panel,
    .risk-method-note {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .risk-action-panel,
    .risk-evidence-panel {
        overflow: hidden;
    }

    .risk-section-heading {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        gap: 14px;
        flex-wrap: wrap;
        margin-top: 4px;
    }

    .risk-section-heading h2 {
        color: var(--text-primary);
        font-size: 1.05rem;
        font-weight: 900;
        margin: 0;
    }

    .risk-section-heading p {
        color: var(--text-muted);
        font-size: .83rem;
        margin: 3px 0 0;
    }

    .risk-workbench-grid + .risk-section-heading {
        margin-top: 10px;
    }

    .risk-workbench-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 16px;
    }

    .risk-panel-header {
        align-items: flex-start;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 16px;
        background: #fbfcfe;
    }

    .risk-panel-title {
        color: var(--text-primary);
        font-size: .98rem;
        font-weight: 900;
        margin: 0;
    }

    .risk-panel-sub {
        color: var(--text-muted);
        font-size: .78rem;
        line-height: 1.45;
        margin: 4px 0 0;
    }

    .risk-review-list {
        display: grid;
        gap: 10px;
        padding: 16px 18px 18px;
    }

    .risk-student-review {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        display: grid;
        gap: 12px;
        grid-template-columns: minmax(0, 1fr) auto auto;
        padding: 14px;
    }

    .risk-score-chip {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        color: var(--text-primary);
        font-size: .78rem;
        font-weight: 900;
        padding: 6px 10px;
        white-space: nowrap;
    }

    .risk-empty-state {
        align-items: center;
        color: var(--text-secondary);
        display: flex;
        font-size: .86rem;
        gap: .5rem;
        justify-content: center;
        line-height: 1.6;
        padding: 28px 20px;
        text-align: center;
    }

    .risk-empty-state i {
        color: #16a34a;
        font-size: 1.35rem;
    }

    .risk-evidence-grid {
        display: grid;
        grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr);
        gap: 16px;
    }

    .risk-band-row {
        display: grid;
        gap: 8px;
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
    }

    .risk-band-row:last-child {
        border-bottom: 0;
    }

    .risk-row-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .risk-bar-track {
        background: #e5e7eb;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }

    .risk-bar-fill {
        border-radius: 999px;
        height: 100%;
    }

    .risk-driver-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 14px;
        align-items: center;
        padding: 15px 18px;
        border-bottom: 1px solid var(--border);
    }

    .risk-driver-row:last-child {
        border-bottom: 0;
    }

    .risk-driver-rank {
        align-items: center;
        background: #ede9fe;
        border-radius: 999px;
        color: #5b21b6;
        display: inline-flex;
        font-size: .72rem;
        font-weight: 900;
        height: 26px;
        justify-content: center;
        margin-right: 8px;
        width: 26px;
    }

    .risk-progress-panel {
        padding: 20px;
    }

    .risk-progress-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
        margin-top: 14px;
    }

    .risk-progress-note {
        background: #f8fafc;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
    }

    .risk-progress-note .eyebrow {
        color: var(--text-muted);
        font-size: .7rem;
        font-weight: 900;
        letter-spacing: .08em;
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .risk-progress-note p {
        color: var(--text-secondary);
        font-size: .82rem;
        line-height: 1.6;
        margin: 0;
    }

    .risk-progress-note strong {
        color: var(--text-primary);
    }

    .risk-movement-stats {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: .5rem;
    }

    .risk-movement-stat {
        min-width: 0;
    }

    .risk-movement-stat strong {
        display: block;
        font-size: 1rem;
        line-height: 1.1;
    }

    .risk-movement-stat span {
        display: block;
        margin-top: .2rem;
        color: var(--text-muted);
        font-size: .7rem;
        line-height: 1.25;
    }

    .risk-movement-stat.increased strong { color: #dc2626; }
    .risk-movement-stat.improved strong { color: #15803d; }
    .risk-movement-stat.stable strong { color: #475569; }
    .risk-movement-stat.new strong { color: #1d4ed8; }

    .risk-method-note {
        background: #eff6ff;
        border-color: #bfdbfe;
        border-radius: 10px;
        color: var(--text-secondary);
        font-size: .82rem;
        line-height: 1.6;
        padding: 16px 18px;
    }

    .sdo-content .risk-report-filters {
        margin-bottom: 0;
        border-radius: 10px;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .risk-overview {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 1px solid var(--border);
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 4px 14px rgba(15, 23, 42, .035);
    }

    .risk-overview .risk-report-brief {
        order: 1;
        border: 0;
        border-bottom: 1px solid var(--border);
        border-radius: 0;
        box-shadow: none;
    }

    .sdo-content .risk-overview .risk-report-filters {
        order: 2;
        margin: 0;
        border: 0;
        border-radius: 0;
        background: #fbfcfe;
        box-shadow: none;
    }

    .risk-method-note i {
        color: #2563eb;
        margin-right: 6px;
    }

    @media (max-width: 1199.98px) {
        .risk-workbench-grid,
        .risk-evidence-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 767.98px) {
        .risk-student-review,
        .risk-progress-grid {
            grid-template-columns: 1fr;
        }

        .risk-report-brief,
        .risk-workbench-grid,
        .risk-evidence-grid,
        .risk-report-shell {
            gap: 14px;
        }

        .risk-driver-row {
            grid-template-columns: 1fr;
            gap: 4px;
        }

        .risk-movement-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .risk-report-brief {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0;
        }

        .risk-summary-item:nth-child(-n+2) {
            border-bottom: 1px solid var(--border);
        }

        .risk-summary-item:nth-child(2n) {
            border-right: 0;
        }
    }
</style>

@include('officer.reports._generated_layout')
<div class="prism-content report-page">

    <div class="prism-page-header d-flex align-items-center justify-content-between flex-wrap gap-2 report-actions">
        <div>
            <x-breadcrumbs :items="[
                ['label' => 'Reports', 'url' => route($reportsRoutePrefix.'.reports.index')],
                ['label' => 'Risk Analytics Report'],
            ]" />
            <h1 class="prism-page-title">Risk Analytics Report</h1>
            <p class="prism-page-sub">
                Report period:
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }}
                &mdash;
                {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
            </p>
        </div>
        @include('officer.reports._export_actions', [
            'type' => 'risk_analytics_report',
            'filters' => [
                'from' => $from,
                'to' => $to,
                'academic_period_id' => $academicPeriod?->id,
            ],
        ])
    </div>

    <div class="risk-overview">
    <div class="prism-filter-bar report-actions risk-report-filters">
        <form method="GET"
              action="{{ route($reportsRoutePrefix.'.reports.risk-analytics') }}"
              class="row g-2 align-items-end" data-auto-submit>
            @include('officer.reports._academic_period_filter')
            <div class="col-12 col-sm-4">
                <label for="riskAnalyticsFrom" class="prism-label">Start Date</label>
                <input id="riskAnalyticsFrom" type="date" name="from" class="prism-input" value="{{ $from }}">
            </div>
            <div class="col-12 col-sm-4">
                <label for="riskAnalyticsTo" class="prism-label">End Date</label>
                <input id="riskAnalyticsTo" type="date" name="to" class="prism-input" value="{{ $to }}">
            </div>
            <div class="col-12 col-sm-auto">
            </div>
        </form>
    </div>

        <div class="risk-report-brief">
            <div class="risk-summary-item attention">
                <div class="risk-summary-value">{{ $unsupportedHighRisk }}</div>
                <div class="risk-summary-label">Need Active Support</div>
                <div class="risk-summary-note">High/Critical students without an active action</div>
            </div>
            <div class="risk-summary-item high-risk">
                <div class="risk-summary-value">{{ $highCriticalCount }}</div>
                <div class="risk-summary-label">High/Critical Risk</div>
                <div class="risk-summary-note">Students requiring priority monitoring</div>
            </div>
            <div class="risk-summary-item increased">
                <div class="risk-summary-value">{{ $movementIncreased }}</div>
                <div class="risk-summary-label">Risk Increased</div>
                <div class="risk-summary-note">Score records that worsened during the period</div>
            </div>
            <div class="risk-summary-item assessed">
                <div class="risk-summary-value">{{ $totalAssessed }}</div>
                <div class="risk-summary-label">Students Assessed</div>
                <div class="risk-summary-note">Students included in this report</div>
            </div>
        </div>
    </div>

    <div class="risk-report-shell">
        <div class="risk-workbench-grid">
            <section class="risk-action-panel">
                <div class="risk-panel-header">
                    <div>
                        <h2 class="risk-panel-title">
                            Students Requiring Support
                        </h2>
                        <p class="risk-panel-sub">High/Critical students without an active support action.</p>
                    </div>
                </div>
                @if($studentsNeedingSupport->isEmpty())
                    <div class="risk-empty-state">
                        Every High/Critical student currently has an active support action.
                    </div>
                @else
                    <div class="risk-review-list">
                        @foreach($studentsNeedingSupport as $score)
                            <div class="risk-student-review">
                                <div>
                                    <div class="prism-cell-primary">{{ $score->student?->list_name ?? 'Unknown student' }}</div>
                                    <div class="prism-cell-sub prism-ref-number">
                                        {{ $score->student?->studentProfile?->student_number ?? 'No student number' }}
                                        &middot; Last scored {{ $score->computed_at?->format('M d, Y') }}
                                    </div>
                                </div>
                                <span class="prism-badge {{ $bandBadges[$score->risk_band] ?? 'prism-badge-gray' }}">
                                    {{ ucfirst($score->risk_band) }}
                                </span>
                                <span class="risk-score-chip">Score {{ number_format((float) $score->composite_score, 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="risk-section-heading">
            <div>
                <h2>Risk Evidence</h2>
                <p>Current risk distribution and the factors contributing to student scores.</p>
            </div>
        </div>

        <div class="risk-method-note">
            <i class="bi bi-info-circle"></i>
            <strong>How to interpret this report:</strong>
            PRISM uses documented rules and weights to support officer review. Risk scores indicate where attention may
            be needed; they do not replace case assessment or professional judgment.
        </div>

        <div class="risk-evidence-grid">
            <section class="risk-evidence-panel">
                <div class="risk-panel-header">
                    <div>
                        <h3 class="risk-panel-title">Students by Risk Level</h3>
                        <p class="risk-panel-sub">Distribution of students with scores computed within the selected date range.</p>
                    </div>
                </div>
                @foreach($bandLabels as $band => $label)
                    @php
                        $count = (int) ($bandCounts[$band] ?? 0);
                        $pct = $totalAssessed > 0 ? round(($count / $totalAssessed) * 100, 1) : 0;
                    @endphp
                    <div class="risk-band-row">
                        <div class="risk-row-topline">
                            <div>
                                <span class="prism-badge {{ $bandBadges[$band] }}">{{ $label }}</span>
                                <span class="prism-cell-sub ms-1">{{ $count }} student(s)</span>
                            </div>
                            <strong style="font-size:.85rem;">{{ $pct }}%</strong>
                        </div>
                        <div class="risk-bar-track">
                            <div class="risk-bar-fill" style="width:{{ $pct }}%;background:{{ $bandColors[$band] }};"></div>
                        </div>
                    </div>
                @endforeach
            </section>

            <section class="risk-evidence-panel">
                <div class="risk-panel-header">
                    <div>
                        <h3 class="risk-panel-title">Leading Risk Factors</h3>
                        <p class="risk-panel-sub">Ranked by contribution to current student risk scores.</p>
                    </div>
                </div>
                @if($topFactors->isEmpty())
                    <div class="risk-empty-state">
                        No risk factor data available yet.
                    </div>
                @else
                    @foreach($topFactors->take(6) as $factor)
                        <div class="risk-driver-row">
                            <div>
                                <div class="prism-cell-primary">
                                    <span class="risk-driver-rank">{{ $loop->iteration }}</span>
                                    {{ $factor['factor_name'] }}
                                </div>
                                <div class="prism-cell-sub mt-1">
                                    Students affected: {{ $factor['students_triggered'] }}
                                    &middot; Average score impact: {{ number_format((float) $factor['average_contribution'], 2) }}
                                </div>
                                <div class="risk-bar-track mt-2">
                                    <div class="risk-bar-fill" style="width:{{ min(100, (float) $factor['share']) }}%;background:#7c3aed;"></div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div style="font-weight:900;color:#5b21b6;">{{ $factor['share'] }}%</div>
                                <div class="prism-cell-sub">share</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </section>
        </div>

        <section class="risk-evidence-panel risk-progress-panel">
            <div class="risk-section-heading" style="margin:0;">
                <div>
                    <h2>Risk Movement and Support Outcomes</h2>
                    <p>Track score changes, active support, and results after follow-up.</p>
                </div>
                <span class="prism-badge prism-badge-blue">
                    Support coverage: {{ $highRiskTotal > 0 ? $supportCoveragePct.'%' : 'Not applicable' }}
                </span>
            </div>

            <div class="risk-progress-grid">
                <div class="risk-progress-note">
                    <div class="eyebrow">Score Changes</div>
                    <div class="risk-movement-stats">
                        <div class="risk-movement-stat increased">
                            <strong>{{ $movementIncreased }}</strong>
                            <span>Increased</span>
                        </div>
                        <div class="risk-movement-stat improved">
                            <strong>{{ $movementImproved }}</strong>
                            <span>Improved</span>
                        </div>
                        <div class="risk-movement-stat stable">
                            <strong>{{ $movementStable }}</strong>
                            <span>Stable</span>
                        </div>
                        <div class="risk-movement-stat new">
                            <strong>{{ $movementNew }}</strong>
                            <span>New</span>
                        </div>
                    </div>
                </div>

                <div class="risk-progress-note">
                    <div class="eyebrow">Active Support</div>
                    @if($hasSupportData)
                        <p>
                            <strong>{{ $highRiskWithSupport }}</strong> of
                            <strong>{{ $highRiskTotal }}</strong> High/Critical student(s) have active support.
                            {{ $interventionCoverage['created_in_period'] ?? 0 }} support action(s) were created,
                            {{ $interventionCoverage['completed_in_period'] ?? 0 }} completed, and
                            {{ $interventionCoverage['overdue_follow_ups'] ?? 0 }} follow-up(s) are overdue.
                        </p>
                    @else
                        <p>No High/Critical students currently need an active support action.</p>
                    @endif
                </div>

                <div class="risk-progress-note">
                    <div class="eyebrow">Follow-up Outcomes</div>
                    @if($completedSupportTotal > 0)
                        <p>
                            After completed support actions,
                            <strong>{{ $outcomeSummary['improved'] ?? 0 }}</strong> improved,
                            <strong>{{ $outcomeSummary['stable'] ?? 0 }}</strong> stayed stable, and
                            <strong>{{ $outcomeSummary['worsened'] ?? 0 }}</strong> increased.
                        </p>
                    @else
                        <p>No completed support actions yet. Outcomes can be measured once follow-ups are closed.</p>
                    @endif
                </div>
            </div>
        </section>

    </div>
</div>
@endsection
