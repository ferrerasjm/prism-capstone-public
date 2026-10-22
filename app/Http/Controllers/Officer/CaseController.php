<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\CaseNotice;
use App\Models\CaseOffense;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Services\AdvisoryOffenseClassificationService;
use App\Services\CaseService;
use App\Services\EscalationService;
use App\Services\NoticeDocumentService;
use App\Services\OffenseRecommendationAuditService;
use App\Services\RiskScoringService;
use App\Services\SanctionRecommendationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly AdvisoryOffenseClassificationService $classificationService,
        private readonly OffenseRecommendationAuditService $recommendationAuditService,
        private readonly EscalationService $escalationService,
        private readonly SanctionRecommendationService $sanctionService,
        private readonly NoticeDocumentService $notices,
    ) {
        $this->middleware(function (Request $request, $next) {
            $case = $request->route('case');

            if ($case instanceof DisciplinaryCase) {
                abort_unless($case->isVisibleToOfficer($request->user()), 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $officer = $request->user()->loadMissing('academicUnit');
        $filters = $request->only(['search', 'case_type', 'status', 'academic_period_id']);

        $cases = DisciplinaryCase::with([
            'student.studentProfile',
            'confirmedOffense',
            'notices' => fn ($query) => $query
                ->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->latest('issued_at'),
        ])
            ->forOfficerDepartment($officer)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(fn ($searchQuery) => $searchQuery->where('case_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                    )
                );
            })
            ->when($filters['case_type'] ?? null, function ($q, $value) {
                return $value === 'unclassified'
                    ? $q->whereNull('case_type')
                    : $q->where('case_type', $value);
            })
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['academic_period_id'] ?? null, fn ($q, $periodId) => $q
                ->whereHas('incidentReport', fn ($incident) => $incident
                    ->where('academic_period_id', $periodId)))
            ->orderByDesc('updated_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $raw = DisciplinaryCase::query()
            ->forOfficerDepartment($officer)
            ->when($filters['academic_period_id'] ?? null, fn ($statsQuery, $periodId) => $statsQuery
                ->whereHas('incidentReport', fn ($incident) => $incident
                    ->where('academic_period_id', $periodId)))
            ->selectRaw("
                SUM(status = 'open') as open,
                SUM(status = 'pending_nte') as pending_nte,
                SUM(status = 'awaiting_explanation') as awaiting_explanation,
                SUM(status = 'under_review') as under_review,
                SUM(status = 'resolved') as resolved,
                SUM(escalation_flagged = 1 AND escalation_acknowledged_at IS NULL) as escalation_alerts
            ")
            ->first();

        $stats = [
            'open' => (int) $raw->open,
            'pending_nte' => (int) $raw->pending_nte,
            'awaiting_explanation' => (int) $raw->awaiting_explanation,
            'under_review' => (int) $raw->under_review,
            'resolved' => (int) $raw->resolved,
            'escalation_alerts' => (int) $raw->escalation_alerts,
            'overdue_nte' => CaseNotice::query()
                ->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->whereNull('student_acknowledged_at')
                ->whereDate('response_deadline', '<', today())
                ->whereHas('case', fn ($query) => $query
                    ->where('status', 'pending_nte')
                    ->whereDoesntHave('explanation')
                    ->when($filters['academic_period_id'] ?? null, fn ($caseQuery, $periodId) => $caseQuery
                        ->whereHas('incidentReport', fn ($incident) => $incident
                            ->where('academic_period_id', $periodId))))
                ->count(),
        ];

        // Ajax requests (see public/js/live-ux.js [data-ajax-table]) get only the results fragment, not the full page.
        if ($request->ajax()) {
            return view('officer.cases._results', [
                'stats' => $stats,
                'cases' => $cases,
            ]);
        }

        return view('officer.cases.index', [
            'cases' => $cases,
            'stats' => $stats,
            'filters' => $filters,
            'assignedDepartments' => $officer->assignedAcademicUnits(),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('starts_on')->get(),
            'activeNav' => 'cases',
        ]);
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json(['results' => []]);
        }

        $results = DisciplinaryCase::query()
            ->with('student.studentProfile')
            ->forOfficerDepartment($request->user())
            ->where(function ($query) use ($search): void {
                $query->where('case_number', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($student) use ($search): void {
                        $nameParts = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

                        $student->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere(function ($fullName) use ($nameParts): void {
                                foreach ($nameParts as $namePart) {
                                    $fullName->where(function ($nameColumn) use ($namePart): void {
                                        $nameColumn->where('first_name', 'like', "%{$namePart}%")
                                            ->orWhere('last_name', 'like', "%{$namePart}%");
                                    });
                                }
                            })
                            ->orWhereHas('studentProfile', fn ($profile) => $profile
                                ->where('student_number', 'like', "%{$search}%"));
                    });
            })
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (DisciplinaryCase $case) => [
                'case_number' => $case->case_number,
                'student_name' => $case->student->list_name,
                'student_number' => $case->student->studentProfile?->student_number,
                'status' => ucwords(str_replace('_', ' ', $case->status)),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Case detail page.
     *
     * Eager-loads the full case graph in one pass because this view
     * renders nearly every relation a case has. Offense suggestions are
     * only requested while the case is still open and unconfirmed (or
     * when explicitly adding another offense), so viewing a settled
     * case never calls the classifier.
     */
    public function show(DisciplinaryCase $case): View
    {
        $case->load([
            'student.studentProfile',
            'incidentReport',
            'confirmedOffense',
            'caseOffenses.offense',
            'suggestedOffense',
            'notices' => fn ($q) => $q->orderBy('created_at'),
            'explanation',
            'upccReferral',
            'resolution',
            'escalationAcknowledgedBy',
            'assignedOfficer',
            'resolvedBy',
            'voidedBy',
            'voidCoordinatorApprovedBy',
        ]);

        $suggestions = collect();
        $classification = null;
        if ($case->status === 'open' && (! $case->confirmed_offense_id || request()->boolean('add_offense'))) {
            $description = $case->incidentReport?->description ?? '';
            if (trim($description) !== '') {
                $existingIds = $case->caseOffenses
                    ->where('status', 'confirmed')
                    ->pluck('offense_catalog_id');
                $excludedIds = $existingIds->map(fn ($id): int => (int) $id)->all();
                $classification = $this->classificationService->classify(
                    $description,
                    $excludedIds,
                    $case->incidentReport?->violation_type,
                );
                $suggestions = $classification['suggestions'];
                $this->recommendationAuditService->record(
                    $case,
                    $description,
                    $excludedIds,
                    $classification,
                    auth()->id(),
                );
            }
        }

        $offenseSummary = $this->buildOffenseSummary($case);
        $livePriorOffenseCount = $case->confirmed_offense_id
            ? $offenseSummary['same_offense_prior_count']
            : null;
        $livePriorOffenseCounts = $case->caseOffenses
            ->where('status', 'confirmed')
            ->mapWithKeys(fn (CaseOffense $charge) => [
                $charge->id => $this->priorConfirmedOccurrences(
                    $case,
                    (int) $charge->offense_catalog_id,
                    (int) $charge->id
                ),
            ]);

        // Looked up whenever there is a confirmed offense — not only before the sanction
        // is set — so reopening the step to review an already-confirmed category still
        // shows the matrix recommendation instead of claiming there is no matrix row.
        $sanctionRecommendation = null;
        if ($case->confirmed_offense_id) {
            $sanctionRecommendation = $this->sanctionService->recommend(
                $case->confirmed_offense_id,
                $livePriorOffenseCount ?? $case->prior_offense_count
            );
        }
        $liveSuggestedSanctionCategory = $this->currentRecommendedCategory($case);
        if ($liveSuggestedSanctionCategory === null) {
            $liveSuggestedSanctionCategory = $case->confirmed_offense_id
                ? ($sanctionRecommendation['sanction_category'] ?? null)
                : $case->suggested_sanction_category;
        }
        $liveSuggestedSanctionCategory = $liveSuggestedSanctionCategory ?: null;

        return view('officer.cases.show', [
            'case' => $case,
            'suggestions' => $suggestions,
            'classification' => $classification,
            'sanctionRecommendation' => $sanctionRecommendation,
            'liveSuggestedSanctionCategory' => $liveSuggestedSanctionCategory,
            'livePriorOffenseCount' => $livePriorOffenseCount,
            'livePriorOffenseCounts' => $livePriorOffenseCounts,
            'offenseSummary' => $offenseSummary,
            'activeNav' => 'cases',
        ]);
    }

    public function offenseSummary(DisciplinaryCase $case): JsonResponse
    {
        $this->authorizeCase($case);

        return response()->json($this->buildOffenseSummary($case));
    }

    /**
     * Polling endpoint for the case-show page to detect changes without a full refresh.
     */
    public function status(DisciplinaryCase $case): JsonResponse
    {
        $this->authorizeCase($case);

        $case->refresh();
        $case->load(['resolution', 'upccReferral']);

        return response()->json([
            'status' => $case->status,
            'updated_at' => $case->updated_at?->toISOString(),
            'resolution_updated_at' => $case->resolution?->updated_at?->toISOString(),
            'upcc_referral_updated_at' => $case->upccReferral?->updated_at?->toISOString(),
        ]);
    }

    /**
     * @return array{
     *     resolved_minor_count: int,
     *     active_minor_charge_count: int,
     *     resolved_major_count: int,
     *     same_offense_prior_count: int,
     *     habitual_same_type_count: int,
     *     habitual_different_type_count: int,
     *     habitual_same_type_threshold: int,
     *     habitual_different_type_threshold: int,
     *     habitual_cycle_reset_at: string|null,
     *     habitual_threshold_met: bool,
     *     habitual_escalation_reason: string|null,
     *     refreshed_at: string
     * }
     */
    private function buildOffenseSummary(DisciplinaryCase $case): array
    {
        $counts = CaseOffense::query()
            ->join('offense_catalog as offenses', 'offenses.id', '=', 'case_offenses.offense_catalog_id')
            ->join('cases', 'cases.id', '=', 'case_offenses.case_id')
            ->where('case_offenses.status', 'confirmed')
            ->where('cases.student_id', $case->student_id)
            ->selectRaw("COALESCE(SUM(CASE WHEN offenses.offense_type = 'minor' AND cases.status = 'resolved' THEN 1 ELSE 0 END), 0) AS resolved_minor_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN offenses.offense_type = 'minor' AND cases.status IN ('open', 'pending_nte', 'awaiting_explanation', 'under_review', 'pending_upcc', 'pending_resolution') THEN 1 ELSE 0 END), 0) AS active_minor_charge_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN offenses.offense_type = 'major' AND cases.status = 'resolved' THEN 1 ELSE 0 END), 0) AS resolved_major_count")
            ->first();

        $currentConfirmedFinding = $case->confirmed_offense_id
            ? $case->caseOffenses()
                ->where('offense_catalog_id', $case->confirmed_offense_id)
                ->where('status', 'confirmed')
                ->orderBy('id')
                ->first(['id'])
            : null;
        $sameOffensePriorCount = $currentConfirmedFinding
            ? $this->priorConfirmedOccurrences($case, (int) $case->confirmed_offense_id, (int) $currentConfirmedFinding->id)
            : 0;
        $escalationProgress = $this->escalationService->progress($case);
        $escalationEvaluation = $this->escalationService->check($case);

        return [
            'resolved_minor_count' => (int) $counts->resolved_minor_count,
            'active_minor_charge_count' => (int) $counts->active_minor_charge_count,
            'resolved_major_count' => (int) $counts->resolved_major_count,
            'same_offense_prior_count' => $sameOffensePriorCount,
            'habitual_same_type_count' => $escalationProgress['current_same_type_count'],
            'habitual_different_type_count' => $escalationProgress['different_type_count'],
            'habitual_same_type_threshold' => $escalationProgress['same_type_threshold'],
            'habitual_different_type_threshold' => $escalationProgress['different_type_threshold'],
            'habitual_cycle_reset_at' => $escalationProgress['last_escalation_at'],
            'habitual_threshold_met' => $escalationEvaluation['escalation_flagged'],
            'habitual_escalation_reason' => $escalationEvaluation['escalation_flag_reason'],
            'refreshed_at' => now()->toIso8601String(),
        ];
    }

    private function priorConfirmedOccurrences(DisciplinaryCase $case, int $offenseId, int $beforeFindingId): int
    {
        return CaseOffense::query()
            ->where('offense_catalog_id', $offenseId)
            ->where('status', 'confirmed')
            ->where('id', '<', $beforeFindingId)
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $case->student_id)
                ->where('id', '!=', $case->id)
                ->whereNotIn('status', ['dismissed', 'voided']))
            ->count();
    }

    private function currentRecommendedCategory(DisciplinaryCase $case): ?int
    {
        $highest = 0;
        $charges = $case->caseOffenses()->where('status', 'confirmed')->get(['id', 'offense_catalog_id']);
        if ($charges->isEmpty()) {
            return null;
        }

        foreach ($charges as $charge) {
            $priorCount = $this->priorConfirmedOccurrences($case, (int) $charge->offense_catalog_id, (int) $charge->id);
            $category = $this->sanctionService->recommend((int) $charge->offense_catalog_id, $priorCount)['sanction_category'];
            if ($category !== null) {
                $highest = max($highest, $category);
            }
        }

        return $highest;
    }

    private function authorizeCase(DisciplinaryCase $case): void
    {
        if ($case->assigned_to !== auth()->id()) {
            abort(403, 'You are not assigned to this case.');
        }
    }

    /**
     * Once a case is forwarded to the Discipline Coordinator for UPCC proceedings,
     * the officer keeps read access but can no longer mutate it — only the
     * coordinator progresses it from there. Void requests are the sole exception,
     * since that's the coordinator-approved correction path, not a progress update.
     */
    private function assertNotForwardedToCoordinator(DisciplinaryCase $case): void
    {
        if ($case->forwarded_to_coordinator) {
            abort(403, 'This case was forwarded to the UPCC process. Only the Discipline Coordinator can update it from here.');
        }
    }

    private function assertEscalationClear(DisciplinaryCase $case): void
    {
        if ($case->escalation_flagged && is_null($case->escalation_acknowledged_at)) {
            throw ValidationException::withMessages([
                'escalation' => 'An escalation alert is active. Apply MAJOR-19 or dismiss the alert with a written justification before proceeding.',
            ]);
        }
    }

    /**
     * Confirms one or more offense charges against a case.
     *
     * Accepts either a single offense_id or an offense_ids array so the
     * single-select and multi-select forms can share one endpoint. An
     * override_reason of at least 20 characters is required when the
     * officer charges outside the system's suggestions, which is what
     * keeps an advisory classification from quietly becoming the
     * decision.
     */
    public function confirmOffense(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'offense_id' => ['nullable', 'integer', Rule::exists('offense_catalog', 'id')->where('is_active', true)],
            'offense_ids' => ['nullable', 'array'],
            'offense_ids.*' => ['integer', Rule::exists('offense_catalog', 'id')->where('is_active', true)],
            'override_reason' => ['nullable', 'string', 'min:20', 'max:1000'],
        ], [
            'override_reason.min' => 'The override reason must be at least 20 characters.',
        ]);

        $offenseIds = array_values(array_unique(array_map(
            'intval',
            $validated['offense_ids'] ?? (isset($validated['offense_id']) ? [$validated['offense_id']] : [])
        )));

        if (empty($offenseIds)) {
            return back()->withErrors([
                'offense_id' => 'Select at least one offense to confirm.',
            ]);
        }

        $description = $case->incidentReport?->description ?? '';
        $excludedIds = $case->caseOffenses
            ->where('status', 'confirmed')
            ->pluck('offense_catalog_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $classification = $this->classificationService->classify(
            $description,
            $excludedIds,
            $case->incidentReport?->violation_type,
        );
        $recommendationAudit = $this->recommendationAuditService->record(
            $case,
            $description,
            $excludedIds,
            $classification,
            auth()->id(),
        );
        $topIds = $classification['suggestions']->pluck('offense.id')
            ->push($case->suggested_offense_id)
            ->filter()
            ->unique();
        $outsideTop = collect($offenseIds)->reject(fn ($id) => $topIds->contains($id));

        if ($outsideTop->isNotEmpty() && empty($validated['override_reason'])) {
            return back()->withErrors([
                'override_reason' => 'An override reason is required when selecting an offense outside the top suggestions.',
            ]);
        }

        try {
            $this->caseService->confirmOffenses(
                $case,
                $offenseIds,
                (int) auth()->id(),
                $validated['override_reason'] ?? null
            );
            $this->recommendationAuditService->recordDecision(
                $recommendationAudit,
                $offenseIds[0],
                (int) auth()->id(),
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $fresh = $case->fresh();
        $escalationRaised = false;
        if ($fresh->confirmed_offense_id) {
            $escalation = $this->escalationService->check($fresh);
            if ($escalation['escalation_flagged']) {
                $escalationRaised = ! $fresh->escalation_flagged;
                $fresh = $this->applyEscalationFlag($fresh, $escalation);
            }
        }

        if ($escalationRaised) {
            app(RiskScoringService::class)->computeForStudent(
                $fresh->student_id,
                'case_escalation_flagged',
                (int) auth()->id()
            );
        }

        $chargeCount = $fresh->caseOffenses()->where('status', 'confirmed')->count();
        $addedCount = count($offenseIds);
        $message = match (true) {
            $addedCount > 1 => $addedCount.' offenses confirmed.',
            $chargeCount > 1 => 'Additional offense added to the case.',
            default => 'Offense confirmed.',
        };
        if ($fresh->escalation_flagged && ! $fresh->escalation_acknowledged_at) {
            $message .= ' Escalation alert generated — review before proceeding.';
        } else {
            $message .= ' Add another offense if applicable, or continue with the case workflow below.';
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')->with('success', $message);
    }

    public function removeOffense(Request $request, DisciplinaryCase $case, CaseOffense $offense): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        try {
            $this->caseService->removeOffenseCharge($case, $offense, $validated['reason'], auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Offense removed from the case.');
    }

    public function confirmSanction(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);
        $this->assertEscalationClear($case);

        $validated = $request->validate([
            'sanction_category' => ['required', 'integer', 'between:1,5'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! \App\Models\OffenseCategory::whereKey($validated['sanction_category'])->where('is_active', true)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['sanction_category' => 'Choose an active offense category.']);
        }

        $recommended = $this->currentRecommendedCategory($case);
        if ($recommended === null) {
            $recommended = $case->confirmed_offense_id
                ? $this->sanctionService->recommend($case->confirmed_offense_id, $case->prior_offense_count)['sanction_category']
                : $case->suggested_sanction_category;
        }
        $recommended = (int) ($recommended ?? 0);
        $selected = (int) $validated['sanction_category'];

        // Same floor the offense and resolution-path overrides carry: a reason short
        // enough to be "n/a" is not a reason, and the client minlength that drives the
        // shared length counter has to be enforced here too.
        if ($recommended > 0 && $selected !== $recommended) {
            $reason = trim((string) ($validated['override_reason'] ?? ''));

            if ($reason === '') {
                return back()->withErrors([
                    'override_reason' => 'An override reason is required when changing the recommended sanction category.',
                ])->withInput();
            }

            if (mb_strlen($reason) < 20) {
                return back()->withErrors([
                    'override_reason' => 'The override reason must be at least 20 characters.',
                ])->withInput();
            }
        }

        try {
            $this->caseService->confirmSanction(
                $case,
                $selected,
                auth()->id(),
                $validated['override_reason'] ?? null
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Sanction category confirmed. Proceed to issue the Notice to Explain.');
    }

    public function issueNTE(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);
        $this->assertEscalationClear($case);

        if (! $case->confirmed_offense_id) {
            return back()->with('error', 'An offense must be confirmed before issuing the NTE.');
        }

        if ($case->case_type !== 'major') {
            return back()->with('error', 'The Notice to Explain procedure applies to major offense cases only. Minor cases are resolved directly.');
        }

        if (! $case->assigned_sanction_category) {
            return back()->with('error', 'A sanction category must be confirmed before issuing the NTE.');
        }

        $validated = $request->validate([
            'handbook_section' => ['nullable', 'string', 'max:50'],
            'handbook_page' => ['nullable', 'string', 'max:10'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'additional_content' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->caseService->issueNTE($case, $validated, auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Notice to Explain issued. Student has 5 days to respond.');
    }

    public function voidNTE(Request $request, DisciplinaryCase $case, CaseNotice $notice): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        try {
            $this->caseService->voidNTE($case, $notice, $validated['reason'], (int) auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Notice to Explain voided. The original notice remains in the case history.');
    }

    public function extendNTE(Request $request, DisciplinaryCase $case, CaseNotice $notice): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'response_deadline' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        try {
            $this->caseService->extendNTEDeadline(
                $case,
                $notice,
                Carbon::parse($validated['response_deadline'])->startOfDay(),
                $validated['reason'],
                (int) auth()->id()
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['response_deadline' => $exception->getMessage()]);
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'NTE response deadline extended. The previous deadline was preserved in the audit log.');
    }

    public function reissueNTE(Request $request, DisciplinaryCase $case, CaseNotice $notice): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'correction_reason' => ['required', 'string', 'min:20', 'max:2000'],
            'handbook_section' => ['nullable', 'string', 'max:50'],
            'handbook_page' => ['nullable', 'string', 'max:10'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'additional_content' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->caseService->reissueNTE(
                $case,
                $notice,
                $validated,
                $validated['correction_reason'],
                (int) auth()->id()
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Corrected Notice to Explain issued. Both versions remain in the case history.');
    }

    public function acknowledgeEscalation(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        try {
            $case = $this->ensureEscalationFlag($case, (int) auth()->id());
            $this->caseService->acknowledgeEscalation($case, auth()->id(), $validated['reason']);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Escalation alert dismissed. Reason recorded in the audit trail.');
    }

    public function applyEscalation(DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        try {
            $case = $this->ensureEscalationFlag($case, (int) auth()->id());
            $this->caseService->escalateHabitualDisregard($case, (int) auth()->id());
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'MAJOR-19 — Habitual Disregard added to the case. Original charges were preserved.');
    }

    private function ensureEscalationFlag(DisciplinaryCase $case, int $actorId): DisciplinaryCase
    {
        if ($case->escalation_flagged && ! $case->escalation_acknowledged_at) {
            return $case;
        }

        $evaluation = $this->escalationService->check($case);
        if (! $evaluation['escalation_flagged']) {
            throw new \RuntimeException('The current case does not meet a habitual-disregard threshold.');
        }

        $case = $this->applyEscalationFlag($case, $evaluation);

        app(RiskScoringService::class)->computeForStudent(
            $case->student_id,
            'case_escalation_flagged',
            $actorId
        );

        return $case;
    }

    /**
     * Persist an escalation finding onto the case.
     *
     * EscalationService also returns suggested_offense_override_id, which is not a
     * column on `cases`. Merging the raw result into update() meant mass-assignment
     * protection silently discarded it, so the MAJOR-19 charge the rule recommends
     * never reached suggested_offense_id — the column the offense picker reads to
     * rank its suggestions. Map it across explicitly instead.
     *
     * @param  array{escalation_flagged: bool, escalation_flag_reason: string|null, suggested_offense_override_id: int|null}  $evaluation
     */
    private function applyEscalationFlag(DisciplinaryCase $case, array $evaluation): DisciplinaryCase
    {
        $case->update([
            'escalation_flagged' => true,
            'escalation_flag_reason' => $evaluation['escalation_flag_reason'],
            'suggested_offense_id' => $evaluation['suggested_offense_override_id']
                ?? $case->suggested_offense_id,
            'escalation_acknowledged_by' => null,
            'escalation_acknowledged_at' => null,
        ]);

        return $case->fresh();
    }

    public function saveEvaluation(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        if (! $case->explanation) {
            return back()->with('error', 'No explanation is on record for this case.');
        }

        $validated = $request->validate([
            'admission_type' => ['required', 'in:full_admission,partial_admission,full_denial,no_response'],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->caseService->saveEvaluation(
            $case,
            $case->explanation,
            $validated['admission_type'],
            $validated['evaluation_notes'] ?? null,
            auth()->id(),
            $this->sanctionService
        );

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Evaluation saved. Path recommendation computed — choose how to proceed.');
    }

    public function correctEvaluation(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        if (! $case->explanation) {
            return back()->with('error', 'No explanation is on record for this case.');
        }

        $validated = $request->validate([
            'admission_type' => ['required', 'in:full_admission,partial_admission,full_denial,no_response'],
            'evaluation_notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        try {
            $this->caseService->correctEvaluation(
                $case,
                $case->explanation,
                $validated['admission_type'],
                $validated['evaluation_notes'] ?? null,
                $validated['reason'],
                auth()->id(),
                $this->sanctionService
            );
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Evaluation corrected. Path recommendation recomputed.');
    }

    public function initiateResolution(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        if ($case->status !== 'under_review') {
            return back()->with('error', 'This action is only available for cases under review.');
        }

        $validated = $request->validate([
            'override_reason' => ['nullable', 'string', 'min:20', 'max:1000'],
        ], [
            'override_reason.min' => 'The override reason must be at least 20 characters.',
        ]);

        // Resolving directly is only an override when the system pointed at UPCC.
        $isOverride = $case->path_recommendation === 'upcc_referral';

        if ($isOverride && empty($validated['override_reason'])) {
            return back()->withErrors([
                'override_reason' => 'An override reason is required when resolving directly against a UPCC referral recommendation.',
            ]);
        }

        $this->caseService->initiateResolution(
            $case,
            auth()->id(),
            $isOverride ? $validated['override_reason'] : null
        );

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Case moved to pending resolution. Open the resolution form to complete it.');
    }

    public function resolution(DisciplinaryCase $case): View|RedirectResponse
    {
        $this->authorizeCase($case);

        if ($case->upccReferral) {
            return redirect()
                ->route('officer.cases.show', $case)
                ->with('info', 'This case went through the UPCC process — the Discipline Coordinator records its resolution.');
        }

        if ($case->status !== 'pending_resolution') {
            return redirect()
                ->route('officer.cases.show', $case)
                ->with('info', 'This case is no longer pending resolution.');
        }

        $case->load([
            'student.studentProfile',
            'incidentReport',
            'confirmedOffense',
            'explanation',
            'resolution',
        ]);

        $sanctionRecommendation = null;
        if ($case->assigned_sanction_category === 2 && $case->confirmed_offense_id) {
            $sanctionRecommendation = $this->sanctionService->recommend(
                $case->confirmed_offense_id,
                $case->prior_offense_count
            );
        }

        return view('officer.cases.resolution', [
            'case' => $case,
            'sanctionRecommendation' => $sanctionRecommendation,
            'activeNav' => 'cases',
        ]);
    }

    /**
     * Records the resolution of a non-UPCC case.
     *
     * Refuses cases that went through UPCC (the Coordinator records
     * those), cases not currently pending resolution, and any
     * intervention category that disagrees with the sanction category
     * already assigned to the case - the officer confirms the category
     * earlier in the workflow and cannot silently change it here.
     */
    public function storeResolution(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);

        if ($case->upccReferral) {
            return back()->with('error', 'This case went through the UPCC process — the Discipline Coordinator records its resolution.');
        }

        if ($case->status !== 'pending_resolution') {
            return back()->with('error', 'This case is not currently pending resolution.');
        }

        $category = (int) $request->input('intervention_category');

        if (! $case->assigned_sanction_category) {
            return back()->with('error', 'The intervention category is missing. Please confirm the sanction category before recording the resolution.');
        }

        $rules = [
            'intervention_category' => [
                'required',
                'integer',
                'between:1,5',
                Rule::in([(int) $case->assigned_sanction_category]),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($category === 1) {
            $rules['probation_terms'] = ['required', 'integer', 'min:1'];
            // The handbook measures probation in academic terms, so the end date is
            // derived from the calendar. The typed date is only asked for -- and only
            // required -- when the calendar does not yet reach that far ahead.
            $rules['probation_end_date'] = AcademicPeriod::endOfTermsFrom(
                today(),
                (int) $request->input('probation_terms', 3),
            )
                ? ['nullable', 'date', 'after:today']
                : ['required', 'date', 'after:today'];
            $rules['counseling_referred'] = ['nullable', 'boolean'];
        }

        if ($category === 2) {
            $hoursRange = ['min' => 1, 'max' => 999];

            if ($case->confirmed_offense_id) {
                $recommendation = $this->sanctionService->recommend($case->confirmed_offense_id, $case->prior_offense_count);
                $hoursRange = [
                    'min' => $recommendation['us_hours_recommended'] ?? $recommendation['us_hours_min'] ?? 1,
                    'max' => $recommendation['us_hours_recommended'] ?? $recommendation['us_hours_max'] ?? 999,
                ];
            }

            $rules['us_hours_assigned'] = [
                'nullable',
                'numeric',
                "min:{$hoursRange['min']}",
                "max:{$hoursRange['max']}",
            ];
            $rules['counseling_referred'] = ['nullable', 'boolean'];
            $rules['discipline_education_required'] = ['nullable', 'boolean'];
            $rules['discipline_education_session_at'] = [
                'required_if:discipline_education_required,1',
                'nullable',
                'date',
                'after_or_equal:tomorrow',
            ];
            $rules['evaluation_monitoring_required'] = ['nullable', 'boolean'];
            // The monitoring period is part of the resolution, the way the Discipline
            // Education session date is: a sanction issued without an end date can never
            // start, remind, or close itself.
            $rules['evaluation_monitoring_end_date'] = [
                'required_if:evaluation_monitoring_required,1',
                'nullable',
                'date',
                'after:today',
            ];
        }

        // The counseling referral's Guidance Officer is auto-matched by the student's
        // department (see GuidanceReferralService::matchOfficerForStudent()) rather than
        // picked here -- the department relationship already determines who should handle
        // it. Reason and urgency remain the officer's own input: what to actually counsel
        // the student about, and how soon they're expected to be seen.
        if (in_array($category, [1, 2], true)) {
            $rules['referral_reason'] = ['required_if:counseling_referred,1', 'nullable', 'string', 'min:10', 'max:1000'];
            $rules['urgency'] = ['required_if:counseling_referred,1', 'nullable', Rule::in(array_keys(CounselingReferral::URGENCY_LEVELS))];
        }

        try {
            $validated = $request->validate($rules, [
                'discipline_education_session_at.after_or_equal' => 'Choose tomorrow or a later date for the Discipline Education Program.',
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        }

        $resolutionLevel = $case->upccReferral ? 'upcc' : 'sdo_level';

        $this->caseService->recordResolution(
            $case,
            array_merge($validated, [
                'resolution_level' => $resolutionLevel,
                'upcc_referral_id' => $case->upccReferral?->id,
            ]),
            auth()->id()
        );

        return redirect()->route('officer.cases.show', $case)
            ->with('success', 'Resolution recorded.'.($category === 2 ? ' Review and issue the NFI to the student.' : ' Appeal window is now open.'));
    }

    /**
     * The NFI document as the student will receive it. Before issuance this renders an
     * unsaved draft built by the same code that issues it, so "review the pre-filled
     * notice" is something the officer can actually do rather than a leap of faith.
     */
    public function nfi(DisciplinaryCase $case): Response|RedirectResponse
    {
        $this->authorizeCase($case);

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'resolution',
            'notices' => fn ($query) => $query
                ->where('notice_type', 'notice_of_formative_intervention')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $issued = $case->notices->first();

        if (! $issued && ! $case->resolution) {
            return redirect()
                ->route('officer.cases.show', $case)
                ->with('error', 'Record the resolution before previewing the Notice of Formative Intervention.');
        }

        $notice = $issued ?? $this->caseService->draftNFI($case, $case->resolution, auth()->id());

        return $this->notices->stream($notice, $case, isPreview: $issued === null);
    }

    public function nteNotice(DisciplinaryCase $case): Response
    {
        $this->authorizeCase($case);

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'notices' => fn ($query) => $query
                ->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $notice = $case->notices->first();

        if (! $notice) {
            abort(404);
        }

        return $this->notices->stream($notice, $case);
    }

    public function hearingNotice(DisciplinaryCase $case): Response
    {
        $this->authorizeCase($case);

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'upccReferral',
            'notices' => fn ($query) => $query
                ->where('notice_type', 'notice_of_hearing')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $notice = $case->notices->first();

        if (! $notice) {
            abort(404);
        }

        return $this->notices->stream($notice, $case, referral: $case->upccReferral);
    }

    public function issueNFI(DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);

        if ($case->upccReferral) {
            return back()->with('error', 'This case went through the UPCC process — the Discipline Coordinator issues its NFI.');
        }

        $resolution = $case->resolution;

        if (! $resolution || $resolution->intervention_category !== 2) {
            return back()->with('error', 'NFI issuance is only applicable to Category 2 resolutions.');
        }

        $alreadyIssued = $case->notices()
            ->where('notice_type', 'notice_of_formative_intervention')
            ->where('is_voided', false)
            ->exists();

        if ($alreadyIssued) {
            return back()->with('error', 'An NFI has already been issued for this case.');
        }

        $this->caseService->issueNFI($case, $resolution, auth()->id());

        return redirect()->route('officer.cases.show', $case)
            ->with('success', 'Notice of Formative Intervention issued to the student.');
    }

    public function forwardToCoordinator(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->caseService->forwardToCoordinator($case, $validated['reason'], auth()->id());

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Case forwarded to the Discipline Coordinator.');
    }

    public function dismiss(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->caseService->dismiss($case, $validated['reason'], auth()->id());

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Case dismissed. Reason recorded internally.');
    }

    public function requestVoid(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertNotForwardedToCoordinator($case);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $voided = $this->caseService->requestVoid($case, $validated['reason'], auth()->id());
        $message = $voided
            ? 'Case voided immediately.'
            : 'Void request submitted. Awaiting Coordinator approval.';

        return redirect(route('officer.cases.show', $case).'#action-panel')->with('success', $message);
    }

    public function resolveMinor(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);
        $this->assertEscalationClear($case);

        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->caseService->resolveMinorCase($case, $validated['resolution_notes'], auth()->id());

        return redirect(route('officer.cases.show', $case).'#action-panel')
            ->with('success', 'Minor case resolved and recorded.');
    }

    public function proceedNoResponse(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeCase($case);

        if ($case->status !== 'pending_nte') {
            return back()->with('error', 'This action is only available for pending NTE cases.');
        }

        $activeNte = $case->notices()
            ->where('notice_type', 'notice_to_explain')
            ->where('is_voided', false)
            ->latest('issued_at')
            ->first();

        if (! $activeNte?->isOverdue()) {
            return back()->with('error', 'The response deadline has not yet passed.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        $this->caseService->proceedWithNoResponse($case, $validated['reason'], (int) auth()->id());

        return redirect()->route('officer.cases.show', $case)
            ->with('success', 'Case advanced. Proceed to evaluate.');
    }

    public function offenseSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $offenses = OffenseCatalog::query()
            ->where('is_active', true)
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($search) use ($q): void {
                    $search->where('title', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%")
                        ->orWhere('keywords', 'like', "%{$q}%");
                });
            })
            ->orderByRaw("CASE offense_type WHEN 'minor' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->limit(15)
            ->get(['id', 'code', 'title', 'offense_type']);

        return response()->json($offenses);
    }
}
