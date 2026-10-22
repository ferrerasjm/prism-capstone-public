<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Models\RiskIntervention;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiskDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'band', 'stage']);

        $bandCounts = DisciplinaryCase::query()
            ->join('risk_scores', 'risk_scores.student_id', '=', 'cases.student_id')
            ->whereIn('cases.status', ['pending_upcc', 'pending_resolution'])
            ->selectRaw('risk_scores.risk_band as risk_band, count(*) as total')
            ->groupBy('risk_scores.risk_band')
            ->pluck('total', 'risk_band');

        $cases = DisciplinaryCase::query()
            ->with([
                'student.studentProfile',
                'student.riskScore',
                'upccReferral',
            ])
            ->leftJoin('risk_scores', 'risk_scores.student_id', '=', 'cases.student_id')
            ->select('cases.*')
            ->whereIn('cases.status', ['pending_upcc', 'pending_resolution'])
            ->when($filters['search'] ?? null, fn (Builder $q, string $search) => $this->applySearchFilter($q, $search))
            ->when($filters['band'] ?? null, fn (Builder $q, string $band) => $band === 'unassessed'
                    ? $q->whereNull('risk_scores.id')
                    : $q->where('risk_scores.risk_band', $band))
            ->when($filters['stage'] ?? null, fn (Builder $q, string $stage) => $this->applyStageFilter($q, $stage))
            ->orderByRaw("
                CASE risk_scores.risk_band
                    WHEN 'critical' THEN 4
                    WHEN 'high' THEN 3
                    WHEN 'moderate' THEN 2
                    WHEN 'low' THEN 1
                    ELSE 0
                END DESC
            ")
            ->orderByDesc('risk_scores.composite_score')
            ->orderByDesc('cases.forwarded_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('coordinator.risk.index', [
            'cases' => $cases,
            'filters' => $filters,
            'bandCounts' => $bandCounts,
            'coordActiveNav' => 'risk',
        ]);
    }

    /**
     * Read-only risk profile for a student who is the subject of a case currently
     * under UPCC proceedings. Mirrors the officer's risk detail page, minus the
     * intervention controls — coordinators oversee, they do not run interventions.
     */
    public function show(User $student): View
    {
        abort_unless($student->role === 'student', 404);
        abort_unless(
            DisciplinaryCase::where('student_id', $student->id)
                ->whereIn('status', ['pending_upcc', 'pending_resolution'])
                ->exists(),
            403
        );

        $score = RiskScore::where('student_id', $student->id)->first();

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

        return view('coordinator.risk.show', [
            'student' => $student,
            'score' => $score,
            'snapshots' => $snapshots,
            'interventions' => $interventions,
            'coordActiveNav' => 'risk',
        ]);
    }

    private function applySearchFilter(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search): void {
            $q->where('cases.case_number', 'like', "%{$search}%")
                ->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('studentProfile', fn (Builder $profileQuery) => $profileQuery->where('student_number', 'like', "%{$search}%")
                    )
                );
        });
    }

    private function applyStageFilter(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'awaiting_schedule' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereDoesntHave('upccReferral'),
            'scheduled' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereHas('upccReferral', fn (Builder $q) => $q->where('status', 'scheduled')),
            'in_progress' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereHas('upccReferral', fn (Builder $q) => $q->where('status', 'in_progress')),
            'awaiting_distribution' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereHas('upccReferral', fn (Builder $q) => $q->where('status', 'concluded')
                    ->whereNull('decision_distributed_at')),
            'appeal_window' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereHas('upccReferral', fn (Builder $q) => $q->whereNotNull('decision_distributed_at')
                    ->whereNull('decision_final_at')
                    ->where('appeal_filed', false)),
            'appeal_filed' => $query
                ->where('cases.status', 'pending_upcc')
                ->whereHas('upccReferral', fn (Builder $q) => $q->where('status', 'reconsideration_filed')),
            'pending_resolution' => $query
                ->where('cases.status', 'pending_resolution'),
            default => $query,
        };
    }
}
