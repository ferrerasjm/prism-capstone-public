<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\DisciplinaryCase;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\Sanction;
use App\Models\ScheduledReport;
use App\Models\StudentMonitoringFlag;
use App\Models\StudentProfile;
use App\Models\UpccReferral;
use App\Models\UsAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query layer behind every report: monthly violation summary, case
 * summary, per-student history, University Service compliance, UPCC cases,
 * registration holds, escalations and risk analytics.
 */
class ReportService
{
    public function record(
        string $type,
        string $periodStart,
        string $periodEnd,
        int $generatedBy
    ): void {
        ScheduledReport::create([
            'report_type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generated_by' => $generatedBy,
            'file_path' => "{$type}_{$periodStart}_{$periodEnd}",
            'generated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Constrain a report query to the students in a discipline officer's assigned
     * departments. No-ops when $officer is null (coordinator / institution-wide)
     * or when the user is not a department-scoped discipline officer.
     *
     * @param  string  $relation  dot-path from the queried model to studentProfile
     */
    private function forOfficer(mixed $query, ?User $officer, string $relation = 'student.studentProfile'): mixed
    {
        if ($officer === null) {
            return $query;
        }

        return $query->whereHas($relation, fn ($profile) => $profile->forOfficerDepartment($officer));
    }

    /**
     * Monthly violation summary, one column per month.
     *
     * The month column and date filter both switch on academic period:
     * with one given, months span the period's own start/end dates and
     * filter on incident date; without one, months default to the full
     * calendar year filtered on case creation date. SQLite and MySQL
     * extract the month differently, so the driver is checked once here
     * rather than per row.
     */
    public function monthlyViolation(
        int $year,
        ?int $month = null,
        ?AcademicPeriod $academicPeriod = null,
        ?User $officer = null
    ): array {
        $dateColumn = $academicPeriod
            ? 'incident_reports.date_of_incident'
            : 'cases.created_at';
        $monthExpression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%m', {$dateColumn}) AS INTEGER)"
            : "MONTH({$dateColumn})";

        if ($academicPeriod) {
            $months = collect();
            $cursor = $academicPeriod->starts_on->copy()->startOfMonth();
            $lastMonth = $academicPeriod->ends_on->copy()->startOfMonth();

            while ($cursor->lte($lastMonth)) {
                $months->put($cursor->month, $cursor->format('F Y'));
                $cursor->addMonth();
            }
        } else {
            $monthNumbers = $month ? [$month] : range(1, 12);
            $months = collect($monthNumbers)->mapWithKeys(fn ($number) => [
                $number => Carbon::create($year, $number, 1)->format('F'),
            ]);
        }

        $applyPeriod = function ($query) use ($academicPeriod, $year, $month) {
            if ($academicPeriod) {
                return $query
                    ->join('incident_reports', 'cases.incident_report_id', '=', 'incident_reports.id')
                    ->whereBetween('incident_reports.date_of_incident', [
                        $academicPeriod->starts_on->toDateString(),
                        $academicPeriod->ends_on->toDateString(),
                    ]);
            }

            $query->whereYear('cases.created_at', $year);
            if ($month) {
                $query->whereMonth('cases.created_at', $month);
            }

            return $query;
        };

        // Minor and major month counts are the same aggregate split by case_type,
        // so one grouped query feeds both instead of two near-identical scans.
        $monthTypeQuery = DisciplinaryCase::selectRaw(
            "{$monthExpression} as month,
             cases.case_type as case_type,
             COUNT(*) as total,
             SUM(CASE WHEN cases.status IN ('resolved','dismissed') THEN 1 ELSE 0 END) as resolved_count"
        )
            ->whereIn('cases.case_type', ['minor', 'major'])
            ->whereNotIn('cases.status', ['voided']);
        $applyPeriod($monthTypeQuery);
        $this->forOfficer($monthTypeQuery, $officer);

        $byMonthAndType = $monthTypeQuery
            ->groupByRaw("{$monthExpression}, cases.case_type")
            ->get();

        $minorByMonth = $byMonthAndType->where('case_type', 'minor')->keyBy('month');
        $majorByMonth = $byMonthAndType->where('case_type', 'major')->keyBy('month');

        $topOffensesQuery = DisciplinaryCase::selectRaw(
            'offense_catalog.code,
             offense_catalog.title,
             offense_catalog.offense_type,
             COUNT(case_offenses.id) as occurrence_count'
        )
            ->join('case_offenses', 'cases.id', '=', 'case_offenses.case_id')
            ->join('offense_catalog', 'case_offenses.offense_catalog_id', '=', 'offense_catalog.id')
            ->whereNotIn('cases.status', ['voided'])
            ->where('case_offenses.status', 'confirmed');
        $applyPeriod($topOffensesQuery);
        $this->forOfficer($topOffensesQuery, $officer);

        $topOffenses = $topOffensesQuery
            ->groupBy(
                'offense_catalog.id',
                'offense_catalog.code',
                'offense_catalog.title',
                'offense_catalog.offense_type'
            )
            ->orderByDesc('occurrence_count')
            ->limit(10)
            ->get();

        $escalationsQuery = DisciplinaryCase::where('cases.escalation_flagged', true);
        $applyPeriod($escalationsQuery);
        $this->forOfficer($escalationsQuery, $officer);

        $escalations = $escalationsQuery->count();

        $programs = StudentProfile::query()
            ->when($officer, fn ($query) => $query->forOfficerDepartment($officer))
            ->whereNotNull('program')
            ->where('program', '<>', '')
            ->distinct()
            ->pluck('program')
            ->map(fn (string $program) => StudentProfile::canonicalProgramName($program))
            ->unique()
            ->sort()
            ->values();

        $programCountsQuery = DisciplinaryCase::query()
            ->selectRaw(
                'student_profiles.program,
                 cases.case_type,
                 COUNT(cases.id) as total'
            )
            ->join('student_profiles', 'cases.student_id', '=', 'student_profiles.user_id')
            ->whereNotIn('cases.status', ['voided'])
            ->whereIn('cases.case_type', ['minor', 'major'])
            ->whereNotNull('student_profiles.program')
            ->where('student_profiles.program', '<>', '');
        $applyPeriod($programCountsQuery);
        $this->forOfficer($programCountsQuery, $officer);

        $programCountLookup = $programCountsQuery
            ->groupBy('student_profiles.program', 'cases.case_type')
            ->get()
            ->groupBy(fn ($row) => StudentProfile::canonicalProgramName($row->program))
            ->map(fn (Collection $rows) => $rows->groupBy('case_type')
                ->map(fn (Collection $r) => $r->sum('total')));

        $programViolationCounts = $programs
            ->map(function (string $program) use ($programCountLookup): array {
                $counts = $programCountLookup->get($program, collect());
                $minorCount = (int) $counts->get('minor', 0);
                $majorCount = (int) $counts->get('major', 0);

                return [
                    'program' => $program,
                    'minor_count' => $minorCount,
                    'major_count' => $majorCount,
                    'total_count' => $minorCount + $majorCount,
                ];
            })
            ->sortBy([
                ['total_count', 'asc'],
                ['program', 'asc'],
            ])
            ->values();

        $hasMinorViolations = $programViolationCounts
            ->contains(fn (array $program) => $program['minor_count'] > 0);
        $hasMajorViolations = $programViolationCounts
            ->contains(fn (array $program) => $program['major_count'] > 0);
        // "Lowest" means every program tied at the minimum count, not an arbitrary
        // top-N — otherwise programs with just as few violations get silently
        // excluded once more than N programs tie for last place.
        $minMinorCount = $programViolationCounts->min('minor_count');
        $minMajorCount = $programViolationCounts->min('major_count');
        $lowestMinorPrograms = $hasMinorViolations
            ? $programViolationCounts
                ->filter(fn (array $program) => $program['minor_count'] === $minMinorCount)
                ->sortBy('program')
                ->values()
            : collect();
        $lowestMajorPrograms = $hasMajorViolations
            ? $programViolationCounts
                ->filter(fn (array $program) => $program['major_count'] === $minMajorCount)
                ->sortBy('program')
                ->values()
            : collect();

        return compact(
            'months',
            'minorByMonth',
            'majorByMonth',
            'topOffenses',
            'escalations',
            'programViolationCounts',
            'lowestMinorPrograms',
            'lowestMajorPrograms',
            'hasMinorViolations',
            'hasMajorViolations',
            'academicPeriod',
            'year',
            'month'
        );
    }

    public function caseSummary(
        string $from,
        string $to,
        ?AcademicPeriod $academicPeriod = null,
        ?string $program = null,
        ?User $officer = null
    ): array {
        $base = DisciplinaryCase::with(['confirmedOffense'])
            ->whereNotIn('status', ['voided']);
        $academicPeriod
            ? $base->whereHas('incidentReport', fn ($incident) => $incident
                ->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod)))
            : $base->whereBetween('created_at', [$from, $to.' 23:59:59']);
        $base->when($program, fn ($query, $value) => $query
            ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));
        $this->forOfficer($base, $officer);

        $total = (clone $base)->count();
        $minor = (clone $base)->where('case_type', 'minor')->count();
        $major = (clone $base)->where('case_type', 'major')->count();
        $resolved = (clone $base)->where('status', 'resolved')->count();
        $dismissed = (clone $base)->where('status', 'dismissed')->count();
        $pending = (clone $base)->whereNotIn('status', ['resolved', 'dismissed'])->count();

        $byStatus = DisciplinaryCase::selectRaw('status, COUNT(*) as total')
            ->whereNotIn('status', ['voided']);
        $academicPeriod
            ? $byStatus->whereHas('incidentReport', fn ($incident) => $incident
                ->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod)))
            : $byStatus->whereBetween('created_at', [$from, $to.' 23:59:59']);
        $byStatus->when($program, fn ($query, $value) => $query
            ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));
        $this->forOfficer($byStatus, $officer);
        $byStatus = $byStatus->groupBy('status')->pluck('total', 'status');

        $byOffenseType = DisciplinaryCase::selectRaw(
            'offense_catalog.code,
             offense_catalog.title,
             offense_catalog.offense_type,
             COUNT(case_offenses.id) as total'
        )
            ->join('case_offenses', 'cases.id', '=', 'case_offenses.case_id')
            ->join('offense_catalog', 'case_offenses.offense_catalog_id', '=', 'offense_catalog.id')
            ->whereNotIn('cases.status', ['voided'])
            ->where('case_offenses.status', 'confirmed');
        $academicPeriod
            ? $byOffenseType->whereHas('incidentReport', fn ($incident) => $incident
                ->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod)))
            : $byOffenseType->whereBetween('cases.created_at', [$from, $to.' 23:59:59']);
        $byOffenseType->when($program, fn ($query, $value) => $query
            ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));
        $this->forOfficer($byOffenseType, $officer);
        $byOffenseType = $byOffenseType
            ->groupBy(
                'offense_catalog.id',
                'offense_catalog.code',
                'offense_catalog.title',
                'offense_catalog.offense_type'
            )
            ->orderByDesc('total')
            ->get();

        ['months' => $months, 'minorByMonth' => $minorByMonth, 'majorByMonth' => $majorByMonth]
            = $this->monthlyCaseActivity($from, $to, $academicPeriod, $program, $officer);

        return compact(
            'total',
            'minor',
            'major',
            'resolved',
            'dismissed',
            'pending',
            'byStatus',
            'byOffenseType',
            'months',
            'minorByMonth',
            'majorByMonth',
            'from',
            'to'
        );
    }

    private function periodDateRange(AcademicPeriod $academicPeriod): array
    {
        return [
            $academicPeriod->starts_on->toDateString(),
            $academicPeriod->ends_on->toDateString(),
        ];
    }

    private function monthlyCaseActivity(
        string $from,
        string $to,
        ?AcademicPeriod $academicPeriod,
        ?string $program,
        ?User $officer = null
    ): array {
        $dateColumn = $academicPeriod ? 'incident_reports.date_of_incident' : 'cases.created_at';
        $monthKeyExpression = DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y%m', {$dateColumn}) AS INTEGER)"
            : "(YEAR({$dateColumn}) * 100 + MONTH({$dateColumn}))";

        $applyFilters = function ($query) use ($academicPeriod, $from, $to, $program, $officer) {
            if ($academicPeriod) {
                $query->join('incident_reports', 'cases.incident_report_id', '=', 'incident_reports.id')
                    ->whereBetween('incident_reports.date_of_incident', $this->periodDateRange($academicPeriod));
            } else {
                $query->whereBetween('cases.created_at', [$from, $to.' 23:59:59']);
            }

            $query->when($program, fn ($q, $value) => $q
                ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));

            return $this->forOfficer($query, $officer);
        };

        $minorQuery = DisciplinaryCase::selectRaw(
            "{$monthKeyExpression} as month_key,
             COUNT(*) as total,
             SUM(CASE WHEN cases.status IN ('resolved','dismissed') THEN 1 ELSE 0 END) as resolved_count"
        )
            ->where('cases.case_type', 'minor')
            ->whereNotIn('cases.status', ['voided']);
        $applyFilters($minorQuery);
        $minorByMonth = $minorQuery->groupByRaw($monthKeyExpression)->get()->keyBy('month_key');

        $majorQuery = DisciplinaryCase::selectRaw(
            "{$monthKeyExpression} as month_key,
             COUNT(*) as total,
             SUM(CASE WHEN cases.status IN ('resolved','dismissed') THEN 1 ELSE 0 END) as resolved_count"
        )
            ->where('cases.case_type', 'major')
            ->whereNotIn('cases.status', ['voided']);
        $applyFilters($majorQuery);
        $majorByMonth = $majorQuery->groupByRaw($monthKeyExpression)->get()->keyBy('month_key');

        $months = collect();
        $cursor = Carbon::parse($from)->startOfMonth();
        $lastMonth = Carbon::parse($to)->startOfMonth();
        while ($cursor->lte($lastMonth)) {
            $months->put((int) $cursor->format('Ym'), $cursor->format('F Y'));
            $cursor->addMonth();
        }

        return compact('months', 'minorByMonth', 'majorByMonth');
    }

    public function studentCase(int $studentId, ?User $officer = null): array
    {
        $student = User::with('studentProfile')
            ->where('role', 'student')
            ->findOrFail($studentId);

        if ($officer !== null) {
            abort_unless(
                StudentProfile::query()
                    ->where('user_id', $studentId)
                    ->forOfficerDepartment($officer)
                    ->exists(),
                404,
            );
        }

        $cases = DisciplinaryCase::where('student_id', $studentId)
            ->with([
                'confirmedOffense',
                'confirmedOffenses',
                'resolution',
            ])
            ->whereNotIn('status', ['voided'])
            ->orderByDesc('created_at')
            ->get();

        $sanctions = Sanction::where('student_id', $studentId)
            ->with('resolution.case')
            ->orderByDesc('created_at')
            ->get();

        $usAssignments = UsAssignment::where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->get();

        $riskScore = RiskScore::where('student_id', $studentId)->first();

        $hold = StudentMonitoringFlag::where('student_id', $studentId)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->first();

        return compact(
            'student',
            'cases',
            'sanctions',
            'usAssignments',
            'riskScore',
            'hold'
        );
    }

    public function usCompliance(
        string $from,
        string $to,
        ?AcademicPeriod $academicPeriod = null,
        ?string $program = null,
        ?User $officer = null
    ): array {
        $assignments = UsAssignment::with(['student.studentProfile', 'supervisor'])
            ->when(
                $academicPeriod,
                fn ($query) => $query->whereHas(
                    'caseResolution.case.incidentReport',
                    fn ($incident) => $incident->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod))
                ),
                fn ($query) => $query->whereBetween('created_at', [$from, $to.' 23:59:59'])
            )
            ->when($program, fn ($query, $value) => $query
                ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)))
            ->when($officer, fn ($query, $value) => $query->forOfficerDepartment($value))
            ->orderByRaw("CASE status WHEN 'non_compliant' THEN 1 WHEN 'pending' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
            ->get();

        $total = $assignments->count();
        $completed = $assignments->where('status', 'completed')->count();
        $inProgress = $assignments->where('status', 'in_progress')->count();
        $pending = $assignments->where('status', 'pending')->count();
        $nonCompliant = $assignments->where('status', 'non_compliant')->count();

        $completionRate = $total > 0
            ? round(($completed / $total) * 100, 1)
            : 0;

        $totalHoursRequired = $assignments->sum('total_hours_required');
        $totalHoursRendered = $assignments->sum('hours_rendered');

        return compact(
            'assignments',
            'total',
            'completed',
            'inProgress',
            'pending',
            'nonCompliant',
            'completionRate',
            'totalHoursRequired',
            'totalHoursRendered',
            'from',
            'to'
        );
    }

    public function upccCases(
        string $from,
        string $to,
        ?AcademicPeriod $academicPeriod = null,
        ?string $program = null,
        ?User $officer = null
    ): array {
        $referrals = UpccReferral::with([
            'case.student.studentProfile',
            'case.confirmedOffense',
        ])
            ->when(
                $academicPeriod,
                fn ($query) => $query->whereHas(
                    'case.incidentReport',
                    fn ($incident) => $incident->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod))
                ),
                fn ($query) => $query->whereBetween('referred_at', [$from, $to.' 23:59:59'])
            )
            ->when($program, fn ($query, $value) => $query
                ->whereHas('case.student.studentProfile', fn ($profile) => $profile->where('program', $value)))
            ->when($officer, fn ($query, $value) => $query
                ->whereHas('case.student.studentProfile', fn ($profile) => $profile->forOfficerDepartment($value)))
            ->orderByDesc('referred_at')
            ->get();

        $total = $referrals->count();
        $concluded = $referrals->where('status', 'concluded')->count();
        $appealsFiled = $referrals->where('appeal_filed', true)->count();
        $fullHearing = $referrals->where('proceedings_type', 'full_hearing')->count();
        $summary = $referrals->where('proceedings_type', 'summary')->count();

        return compact(
            'referrals',
            'total',
            'concluded',
            'appealsFiled',
            'fullHearing',
            'summary',
            'from',
            'to'
        );
    }

    public function registrationHolds(bool $activeOnly = true, ?string $program = null, ?User $officer = null): array
    {
        $query = StudentMonitoringFlag::with(['student.studentProfile'])
            ->where('flag_type', 'registration_hold');

        $query->when($program, fn ($builder, $value) => $builder
            ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));
        $this->forOfficer($query, $officer);

        $activeHolds = (clone $query)->where('is_active', true)->count();
        $clearedHolds = (clone $query)->where('is_active', false)->count();
        $totalHolds = $activeHolds + $clearedHolds;

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $holds = $query->orderByDesc('created_at')->get();

        return compact('holds', 'activeOnly', 'activeHolds', 'clearedHolds', 'totalHolds');
    }

    /**
     * Escalation report: cases flagged for habitual-offense escalation.
     *
     * Filters by academic period when one is given - matching on the
     * incident date rather than the case creation date, since a case
     * opened after a term ends still belongs to the term the incident
     * happened in - and falls back to a plain created_at range
     * otherwise. Voided cases are always excluded.
     */
    public function escalations(
        string $from,
        string $to,
        string $status = 'all',
        ?AcademicPeriod $academicPeriod = null,
        ?string $program = null,
        ?User $officer = null
    ): array {
        $query = DisciplinaryCase::with([
            'student.studentProfile',
            'assignedOfficer',
            'escalationAcknowledgedBy',
            'caseOffenses.offense',
            'confirmedOffenses',
        ])
            ->where('escalation_flagged', true)
            ->whereNotIn('status', ['voided']);
        $academicPeriod
            ? $query->whereHas('incidentReport', fn ($incident) => $incident
                ->whereBetween('date_of_incident', $this->periodDateRange($academicPeriod)))
            : $query->whereBetween('created_at', [$from, $to.' 23:59:59']);
        $query->when($program, fn ($builder, $value) => $builder
            ->whereHas('student.studentProfile', fn ($profile) => $profile->where('program', $value)));
        $this->forOfficer($query, $officer);

        if ($status === 'pending') {
            $query->whereNull('escalation_acknowledged_at');
        } elseif ($status === 'applied') {
            $query->whereHas('confirmedOffenses', fn ($offense) => $offense->where('code', 'MAJOR-19'));
        } elseif ($status === 'retained') {
            $query->whereNotNull('escalation_acknowledged_at')
                ->whereDoesntHave('confirmedOffenses', fn ($offense) => $offense->where('code', 'MAJOR-19'));
        }

        $cases = $query
            ->orderByRaw('CASE WHEN escalation_acknowledged_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->get();

        $escalationService = new EscalationService;

        $records = $cases->map(function (DisciplinaryCase $case) use ($escalationService) {
            $minorFindings = $case->caseOffenses
                ->filter(fn ($finding) => $finding->status === 'confirmed' && $finding->offense?->offense_type === 'minor');

            $highestSameTypeCount = (int) ($minorFindings
                ->map(fn ($finding) => ((int) $finding->prior_offense_count) + 1)
                ->max() ?? ($case->prior_offense_count + 1));

            $progress = $escalationService->progress($case);
            $reason = strtolower((string) $case->escalation_flag_reason);
            $sameTypeCount = max($highestSameTypeCount, (int) $progress['current_same_type_count']);
            $differentTypeCount = (int) $progress['different_type_count'];

            if (str_contains($reason, 'third offense')) {
                $sameTypeCount = max($sameTypeCount, 3);
            }

            if (str_contains($reason, 'fourth minor')) {
                $differentTypeCount = max($differentTypeCount, 4);
            }

            $hasMajor19 = $case->confirmedOffenses->contains(fn ($offense) => $offense->code === 'MAJOR-19');
            $statusKey = match (true) {
                is_null($case->escalation_acknowledged_at) => 'pending',
                $hasMajor19 => 'applied',
                default => 'retained',
            };
            $statusLabel = match ($statusKey) {
                'pending' => 'Pending Review',
                'applied' => 'MAJOR-19 Applied',
                default => 'Retained / Justified',
            };

            return [
                'case' => $case,
                'case_number' => $case->case_number,
                'student' => $case->student?->list_name ?? 'Unknown student',
                'student_number' => $case->student?->studentProfile?->student_number ?? '—',
                'program' => $case->student?->studentProfile?->program ?? '—',
                'offenses' => $case->confirmedOffenses
                    ->reject(fn ($offense) => $offense->code === 'MAJOR-19')
                    ->map(fn ($offense) => "{$offense->code} - {$offense->title}")
                    ->values()
                    ->join('; ') ?: '—',
                'same_type_count' => $sameTypeCount,
                'same_type_threshold' => $progress['same_type_threshold'],
                'different_type_count' => $differentTypeCount,
                'different_type_threshold' => $progress['different_type_threshold'],
                'reason' => $case->escalation_flag_reason ?? 'Habitual disregard threshold reached.',
                'status_key' => $statusKey,
                'status_label' => $statusLabel,
                'assigned_officer' => $case->assignedOfficer?->list_name ?? '—',
                'acknowledged_by' => $case->escalationAcknowledgedBy?->list_name ?? '—',
                'flagged_at' => $case->created_at,
                'acknowledged_at' => $case->escalation_acknowledged_at,
            ];
        });

        $total = $records->count();
        $pending = $records->where('status_key', 'pending')->count();
        $applied = $records->where('status_key', 'applied')->count();
        $retained = $records->where('status_key', 'retained')->count();

        return compact(
            'records',
            'total',
            'pending',
            'applied',
            'retained',
            'from',
            'to',
            'status'
        );
    }

    /**
     * Risk analytics report: band distribution, trend and movement
     * breakdowns for scores computed in the given window.
     *
     * Band, trend and movement each have a fixed display order (e.g.
     * low to critical) independent of which values happen to have
     * counts, so the report renders consistently even when a band is
     * empty for the period.
     */
    public function riskAnalytics(string $from, string $to, ?User $officer = null): array
    {
        $fromAt = Carbon::parse($from)->startOfDay();
        $toAt = Carbon::parse($to)->endOfDay();
        $bandOrder = ['low', 'moderate', 'high', 'critical'];
        $trendOrder = ['new', 'decreasing', 'stable', 'increasing'];
        $movementOrder = ['improved', 'stable', 'worsened', 'new'];

        $scoreQuery = $this->forOfficer(
            RiskScore::query()->whereBetween('computed_at', [$fromAt, $toAt]),
            $officer,
        );

        $totalAssessed = (clone $scoreQuery)->count();
        $averageScore = round((float) (clone $scoreQuery)->avg('composite_score'), 2);

        $rawBandCounts = (clone $scoreQuery)
            ->selectRaw('risk_band, COUNT(*) as total')
            ->groupBy('risk_band')
            ->pluck('total', 'risk_band');

        $bandCounts = collect($bandOrder)
            ->mapWithKeys(fn (string $band) => [$band => (int) ($rawBandCounts[$band] ?? 0)]);

        $rawTrendCounts = (clone $scoreQuery)
            ->selectRaw("COALESCE(trend, 'new') as trend_key, COUNT(*) as total")
            ->groupByRaw("COALESCE(trend, 'new')")
            ->pluck('total', 'trend_key');

        $currentTrendCounts = collect($trendOrder)
            ->mapWithKeys(fn (string $trend) => [$trend => (int) ($rawTrendCounts[$trend] ?? 0)]);

        $movementCounts = collect($movementOrder)->mapWithKeys(fn (string $movement) => [$movement => 0]);

        $snapshots = $this->forOfficer(
            RiskScoreSnapshot::query()->whereBetween('created_at', [$fromAt, $toAt]),
            $officer,
        )->get(['risk_band', 'previous_risk_band', 'composite_score', 'previous_composite_score']);

        foreach ($snapshots as $snapshot) {
            $movement = $this->riskMovement(
                $snapshot->previous_risk_band,
                $snapshot->risk_band,
                $snapshot->previous_composite_score !== null ? (float) $snapshot->previous_composite_score : null,
                (float) $snapshot->composite_score
            );

            $movementCounts[$movement] = (int) $movementCounts[$movement] + 1;
        }

        $topFactors = $this->riskFactorContributions($totalAssessed, $fromAt, $toAt, $officer);
        $highRiskStudentIds = (clone $scoreQuery)
            ->whereIn('risk_band', ['high', 'critical'])
            ->pluck('student_id')
            ->filter()
            ->values();

        $highRiskTotal = $highRiskStudentIds->count();
        $highRiskShare = $totalAssessed > 0
            ? round(($highRiskTotal / $totalAssessed) * 100, 1)
            : 0.0;
        $highRiskWithActiveSupport = $highRiskTotal > 0
            ? RiskIntervention::query()
                ->whereIn('student_id', $highRiskStudentIds)
                ->where('status', 'in_progress')
                ->distinct('student_id')
                ->count('student_id')
            : 0;

        $interventionStatusCounts = $this->forOfficer(
            RiskIntervention::query()->whereBetween('created_at', [$fromAt, $toAt]),
            $officer,
        )
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $interventionsCreated = (int) $interventionStatusCounts->sum();
        $interventionsCompleted = $this->forOfficer(
            RiskIntervention::query()
                ->where('status', 'completed')
                ->whereBetween('closed_at', [$fromAt, $toAt]),
            $officer,
        )->count();
        $completionRate = $interventionsCreated > 0
            ? round((min($interventionsCompleted, $interventionsCreated) / $interventionsCreated) * 100, 1)
            : 0.0;

        $overdueFollowUps = $this->forOfficer(
            RiskIntervention::query()
                ->where('status', 'in_progress')
                ->whereDate('follow_up_on', '<', today()),
            $officer,
        )->count();

        $interventionCoverage = [
            'high_risk_total' => $highRiskTotal,
            'high_risk_with_active_support' => $highRiskWithActiveSupport,
            'high_risk_without_active_support' => max(0, $highRiskTotal - $highRiskWithActiveSupport),
            'coverage_rate' => $highRiskTotal > 0 ? round(($highRiskWithActiveSupport / $highRiskTotal) * 100, 1) : 0.0,
            'created_in_period' => $interventionsCreated,
            'completed_in_period' => $interventionsCompleted,
            'completion_rate' => $completionRate,
            'overdue_follow_ups' => $overdueFollowUps,
            'status_counts' => collect(['in_progress', 'completed', 'cancelled'])
                ->mapWithKeys(fn (string $status) => [$status => (int) ($interventionStatusCounts[$status] ?? 0)]),
        ];

        $outcomeSummary = $this->interventionOutcomeSummary($fromAt, $toAt, $officer);
        $studentsNeedingSupport = $this->studentsNeedingRiskSupport($highRiskStudentIds);
        $priorityActions = $this->riskAnalyticsPriorityActions(
            $topFactors,
            $movementCounts,
            $interventionCoverage
        );

        return compact(
            'from',
            'to',
            'totalAssessed',
            'averageScore',
            'bandCounts',
            'currentTrendCounts',
            'movementCounts',
            'snapshots',
            'topFactors',
            'interventionCoverage',
            'outcomeSummary',
            'studentsNeedingSupport',
            'priorityActions'
        );
    }

    private function riskFactorContributions(
        int $totalAssessed,
        Carbon $fromAt,
        Carbon $toAt,
        ?User $officer = null
    ): Collection {
        $factors = [];
        $totalContribution = 0.0;

        $this->forOfficer(
            RiskScore::query()
                ->whereBetween('computed_at', [$fromAt, $toAt])
                ->whereNotNull('contributing_factors'),
            $officer,
        )
            ->get(['contributing_factors'])
            ->each(function (RiskScore $score) use (&$factors, &$totalContribution): void {
                foreach (($score->contributing_factors ?? []) as $code => $factor) {
                    $contribution = round((float) ($factor['contribution'] ?? 0), 2);
                    $triggered = (bool) ($factor['triggered'] ?? false)
                        || (float) ($factor['raw_value'] ?? 0) > 0
                        || $contribution > 0;

                    $factors[$code] ??= [
                        'factor_code' => $code,
                        'factor_name' => $factor['factor_name'] ?? config("risk_scoring.factors.{$code}.name", $code),
                        'students_triggered' => 0,
                        'total_contribution' => 0.0,
                    ];

                    if ($triggered) {
                        $factors[$code]['students_triggered']++;
                    }

                    $factors[$code]['total_contribution'] += $contribution;
                    $totalContribution += $contribution;
                }
            });

        return collect($factors)
            ->map(function (array $factor) use ($totalAssessed, $totalContribution): array {
                $factor['total_contribution'] = round((float) $factor['total_contribution'], 2);
                $factor['average_contribution'] = $totalAssessed > 0
                    ? round($factor['total_contribution'] / $totalAssessed, 2)
                    : 0.0;
                $factor['share'] = $totalContribution > 0
                    ? round(($factor['total_contribution'] / $totalContribution) * 100, 1)
                    : 0.0;

                return $factor;
            })
            ->sortByDesc('total_contribution')
            ->values()
            ->take(10);
    }

    private function interventionOutcomeSummary(Carbon $fromAt, Carbon $toAt, ?User $officer = null): Collection
    {
        $outcomes = collect([
            'improved' => 0,
            'stable' => 0,
            'worsened' => 0,
            'no_current_score' => 0,
        ]);

        $this->forOfficer(
            RiskIntervention::query()
                ->with('student.riskScore')
                ->where('status', 'completed')
                ->whereBetween('closed_at', [$fromAt, $toAt]),
            $officer,
        )
            ->get()
            ->each(function (RiskIntervention $intervention) use ($outcomes): void {
                $currentScore = $intervention->student?->riskScore;

                if (! $currentScore) {
                    $outcomes['no_current_score'] = (int) $outcomes['no_current_score'] + 1;

                    return;
                }

                $delta = (float) $currentScore->composite_score - (float) $intervention->risk_score;
                $key = match (true) {
                    $delta <= -2.0 => 'improved',
                    $delta >= 2.0 => 'worsened',
                    default => 'stable',
                };

                $outcomes[$key] = (int) $outcomes[$key] + 1;
            });

        return $outcomes;
    }

    private function studentsNeedingRiskSupport(Collection $highRiskStudentIds): Collection
    {
        $studentsWithActiveSupport = RiskIntervention::query()
            ->whereIn('student_id', $highRiskStudentIds)
            ->where('status', 'in_progress')
            ->pluck('student_id')
            ->filter()
            ->values();

        return RiskScore::query()
            ->with('student.studentProfile')
            ->whereIn('student_id', $highRiskStudentIds)
            ->when($studentsWithActiveSupport->isNotEmpty(), fn ($query) => $query->whereNotIn('student_id', $studentsWithActiveSupport))
            ->orderByDesc('composite_score')
            ->limit(10)
            ->get();
    }

    private function riskAnalyticsPriorityActions(
        Collection $topFactors,
        Collection $movementCounts,
        array $interventionCoverage
    ): Collection {
        $actions = collect();
        $topFactor = $topFactors->first();
        $worsened = (int) ($movementCounts['worsened'] ?? 0);

        if ($interventionCoverage['high_risk_without_active_support'] > 0) {
            $actions->push([
                'title' => 'Create support actions for students who need attention.',
                'detail' => "{$interventionCoverage['high_risk_without_active_support']} High/Critical student(s) still need a support plan or staff review.",
                'tone' => 'red',
            ]);
        }

        if ($interventionCoverage['overdue_follow_ups'] > 0) {
            $actions->push([
                'title' => 'Check late support follow-ups.',
                'detail' => "{$interventionCoverage['overdue_follow_ups']} support follow-up(s) are past their review date.",
                'tone' => 'orange',
            ]);
        }

        if ($worsened > 0) {
            $actions->push([
                'title' => 'Review students whose risk increased.',
                'detail' => "{$worsened} score record(s) increased during the selected period.",
                'tone' => 'orange',
            ]);
        }

        if ($topFactor) {
            $actions->push([
                'title' => "Look into the main risk reason: {$topFactor['factor_name']}.",
                'detail' => 'Use case review, support meetings, monitoring plans, or referral coordination depending on the student context.',
                'tone' => 'violet',
            ]);
        }

        if ($actions->isEmpty()) {
            $actions->push([
                'title' => 'Continue regular monitoring.',
                'detail' => 'No urgent risk action was detected for the selected period.',
                'tone' => 'green',
            ]);
        }

        return $actions
            ->take(4)
            ->values();
    }

    private function riskMovement(?string $previousBand, string $currentBand, ?float $previousScore, float $currentScore): string
    {
        if (! $previousBand) {
            return 'new';
        }

        $rank = [
            'low' => 1,
            'moderate' => 2,
            'high' => 3,
            'critical' => 4,
        ];

        $previousRank = $rank[$previousBand] ?? 0;
        $currentRank = $rank[$currentBand] ?? 0;

        if ($currentRank < $previousRank) {
            return 'improved';
        }

        if ($currentRank > $previousRank) {
            return 'worsened';
        }

        if ($previousScore !== null) {
            $delta = $currentScore - $previousScore;

            if ($delta <= -2.0) {
                return 'improved';
            }

            if ($delta >= 2.0) {
                return 'worsened';
            }
        }

        return 'stable';
    }
}
