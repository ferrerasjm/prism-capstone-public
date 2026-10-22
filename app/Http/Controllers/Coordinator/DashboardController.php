<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Models\StudentProfile;
use App\Models\UpccReferral;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Coordinator dashboard.
     *
     * "Awaiting schedule" and "pending decisions" are read from the
     * UPCC referral's own status and timestamps rather than re-derived
     * from the case, since a case's status alone does not say whether
     * its referral has been scheduled or decided.
     */
    public function index(): View
    {
        $activeCases = DisciplinaryCase::query()
            ->whereIn('status', ['pending_upcc', 'pending_resolution'])
            ->forwardedToUpcc();

        $stats = [
            'active_cases' => (clone $activeCases)->count(),
            'awaiting_schedule' => (clone $activeCases)
                ->where('status', 'pending_upcc')
                ->whereDoesntHave('upccReferral')
                ->count(),
            'upcoming_hearings' => UpccReferral::query()
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_conference_at', [now(), now()->copy()->addDays(7)->endOfDay()])
                ->count(),
            'pending_decisions' => UpccReferral::query()
                ->whereIn('status', ['in_progress', 'concluded', 'reconsideration_filed'])
                ->whereNull('decision_final_at')
                ->count(),
            'void_approvals' => DisciplinaryCase::query()
                ->where('void_requires_coordinator_approval', true)
                ->where('status', '!=', 'voided')
                ->count(),
            'high_risk_cases' => (clone $activeCases)
                ->whereHas('student.riskScore', fn ($query) => $query
                    ->whereIn('risk_band', ['high', 'critical']))
                ->count(),
        ];

        $programCountRows = DisciplinaryCase::query()
            ->selectRaw('student_profiles.program, cases.case_type, COUNT(cases.id) as total')
            ->join('student_profiles', 'cases.student_id', '=', 'student_profiles.user_id')
            ->whereNotIn('cases.status', ['voided'])
            ->whereIn('cases.case_type', ['minor', 'major'])
            ->whereNotNull('student_profiles.program')
            ->whereRaw("TRIM(student_profiles.program) <> ''")
            ->groupBy('student_profiles.program', 'cases.case_type')
            ->get();

        $programCountLookup = $programCountRows
            ->groupBy(fn ($row) => StudentProfile::canonicalProgramName($row->program))
            ->map(fn (Collection $rows) => $rows->groupBy('case_type')
                ->map(fn (Collection $r) => $r->sum('total')));

        $programViolationCounts = $programCountLookup
            ->map(function (Collection $counts, string $program): array {
                $minorCount = (int) $counts->get('minor', 0);
                $majorCount = (int) $counts->get('major', 0);

                return [
                    'program' => $program,
                    'minor_count' => $minorCount,
                    'major_count' => $majorCount,
                    'total_count' => $minorCount + $majorCount,
                ];
            })
            ->sortByDesc('total_count')
            ->values();

        $programChartTotal = (int) $programViolationCounts->sum('total_count');
        $programChartPeak = $programViolationCounts
            ->flatMap(fn (array $p) => [$p['minor_count'], $p['major_count']])
            ->max() ?: 0;
        $programChartScaleMax = $programChartPeak > 0 ? (int) (ceil($programChartPeak / 4) * 4) : 4;
        $programChartTicks = [];
        for ($tick = 4; $tick >= 0; $tick--) {
            $programChartTicks[] = (int) round($programChartScaleMax * $tick / 4);
        }

        $upcomingHearings = UpccReferral::query()
            ->with(['case.student.studentProfile', 'case.confirmedOffense'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_conference_at')
            ->where('scheduled_conference_at', '>=', now())
            ->orderBy('scheduled_conference_at')
            ->limit(4)
            ->get();

        $recentCases = DisciplinaryCase::query()
            ->with(['student.studentProfile', 'student.riskScore', 'confirmedOffense', 'upccReferral'])
            ->whereIn('status', ['pending_upcc', 'pending_resolution'])
            ->forwardedToUpcc()
            ->latest('forwarded_at')
            ->limit(5)
            ->get();

        return view('coordinator.dashboard', [
            'coordActiveNav' => 'dashboard',
            'stats' => $stats,
            'programViolationCounts' => $programViolationCounts,
            'programChartTotal' => $programChartTotal,
            'programChartScaleMax' => $programChartScaleMax,
            'programChartTicks' => $programChartTicks,
            'upcomingHearings' => $upcomingHearings,
            'recentCases' => $recentCases,
        ]);
    }
}
