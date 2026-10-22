<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\UsAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Assembles the structured document model (sections, tables, totals) that
 * report exports render from, keeping layout decisions out of the
 * individual report queries.
 */
final class ReportDocumentBuilder
{
    public function __construct(private readonly ReportService $reports) {}

    /**
     * Set for the lifetime of a single build() call: the discipline officer whose
     * assigned departments scope the underlying report data (null = unscoped).
     */
    private ?User $officer = null;

    /**
     * Build a presentation-neutral report document from persisted report data.
     *
     * @return array{type:string,title:string,period_start:string,period_end:string,filters:array,summary:array,sections:array}
     */
    public function build(string $type, array $filters, ?User $officer = null): array
    {
        $this->officer = $officer;

        return match ($type) {
            'monthly_violation_summary' => $this->monthlyViolation($filters),
            'case_summary' => $this->caseSummary($filters),
            'student_case_report' => $this->studentCase($filters),
            'us_compliance' => $this->universityService($filters),
            'upcc_case_report' => $this->upccCases($filters),
            'registration_hold_report' => $this->registrationHolds($filters),
            'escalation_report' => $this->escalations($filters),
            'risk_analytics_report' => $this->riskAnalytics($filters),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    private function monthlyViolation(array $filters): array
    {
        $period = $this->academicPeriod($filters);
        $year = isset($filters['year']) ? (int) $filters['year'] : ($period?->starts_on->year ?? now()->year);
        $month = isset($filters['month']) ? (int) $filters['month'] : null;
        $data = $this->reports->monthlyViolation($year, $month, $period, $this->officer);

        [$from, $to] = $period
            ? [$period->starts_on->toDateString(), $period->ends_on->toDateString()]
            : $this->yearRange($year, $month);

        $activity = $data['months']->map(function (string $label, int $number) use ($data): array {
            $minor = $data['minorByMonth']->get($number);
            $major = $data['majorByMonth']->get($number);

            return [
                $label,
                (int) ($minor?->total ?? 0),
                (int) ($major?->total ?? 0),
                (int) ($minor?->resolved_count ?? 0),
                (int) ($major?->resolved_count ?? 0),
            ];
        })->values();

        $minorTotal = $activity->sum(fn (array $row) => $row[1]);
        $majorTotal = $activity->sum(fn (array $row) => $row[2]);

        return $this->document(
            'monthly_violation_summary',
            'Monthly Violation Summary',
            $from,
            $to,
            $this->filterLabels($period, null, ['Year' => $year]),
            [
                ['Total Cases', $minorTotal + $majorTotal],
                ['Minor Cases', $minorTotal],
                ['Major Cases', $majorTotal],
                ['Escalations', (int) $data['escalations']],
            ],
            [
                $this->section('Monthly Case Activity', ['Month', 'Minor Cases', 'Major Cases', 'Minor Closed', 'Major Closed'], $activity),
                $this->section('Confirmed Offense Findings', ['Code', 'Offense', 'Type', 'Count'], $data['topOffenses']->map(fn ($offense) => [
                    $offense->code,
                    $offense->title,
                    $this->label($offense->offense_type),
                    (int) $offense->occurrence_count,
                ])),
                $this->section('Violations by Program', ['Program', 'Minor Cases', 'Major Cases', 'Total Cases'], $data['programViolationCounts']->map(fn (array $row) => [
                    $row['program'], $row['minor_count'], $row['major_count'], $row['total_count'],
                ])),
                $this->section('Programs with Lowest Minor Case Counts', ['Program', 'Minor Cases'], $data['lowestMinorPrograms']->map(fn (array $row) => [
                    $row['program'], $row['minor_count'],
                ])),
                $this->section('Programs with Lowest Major Case Counts', ['Program', 'Major Cases'], $data['lowestMajorPrograms']->map(fn (array $row) => [
                    $row['program'], $row['major_count'],
                ])),
            ],
            [
                $this->barChart('Violations by Program', 'Case volume by program and severity', ['Minor' => '#3156c7', 'Major' => '#dc2626'], $data['programViolationCounts']
                    ->filter(fn (array $row) => $row['total_count'] > 0)
                    ->sortByDesc('total_count')
                    ->take(10)
                    ->map(fn (array $row) => [
                        'label' => $row['program'],
                        'values' => ['Minor' => $row['minor_count'], 'Major' => $row['major_count']],
                    ])->values()->all()),
            ]
        );
    }

    private function caseSummary(array $filters): array
    {
        [$from, $to, $period, $program] = $this->datedFilters($filters);
        $data = $this->reports->caseSummary($from, $to, $period, $program, $this->officer);

        $monthlyRows = $data['months']->map(function (string $label, int $key) use ($data): array {
            $minor = $data['minorByMonth']->get($key);
            $major = $data['majorByMonth']->get($key);

            return [$label, (int) ($minor?->total ?? 0), (int) ($major?->total ?? 0)];
        })->values();

        $statusColors = [
            'open' => '#2563eb',
            'pending_nte' => '#ea580c',
            'awaiting_explanation' => '#7c3aed',
            'under_review' => '#0f766e',
            'pending_upcc' => '#4338ca',
            'pending_resolution' => '#b45309',
            'resolved' => '#16a34a',
            'dismissed' => '#64748b',
            'voided' => '#dc2626',
        ];

        return $this->document(
            'case_summary',
            'Case Summary Report',
            $from,
            $to,
            $this->filterLabels($period, $program),
            [
                ['Total Cases', $data['total']],
                ['Minor Cases', $data['minor']],
                ['Major Cases', $data['major']],
                ['Resolved', $data['resolved']],
                ['Dismissed', $data['dismissed']],
                ['Pending', $data['pending']],
            ],
            [
                $this->section('Monthly Case Activity', ['Month', 'Minor Cases', 'Major Cases'], $monthlyRows),
                $this->section('Cases by Status', ['Status', 'Cases'], collect($data['byStatus'])->map(fn ($count, $status) => [
                    $this->label((string) $status), (int) $count,
                ])->values()),
                $this->section('Confirmed Offense Findings', ['Code', 'Offense', 'Type', 'Count'], $data['byOffenseType']->map(fn ($offense) => [
                    $offense->code, $offense->title, $this->label($offense->offense_type), (int) $offense->total,
                ])),
            ],
            [
                $this->pieChart('Cases by Status', 'Status distribution of cases within the selected date range', collect($data['byStatus'])->map(fn ($count, $status) => [
                    'label' => $this->label((string) $status),
                    'value' => (int) $count,
                    'color' => $statusColors[$status] ?? '#64748b',
                ])->values()->all()),
            ]
        );
    }

    private function studentCase(array $filters): array
    {
        $data = $this->reports->studentCase((int) $filters['student_id'], $this->officer);
        $student = $data['student'];
        $from = $data['cases']->min('created_at')?->toDateString() ?? today()->toDateString();
        $activeSanctions = $data['sanctions']->whereIn('status', ['pending', 'in_progress'])->count();

        return $this->document(
            'student_case_report',
            'Student Case Report',
            $from,
            today()->toDateString(),
            [
                ['Student Number', $student->studentProfile?->student_number ?? 'Not assigned'],
                ['Student', $student->list_name],
                ['Program', $student->studentProfile?->program ?? 'Not assigned'],
                ['Year Level', $student->studentProfile?->year_level ?? 'Not assigned'],
                ['Email', $student->email],
            ],
            [
                ['Cases', $data['cases']->count()],
                ['Sanctions', $data['sanctions']->count()],
                ['Active Sanctions', $activeSanctions],
                ['University Service Assignments', $data['usAssignments']->count()],
                ['Current Risk Level', $data['riskScore'] ? $this->label($data['riskScore']->risk_band) : 'Not assessed'],
                ['Registration Hold', $data['hold'] ? 'Active' : 'None'],
            ],
            [
                $this->section('Cases', ['Case Number', 'Opened', 'Type', 'Status', 'Confirmed Offenses', 'Resolved'], $data['cases']->map(fn ($case) => [
                    $case->case_number,
                    $this->date($case->created_at),
                    $this->label($case->case_type),
                    $this->label($case->status),
                    $case->confirmedOffenses->map(fn ($offense) => "{$offense->code} - {$offense->title}")->join('; ') ?: 'None',
                    $this->date($case->resolved_at),
                ])),
                $this->section('Sanctions and Interventions', ['Type', 'Related Case', 'Status', 'Due Date', 'Completed'], $data['sanctions']->map(fn ($sanction) => [
                    $sanction->typeLabel(),
                    $sanction->resolution?->case?->case_number ?? 'Not available',
                    $this->label($sanction->status),
                    $this->date($sanction->due_date),
                    $this->date($sanction->completed_at),
                ])),
                $this->section('University Service', ['Office', 'Required Time', 'Rendered Time', 'Remaining Time', 'Status', 'Target Date'], $data['usAssignments']->map(fn ($assignment) => [
                    $assignment->office_name,
                    $assignment->requiredDuration(),
                    $assignment->renderedDuration(),
                    $assignment->remainingDuration(),
                    $this->label($assignment->status),
                    $this->date($assignment->target_completion_date),
                ])),
            ]
        );
    }

    private function universityService(array $filters): array
    {
        [$from, $to, $period, $program] = $this->datedFilters($filters);
        $data = $this->reports->usCompliance($from, $to, $period, $program, $this->officer);

        return $this->document(
            'us_compliance',
            'University Service Compliance Report',
            $from,
            $to,
            $this->filterLabels($period, $program),
            [
                ['Total Assignments', $data['total']],
                ['Completed', $data['completed']],
                ['In Progress', $data['inProgress']],
                ['Pending', $data['pending']],
                ['Non-Compliant', $data['nonCompliant']],
                ['Completion Rate', $this->percent($data['completionRate'])],
                ['Total Time Required', UsAssignment::formatDuration($data['totalHoursRequired'])],
                ['Total Time Rendered', UsAssignment::formatDuration($data['totalHoursRendered'])],
            ],
            [
                $this->section('Assignment Details', [
                    'Student Number', 'Student', 'Program', 'Office', 'Required', 'Rendered', 'Remaining',
                    'Status', 'Supervising Contact', 'Start Date', 'Target Date',
                ], $data['assignments']->map(fn ($assignment) => [
                    $assignment->student?->studentProfile?->student_number ?? 'Not assigned',
                    $assignment->student?->list_name ?? 'Unknown student',
                    $assignment->student?->studentProfile?->program ?? 'Not assigned',
                    $assignment->office_name,
                    $assignment->requiredDuration(),
                    $assignment->renderedDuration(),
                    $assignment->remainingDuration(),
                    $this->label($assignment->status),
                    $assignment->supervisor_display_name,
                    $this->date($assignment->start_date),
                    $this->date($assignment->target_completion_date),
                ])),
            ],
            [
                $this->pieChart('Compliance Breakdown', 'Assignment status distribution within the selected date range', [
                    ['label' => 'Completed', 'value' => (int) $data['completed'], 'color' => '#15803d'],
                    ['label' => 'In Progress', 'value' => (int) $data['inProgress'], 'color' => '#4f46e5'],
                    ['label' => 'Pending', 'value' => (int) $data['pending'], 'color' => '#d97706'],
                    ['label' => 'Non-Compliant', 'value' => (int) $data['nonCompliant'], 'color' => '#b91c1c'],
                ]),
            ]
        );
    }

    private function upccCases(array $filters): array
    {
        [$from, $to, $period, $program] = $this->datedFilters($filters);
        $data = $this->reports->upccCases($from, $to, $period, $program, $this->officer);

        return $this->document(
            'upcc_case_report',
            'UPCC Case Report',
            $from,
            $to,
            $this->filterLabels($period, $program),
            [
                ['Total Referrals', $data['total']],
                ['Concluded', $data['concluded']],
                ['Appeals Filed', $data['appealsFiled']],
                ['Full Hearings', $data['fullHearing']],
                ['Summary Proceedings', $data['summary']],
            ],
            [
                $this->section('UPCC Referral Details', [
                    'Case Number', 'Student Number', 'Student', 'Program', 'Confirmed Offense', 'Proceedings',
                    'Status', 'Referred At', 'Conference', 'Location', 'Attendance', 'Decision Issued',
                    'Appeal Filed', 'Appeal Deadline',
                ], $data['referrals']->map(function ($referral): array {
                    $case = $referral->case;

                    return [
                        $case?->case_number,
                        $case?->student?->studentProfile?->student_number ?? 'Not assigned',
                        $case?->student?->list_name ?? 'Unknown student',
                        $case?->student?->studentProfile?->program ?? 'Not assigned',
                        $case?->confirmedOffense ? "{$case->confirmedOffense->code} - {$case->confirmedOffense->title}" : 'Not recorded',
                        $this->label($referral->proceedings_type),
                        $this->label($referral->status),
                        $this->dateTime($referral->referred_at),
                        $this->dateTime($referral->scheduled_conference_at),
                        $referral->conference_location,
                        $referral->student_attended === null ? 'Not recorded' : ($referral->student_attended ? 'Present' : 'No-show'),
                        $this->dateTime($referral->decision_issued_at),
                        $referral->appeal_filed ? 'Yes' : 'No',
                        $this->date($referral->appeal_window_end),
                    ];
                })),
            ]
        );
    }

    private function registrationHolds(array $filters): array
    {
        $activeOnly = filter_var($filters['active_only'] ?? true, FILTER_VALIDATE_BOOL);
        $program = isset($filters['program']) ? trim((string) $filters['program']) : null;
        $data = $this->reports->registrationHolds($activeOnly, $program ?: null, $this->officer);
        $from = $data['holds']->min('created_at')?->toDateString() ?? today()->toDateString();

        return $this->document(
            'registration_hold_report',
            'Registration Hold Report',
            $from,
            today()->toDateString(),
            array_values(array_filter([
                ['Records Included', $activeOnly ? 'Active holds only' : 'All holds'],
                $program ? ['Program', $program] : null,
            ])),
            [
                ['Total Holds', $data['totalHolds']],
                ['Active Holds', $data['activeHolds']],
                ['Cleared Holds', $data['clearedHolds']],
            ],
            [
                $this->section('Registration Hold Details', [
                    'Student Number', 'Student', 'Program', 'Reason', 'Status', 'Flagged At', 'Lifted At',
                ], $data['holds']->map(fn ($hold) => [
                    $hold->student?->studentProfile?->student_number ?? 'Not assigned',
                    $hold->student?->list_name ?? 'Unknown student',
                    $hold->student?->studentProfile?->program ?? 'Not assigned',
                    $hold->flag_reason,
                    $hold->is_active ? 'Active' : 'Lifted',
                    $this->dateTime($hold->created_at),
                    $this->dateTime($hold->deactivated_at),
                ])),
            ]
        );
    }

    private function escalations(array $filters): array
    {
        [$from, $to, $period, $program] = $this->datedFilters($filters);
        $status = (string) ($filters['status'] ?? 'all');
        $data = $this->reports->escalations($from, $to, $status, $period, $program, $this->officer);

        return $this->document(
            'escalation_report',
            'Escalation / Habitual Offense Report',
            $from,
            $to,
            $this->filterLabels($period, $program, ['Status' => $this->label($status)]),
            [
                ['Total Flagged Cases', $data['total']],
                ['Pending Review', $data['pending']],
                ['MAJOR-19 Applied', $data['applied']],
                ['Retained / Justified', $data['retained']],
            ],
            [
                $this->section('Escalation Details', [
                    'Case Number', 'Student Number', 'Student', 'Program', 'Confirmed Offenses', 'Same-Type Progress',
                    'Different-Type Progress', 'Reason', 'Status', 'Assigned Officer', 'Flagged At', 'Acknowledged By',
                    'Acknowledged At',
                ], $data['records']->map(fn (array $record) => [
                    $record['case_number'],
                    $record['student_number'],
                    $record['student'],
                    $record['program'],
                    $record['offenses'],
                    "{$record['same_type_count']} of {$record['same_type_threshold']}",
                    "{$record['different_type_count']} of {$record['different_type_threshold']}",
                    $record['reason'],
                    $record['status_label'],
                    $record['assigned_officer'],
                    $this->dateTime($record['flagged_at']),
                    $record['acknowledged_by'],
                    $this->dateTime($record['acknowledged_at']),
                ])),
            ]
        );
    }

    private function riskAnalytics(array $filters): array
    {
        $from = (string) $filters['from'];
        $to = (string) $filters['to'];
        $period = $this->academicPeriod($filters);
        $data = $this->reports->riskAnalytics($from, $to, $this->officer);
        $coverage = $data['interventionCoverage'];

        $movementLabels = [
            'improved' => 'Improved', 'stable' => 'Stable', 'worsened' => 'Increased', 'new' => 'First-time score',
        ];
        $outcomeLabels = [
            'improved' => 'Improved after support', 'stable' => 'Stable after support',
            'worsened' => 'Increased after support', 'no_current_score' => 'No current score available',
        ];

        return $this->document(
            'risk_analytics_report',
            'Risk Analytics Report',
            $from,
            $to,
            $this->filterLabels($period),
            [
                ['Students Assessed', $data['totalAssessed']],
                ['Average Score', number_format((float) $data['averageScore'], 2, '.', '')],
                ['High / Critical Students', $coverage['high_risk_total']],
                ['Support Coverage', $this->percent($coverage['coverage_rate'])],
                ['Overdue Follow-ups', $coverage['overdue_follow_ups']],
            ],
            [
                $this->section('Recommended Actions', ['Action', 'Reason'], $data['priorityActions']->map(fn (array $action) => [
                    $action['title'], $action['detail'],
                ])),
                $this->section('Students by Risk Level', ['Risk Level', 'Students', 'Share'], $data['bandCounts']->map(fn ($count, $band) => [
                    $this->label((string) $band), (int) $count,
                    $this->percent($data['totalAssessed'] > 0 ? ((int) $count / $data['totalAssessed']) * 100 : 0),
                ])->values()),
                $this->section('Main Reasons Behind Scores', ['Factor', 'Students Affected', 'Average Impact', 'Total Impact', 'Share'], $data['topFactors']->map(fn (array $factor) => [
                    $factor['factor_name'], $factor['students_triggered'],
                    number_format((float) $factor['average_contribution'], 2, '.', ''),
                    number_format((float) $factor['total_contribution'], 2, '.', ''),
                    $this->percent($factor['share']),
                ])),
                $this->section('Risk Changes During Period', ['Movement', 'Score Records'], $data['movementCounts']->map(fn ($count, $movement) => [
                    $movementLabels[$movement] ?? $this->label((string) $movement), (int) $count,
                ])->values()),
                $this->section('Support Actions', ['Metric', 'Value'], collect([
                    ['High / Critical students', $coverage['high_risk_total']],
                    ['With active support action', $coverage['high_risk_with_active_support']],
                    ['Without active support action', $coverage['high_risk_without_active_support']],
                    ['Coverage rate', $this->percent($coverage['coverage_rate'])],
                    ['Created in period', $coverage['created_in_period']],
                    ['Completed in period', $coverage['completed_in_period']],
                    ['Completion rate', $this->percent($coverage['completion_rate'])],
                    ['Overdue follow-ups', $coverage['overdue_follow_ups']],
                ])),
                $this->section('Follow-up Outcomes', ['Outcome', 'Count'], $data['outcomeSummary']->map(fn ($count, $outcome) => [
                    $outcomeLabels[$outcome] ?? $this->label((string) $outcome), (int) $count,
                ])->values()),
                $this->section('Students Requiring Support', ['Student Number', 'Student', 'Program', 'Risk Level', 'Score', 'Last Scored'], $data['studentsNeedingSupport']->map(fn ($score) => [
                    $score->student?->studentProfile?->student_number ?? 'Not assigned',
                    $score->student?->list_name ?? 'Unknown student',
                    $score->student?->studentProfile?->program ?? 'Not assigned',
                    $this->label($score->risk_band),
                    number_format((float) $score->composite_score, 2, '.', ''),
                    $this->dateTime($score->computed_at),
                ])),
            ]
        );
    }

    private function document(
        string $type,
        string $title,
        string $from,
        string $to,
        array $filters,
        array $summary,
        array $sections,
        array $charts = []
    ): array {
        return compact('type', 'title', 'from', 'to', 'filters', 'summary', 'sections', 'charts') + [
            'period_start' => $from,
            'period_end' => $to,
        ];
    }

    /**
     * @param  array<int, array{label:string, value:int, color:string}>  $segments
     */
    private function pieChart(string $title, string $subtitle, array $segments): array
    {
        return [
            'type' => 'pie',
            'title' => $title,
            'subtitle' => $subtitle,
            'segments' => array_values(array_filter($segments, fn (array $segment) => $segment['value'] > 0)),
        ];
    }

    /**
     * @param  array<string, string>  $series a map of series label => color
     * @param  array<int, array{label:string, values: array<string, int>}>  $rows
     */
    private function barChart(string $title, string $subtitle, array $series, array $rows): array
    {
        return [
            'type' => 'bar',
            'title' => $title,
            'subtitle' => $subtitle,
            'series' => $series,
            'rows' => $rows,
            'max' => max(1, collect($rows)->flatMap(fn (array $row) => array_values($row['values']))->max() ?? 1),
        ];
    }

    private function section(string $title, array $headers, Collection|array $rows): array
    {
        return [
            'title' => $title,
            'headers' => array_values($headers),
            'rows' => collect($rows)->map(fn ($row) => array_values((array) $row))->values()->all(),
        ];
    }

    private function datedFilters(array $filters): array
    {
        $period = $this->academicPeriod($filters);
        $from = $period?->starts_on->toDateString() ?? (string) $filters['from'];
        $to = $period?->ends_on->toDateString() ?? (string) $filters['to'];
        $program = isset($filters['program']) && trim((string) $filters['program']) !== ''
            ? trim((string) $filters['program'])
            : null;

        return [$from, $to, $period, $program];
    }

    private function academicPeriod(array $filters): ?AcademicPeriod
    {
        return isset($filters['academic_period_id'])
            ? AcademicPeriod::findOrFail((int) $filters['academic_period_id'])
            : null;
    }

    private function filterLabels(?AcademicPeriod $period, ?string $program = null, array $extra = []): array
    {
        $filters = [];
        if ($period) {
            $filters[] = ['Academic Period', $period->label];
        }
        if ($program) {
            $filters[] = ['Program', $program];
        }
        foreach ($extra as $label => $value) {
            $filters[] = [$label, $value];
        }

        return $filters;
    }

    private function yearRange(int $year, ?int $month): array
    {
        $start = CarbonImmutable::create($year, $month ?: 1, 1);

        return $month
            ? [$start->startOfMonth()->toDateString(), $start->endOfMonth()->toDateString()]
            : [$start->startOfYear()->toDateString(), $start->endOfYear()->toDateString()];
    }

    private function label(?string $value): string
    {
        return $value ? ucwords(str_replace('_', ' ', $value)) : 'Not recorded';
    }

    private function date(mixed $value): string
    {
        return $value ? CarbonImmutable::parse($value)->toDateString() : '';
    }

    private function dateTime(mixed $value): string
    {
        return $value ? CarbonImmutable::parse($value)->format('Y-m-d H:i') : '';
    }

    private function percent(float|int|string $value): string
    {
        $number = round((float) $value, 1);

        return rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.').'%';
    }
}
