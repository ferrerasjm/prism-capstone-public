<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\AcademicUnit;
use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\RiskIntervention;
use App\Models\Sanction;
use App\Models\StudentProfile;
use App\Models\UsAssignment;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Officer dashboard.
     *
     * The department label is built to read as a sentence in the
     * heading - joined with "and" for two units, an Oxford-comma list
     * for three or more - rather than a raw comma-separated list of
     * academic unit abbreviations.
     */
    public function index(): View
    {
        $officer = auth()->user();
        $officerDepartmentAbbreviations = $officer->assignedAcademicUnits()
            ->pluck('name')
            ->filter()
            ->map(fn (string $name) => AcademicUnit::abbreviatedName($name))
            ->unique()
            ->values();

        // Reads as a sentence in the heading, so the last unit gets an "and"
        // (with the Oxford comma once there are three or more).
        $officerDepartmentLabel = $officerDepartmentAbbreviations
            ->join(', ', $officerDepartmentAbbreviations->count() > 2 ? ', and ' : ' and ')
            ?: 'Your Department';

        $officerDepartmentCount = $officerDepartmentAbbreviations->count();

        $overdueSanctions = Sanction::query()
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count()
            + UsAssignment::query()
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereDate('target_completion_date', '<', today())
                ->count();

        // Support follow-ups the officer owns and has run past. Nothing else in the
        // app surfaces these, so without this card they are only found by opening
        // each student's risk profile one at a time.
        $overdueFollowUps = RiskIntervention::query()
            ->where('assigned_to', $officer->id)
            ->where('status', 'in_progress')
            ->whereDate('follow_up_on', '<', today())
            ->count();

        $attention = [
            'pending_incidents' => IncidentReport::query()
                ->forOfficerDepartment($officer)
                ->where('status', 'pending_review')
                ->count(),
            'overdue_nte' => CaseNotice::query()
                ->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->whereNull('student_acknowledged_at')
                ->whereDate('response_deadline', '<', today())
                ->whereHas('case', fn ($query) => $query
                    ->where('status', 'pending_nte')
                    ->whereDoesntHave('explanation'))
                ->count(),
            'overdue_sanctions' => $overdueSanctions,
            'overdue_follow_ups' => $overdueFollowUps,
        ];

        $programCountRows = DisciplinaryCase::query()
            ->forOfficerDepartment($officer)
            ->selectRaw('student_profiles.program, cases.case_type, COUNT(cases.id) as total')
            ->join('student_profiles', 'cases.student_id', '=', 'student_profiles.user_id')
            ->whereNotIn('cases.status', ['voided'])
            ->where(function ($query): void {
                $query->whereIn('cases.case_type', ['minor', 'major'])
                    ->orWhereNull('cases.case_type');
            })
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
                $unclassifiedCount = (int) $counts->get('', 0);

                return [
                    'program' => $program,
                    'unclassified_count' => $unclassifiedCount,
                    'minor_count' => $minorCount,
                    'major_count' => $majorCount,
                    'total_count' => $unclassifiedCount + $minorCount + $majorCount,
                ];
            })
            ->sortByDesc('total_count')
            ->values();

        $caseStatusTotal = (int) $programViolationCounts->sum('total_count');
        $programChartPeak = $programViolationCounts
            ->flatMap(fn (array $p) => [$p['unclassified_count'], $p['minor_count'], $p['major_count']])
            ->max() ?: 0;
        $programChartScaleMax = $programChartPeak > 0 ? (int) (ceil($programChartPeak / 4) * 4) : 4;
        $programChartTicks = [];
        for ($tick = 4; $tick >= 0; $tick--) {
            $programChartTicks[] = (int) round($programChartScaleMax * $tick / 4);
        }

        $recentCases = DisciplinaryCase::query()
            ->with(['student.studentProfile', 'confirmedOffense'])
            ->forOfficerDepartment($officer)
            ->latest('updated_at')
            ->limit(3)
            ->get();

        $recentIncidents = IncidentReport::query()
            ->with(['student.studentProfile'])
            ->forOfficerDepartment($officer)
            ->latest('created_at')
            ->limit(3)
            ->get();

        return view('officer.dashboard', compact(
            'attention',
            'officerDepartmentLabel',
            'officerDepartmentCount',
            'programViolationCounts',
            'caseStatusTotal',
            'programChartScaleMax',
            'programChartTicks',
            'recentCases',
            'recentIncidents',
        ));
    }
}
