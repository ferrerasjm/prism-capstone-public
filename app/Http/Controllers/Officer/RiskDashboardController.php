<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiskDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $band = $request->input('band');
        $search = $request->input('search');
        $followUp = $request->input('follow_up');
        $officer = $request->user();

        $scores = RiskScore::with([
            'student.studentProfile',
            'student.nextRiskIntervention',
            'student.latestRiskIntervention',
        ])
            ->forOfficerDepartment($officer)
            ->where('composite_score', '>', 0)
            ->when($band, fn ($q) => $q->where('risk_band', $band))
            ->when($followUp === 'overdue', fn ($q) => $q->whereHas(
                'student.riskInterventions',
                fn ($intervention) => $intervention
                    ->where('assigned_to', $officer->id)
                    ->where('status', 'in_progress')
                    ->whereDate('follow_up_on', '<', today())
            ))
            ->when($search, fn ($q) => $q->whereHas('student', fn ($u) => $u->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereHas('studentProfile', fn ($p) => $p->where('student_number', 'like', "%{$search}%")
                )
            )
            )
            ->orderByDesc('composite_score')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $bandCounts = RiskScore::forOfficerDepartment($officer)
            ->where('composite_score', '>', 0)
            ->selectRaw('risk_band, count(*) as total')
            ->groupBy('risk_band')
            ->pluck('total', 'risk_band');

        return view('officer.risk.index', [
            'scores' => $scores,
            'bandCounts' => $bandCounts,
            'filters' => compact('band', 'search', 'followUp'),
            'assignedDepartments' => $officer->scopesReportsByDepartment()
                ? $officer->assignedAcademicUnits()
                : null,
            'activeNav' => 'risk',
        ]);
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json(['results' => []]);
        }

        $results = RiskScore::query()
            ->with('student.studentProfile')
            ->forOfficerDepartment($request->user())
            ->where('composite_score', '>', 0)
            ->whereHas('student', fn ($student) => $student
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                ->orWhereHas('studentProfile', fn ($profile) => $profile
                    ->where('student_number', 'like', "%{$search}%")))
            ->orderByDesc('composite_score')
            ->limit(8)
            ->get()
            ->map(fn (RiskScore $score) => [
                'student_name' => $score->student->list_name,
                'student_number' => $score->student->studentProfile?->student_number,
                'risk_band' => ucfirst($score->risk_band),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function show(Request $request, User $student): View
    {
        abort_unless($student->role === 'student', 404);
        abort_unless(
            StudentProfile::where('user_id', $student->id)->forOfficerDepartment($request->user())->exists(),
            403
        );

        $score = RiskScore::where('student_id', $student->id)
            ->first();

        $snapshots = RiskScoreSnapshot::with('triggeredBy')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        $interventions = RiskIntervention::query()
            ->with(['assignedTo', 'createdBy', 'closedBy'])
            ->where('student_id', $student->id)
            ->orderByRaw("CASE WHEN status = 'in_progress' THEN 0 ELSE 1 END")
            ->orderBy('follow_up_on')
            ->orderByDesc('created_at')
            ->get();

        $student->load(['studentProfile']);

        return view('officer.risk.show', [
            'student' => $student,
            'score' => $score,
            'snapshots' => $snapshots,
            'interventions' => $interventions,
            'actionTypes' => RiskIntervention::ACTION_TYPES,
            'activeNav' => 'risk',
        ]);
    }
}
