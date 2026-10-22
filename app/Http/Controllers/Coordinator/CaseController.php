<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\CaseOffense;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\UpccAppealAttachment;
use App\Models\UpccReferral;
use App\Support\UpccProgressPresenter;
use App\Services\CaseService;
use App\Services\CoordinatorCaseService;
use App\Services\EscalationService;
use App\Services\SanctionRecommendationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function __construct(
        private readonly CoordinatorCaseService $service,
        private readonly EscalationService $escalationService,
        private readonly CaseService $caseService,
        private readonly SanctionRecommendationService $sanctionService,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['search', 'status', 'academic_period_id']);

        $baseCases = DisciplinaryCase::with([
            'student.studentProfile',
            'confirmedOffense',
            'upccReferral',
        ])
            ->whereIn('status', ['pending_upcc', 'pending_resolution'])
            ->forwardedToUpcc()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($searchQuery) => $searchQuery->where('case_number', 'like', "%{$s}%")
                ->orWhereHas('student', fn ($studentQuery) => $studentQuery->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                )
            )
            )
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s)
            )
            ->when($filters['academic_period_id'] ?? null, fn ($q, $periodId) => $q
                ->whereHas('incidentReport', fn ($incident) => $incident
                    ->where('academic_period_id', $periodId)));

        $majorCases = (clone $baseCases)
            ->major()
            ->orderByDesc('forwarded_at')
            ->paginate(PerPage::resolve('major_per_page'), ['*'], 'major_cases_page')
            ->withQueryString();

        // Ajax requests (see public/js/live-ux.js [data-ajax-table]) get only the table fragment, not the full page.
        if ($request->ajax()) {
            return view('coordinator.cases._major-cases-table', [
                'majorCases' => $majorCases,
            ]);
        }

        $raw = DB::table('cases')
            ->leftJoin('upcc_referrals', 'upcc_referrals.case_id', '=', 'cases.id')
            ->leftJoin('incident_reports', 'incident_reports.id', '=', 'cases.incident_report_id')
            ->when($filters['academic_period_id'] ?? null, fn ($statsQuery, $periodId) => $statsQuery
                ->where('incident_reports.academic_period_id', $periodId))
            ->selectRaw("
                SUM(cases.forwarded_to_coordinator = 1 AND cases.status = 'pending_upcc' AND upcc_referrals.id IS NULL) as pending_upcc,
                SUM(cases.forwarded_to_coordinator = 1 AND cases.status = 'pending_upcc' AND upcc_referrals.status = 'scheduled') as scheduled,
                SUM(cases.forwarded_to_coordinator = 1 AND cases.status = 'pending_upcc' AND upcc_referrals.status = 'in_progress') as in_progress,
                SUM(cases.forwarded_to_coordinator = 1 AND cases.status = 'pending_upcc' AND upcc_referrals.status = 'concluded') as concluded,
                SUM(cases.forwarded_to_coordinator = 1 AND cases.status = 'pending_resolution') as pending_resolution,
                SUM(cases.void_requires_coordinator_approval = 1 AND cases.status != 'voided') as void_approvals
            ")
            ->first();

        $stats = [
            'active_cases' => DisciplinaryCase::query()
                ->whereIn('status', ['pending_upcc', 'pending_resolution'])
                ->forwardedToUpcc()
                ->count(),
            'pending_upcc' => (int) $raw->pending_upcc,
            'scheduled' => (int) $raw->scheduled,
            'in_progress' => (int) $raw->in_progress,
            'concluded' => (int) $raw->concluded,
            'pending_resolution' => (int) $raw->pending_resolution,
            'void_approvals' => (int) $raw->void_approvals,
            'upcoming_hearings' => UpccReferral::query()
                ->where('status', 'scheduled')
                ->whereBetween('scheduled_conference_at', [now(), now()->copy()->addDays(7)->endOfDay()])
                ->count(),
            'high_risk_cases' => DisciplinaryCase::query()
                ->whereIn('status', ['pending_upcc', 'pending_resolution'])
                ->forwardedToUpcc()
                ->whereHas('student.riskScore', fn ($query) => $query
                    ->whereIn('risk_band', ['high', 'critical']))
                ->count(),
        ];

        return view('coordinator.cases.index', [
            'majorCases' => $majorCases,
            'stats' => $stats,
            'filters' => $filters,
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('starts_on')->get(),
            'coordActiveNav' => 'upcc',
        ]);
    }

    public function recordsIndex(Request $request): View
    {
        $filters = $request->only(['search', 'case_type', 'status', 'academic_period_id', 'academic_unit_id']);

        $academicUnit = $filters['academic_unit_id'] ?? null
            ? AcademicUnit::find($filters['academic_unit_id'])
            : null;

        $cases = DisciplinaryCase::query()
            ->with([
                'student.studentProfile',
                'confirmedOffense',
            ])
            ->whereIn('case_type', ['minor', 'major'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('case_number', 'like', "%{$search}%")
                        ->orWhereHas('student', function ($studentQuery) use ($search): void {
                            $studentQuery->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery
                                    ->where('student_number', 'like', "%{$search}%"));
                        });
                });
            })
            ->when($filters['case_type'] ?? null, fn ($query, $type) => $query->where('case_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['academic_period_id'] ?? null, fn ($query, $periodId) => $query
                ->whereHas('incidentReport', fn ($incident) => $incident
                    ->where('academic_period_id', $periodId)))
            ->when($academicUnit, fn ($query, $unit) => $query
                ->whereHas('student.studentProfile', fn ($profile) => $profile
                    ->forAcademicUnit($unit)))
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        // program name (as stored on the profile) -> owning department acronym,
        // for the per-row Department column. Built once to avoid an N+1 lookup.
        $departments = AcademicProgram::query()
            ->with('academicUnit:id,name')
            ->get()
            ->mapWithKeys(fn (AcademicProgram $program) => [
                $program->name => AcademicUnit::abbreviatedName($program->academicUnit?->name),
            ])
            ->filter()
            ->all();

        $raw = DisciplinaryCase::query()
            ->whereIn('case_type', ['minor', 'major'])
            ->when($filters['academic_period_id'] ?? null, fn ($statsQuery, $periodId) => $statsQuery
                ->whereHas('incidentReport', fn ($incident) => $incident
                    ->where('academic_period_id', $periodId)))
            ->when($academicUnit, fn ($statsQuery, $unit) => $statsQuery
                ->whereHas('student.studentProfile', fn ($profile) => $profile
                    ->forAcademicUnit($unit)))
            ->selectRaw("
                SUM(status = 'open') as open,
                SUM(status = 'pending_nte') as pending_nte,
                SUM(status = 'resolved') as resolved,
                SUM(status = 'under_review') as under_review
            ")
            ->first();

        $stats = [
            'open' => (int) $raw->open,
            'pending_nte' => (int) $raw->pending_nte,
            'resolved' => (int) $raw->resolved,
            'under_review' => (int) $raw->under_review,
        ];

        return view('coordinator.case-records.index', [
            'cases' => $cases,
            'stats' => $stats,
            'filters' => $filters,
            'departments' => $departments,
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('starts_on')->get(),
            'academicUnits' => AcademicUnit::query()->where('is_active', true)->catalog()->get(),
            'coordActiveNav' => 'cases',
        ]);
    }

    public function recordsShow(DisciplinaryCase $case): View
    {
        abort_unless(in_array($case->case_type, ['minor', 'major'], true), 404);

        return view('coordinator.cases.record-details', $this->caseViewData(
            $case,
            'cases',
            'Cases',
            route('coordinator.case-records.index'),
        ));
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if (mb_strlen($search) < 1) {
            return response()->json(['results' => []]);
        }

        $results = DisciplinaryCase::query()
            ->with('student.studentProfile')
            ->whereIn('status', ['pending_upcc', 'pending_resolution'])
            ->forwardedToUpcc()
            ->where(function ($query) use ($search): void {
                $query->where('case_number', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                        ->orWhereHas('studentProfile', fn ($profile) => $profile
                            ->where('student_number', 'like', "%{$search}%")));
            })
            ->latest('forwarded_at')
            ->limit(8)
            ->get()
            ->map(fn (DisciplinaryCase $case) => [
                'case_number' => $case->case_number,
                'student_name' => $case->student?->list_name ?? 'Unknown student',
                'student_number' => $case->student?->studentProfile?->student_number,
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function show(DisciplinaryCase $case): View
    {
        abort_unless($case->forwarded_to_coordinator, 404);

        return $this->renderCase($case, 'upcc', 'UPCC Cases', route('coordinator.cases.index'));
    }

    public function progress(DisciplinaryCase $case): View
    {
        abort_unless($case->forwarded_to_coordinator, 404);

        $viewData = $this->caseViewData(
            $case,
            'upcc',
            'UPCC Cases',
            route('coordinator.cases.index'),
        );

        $viewData['progress'] = UpccProgressPresenter::for($viewData['case']);

        return view('coordinator.cases.progress', $viewData);
    }

    /**
     * Polling endpoint for the case-show page to detect changes without a full refresh.
     */
    public function status(DisciplinaryCase $case): JsonResponse
    {
        abort_unless($case->forwarded_to_coordinator, 404);

        $case->refresh();
        $case->load(['resolution', 'upccReferral']);

        return response()->json([
            'status' => $case->status,
            'updated_at' => $case->updated_at?->toISOString(),
            'resolution_updated_at' => $case->resolution?->updated_at?->toISOString(),
            'upcc_referral_updated_at' => $case->upccReferral?->updated_at?->toISOString(),
        ]);
    }

    private function renderCase(
        DisciplinaryCase $case,
        string $activeNav,
        string $indexLabel,
        string $indexUrl
    ): View {
        return view('coordinator.cases.show', $this->caseViewData(
            $case,
            $activeNav,
            $indexLabel,
            $indexUrl,
        ));
    }

    private function caseViewData(
        DisciplinaryCase $case,
        string $activeNav,
        string $indexLabel,
        string $indexUrl
    ): array {
        $case->load([
            'student.studentProfile',
            'incidentReport.reportedByUser',
            'confirmedOffense',
            'caseOffenses.offense',
            'notices' => fn ($q) => $q->orderBy('created_at'),
            'explanation',
            'upccReferral.referredBy',
            'upccReferral.noShowRecordedBy',
            'upccReferral.appealFiledBy',
            'upccReferral.appealAttachments',
            'upccReferral.resolution.resolvedBy',
            'resolution',
            'resolution.resolvedBy',
            'assignedOfficer',
        ]);

        return [
            'case' => $case,
            'coordActiveNav' => $activeNav,
            'caseIndexLabel' => $indexLabel,
            'caseIndexUrl' => $indexUrl,
            'offenseSummary' => $this->buildOffenseSummary($case),
            'minorHistory' => $this->priorResolvedFindings($case, 'minor'),
            'majorHistory' => $this->priorResolvedFindings($case, 'major'),
            'activeCases' => DisciplinaryCase::where('student_id', $case->student_id)
                ->where('id', '!=', $case->id)
                ->whereNotIn('status', ['resolved', 'dismissed', 'voided'])
                ->orderByDesc('created_at')
                ->get(['id', 'case_number', 'case_type', 'status']),
            'lastHabitualEscalation' => CaseOffense::where('status', 'confirmed')
                ->whereHas('offense', fn ($q) => $q->where('code', 'MAJOR-19'))
                ->whereHas('case', fn ($q) => $q->where('student_id', $case->student_id))
                ->latest('id')
                ->first(),
        ];
    }

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

        $escalationProgress = $this->escalationService->progress($case);

        return [
            'resolved_minor_count' => (int) $counts->resolved_minor_count,
            'active_minor_charge_count' => (int) $counts->active_minor_charge_count,
            'resolved_major_count' => (int) $counts->resolved_major_count,
            'habitual_same_type_count' => $escalationProgress['current_same_type_count'],
            'habitual_different_type_count' => $escalationProgress['different_type_count'],
            'habitual_same_type_threshold' => $escalationProgress['same_type_threshold'],
            'habitual_different_type_threshold' => $escalationProgress['different_type_threshold'],
            'habitual_cycle_reset_at' => $escalationProgress['last_escalation_at'],
        ];
    }

    private function priorResolvedFindings(DisciplinaryCase $case, string $offenseType)
    {
        return CaseOffense::where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('offense_type', $offenseType))
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $case->student_id)
                ->where('id', '!=', $case->id)
                ->where('status', 'resolved'))
            ->with(['offense', 'case'])
            ->orderByDesc('confirmed_at')
            ->get();
    }

    public function scheduleUpcc(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        if ($case->status !== 'pending_upcc') {
            return back()->with('error', 'This case is not currently pending UPCC scheduling.');
        }

        if ($case->upccReferral) {
            return back()->with('error', 'A UPCC conference has already been scheduled for this case.');
        }

        $case->loadMissing('explanation');

        if ($request->filled(['scheduled_conference_date', 'scheduled_conference_time'])) {
            $request->merge([
                'scheduled_conference_at' => $request->input('scheduled_conference_date')
                    .'T'.$request->input('scheduled_conference_time'),
            ]);
        }

        $validated = $request->validate([
            'scheduled_conference_date' => ['required_without:scheduled_conference_at', 'date'],
            'scheduled_conference_time' => ['required_without:scheduled_conference_at', 'date_format:H:i'],
            'scheduled_conference_at' => [
                'required',
                'date',
                'after_or_equal:tomorrow',
            ],
            'conference_location' => ['required', 'string', 'max:255'],
            'proceedings_type' => ['required', 'in:full_hearing,summary'],
            'thru' => ['nullable', 'string', 'max:255'],
            'sdao_head_name' => ['required', 'string', 'max:255'],
            'dean_or_program_chair_name' => ['required', 'string', 'max:255'],
            'cross_program_faculty_name' => ['required', 'string', 'max:255'],
            'student_representative_name' => ['required', 'string', 'max:255'],
        ], [
            'scheduled_conference_at.after_or_equal' => 'The conference date cannot be today or in the past.',
            'scheduled_conference_date.required_without' => 'The conference date is required.',
            'scheduled_conference_time.required_without' => 'The conference time is required.',
        ]);

        unset($validated['scheduled_conference_date'], $validated['scheduled_conference_time']);

        // The Discipline Office coordinator is always the presiding officer; the
        // field is read-only in the form, so set it authoritatively here.
        $validated['presiding_officer_name'] = auth()->user()->full_name;

        $this->service->scheduleUpcc($case, $validated, auth()->id());

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'UPCC conference scheduled and Notice of Hearing issued to the student.');
    }

    public function rescheduleUpcc(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'scheduled') {
            return back()->with('error', 'Only a conference that is scheduled and has not yet begun can be rescheduled.');
        }

        if ($request->filled(['scheduled_conference_date', 'scheduled_conference_time'])) {
            $request->merge([
                'scheduled_conference_at' => $request->input('scheduled_conference_date')
                    .'T'.$request->input('scheduled_conference_time'),
            ]);
        }

        $validated = $request->validate([
            'scheduled_conference_date' => ['required_without:scheduled_conference_at', 'date'],
            'scheduled_conference_time' => ['required_without:scheduled_conference_at', 'date_format:H:i'],
            'scheduled_conference_at' => ['required', 'date', 'after_or_equal:tomorrow'],
            'conference_location' => ['required', 'string', 'max:255'],
            'reschedule_reason' => ['required', 'string', 'min:20', 'max:2000'],
        ], [
            'scheduled_conference_at.after_or_equal' => 'The new conference date cannot be today or in the past.',
            'scheduled_conference_date.required_without' => 'The conference date is required.',
            'scheduled_conference_time.required_without' => 'The conference time is required.',
        ]);

        $this->service->rescheduleUpcc(
            $case,
            $referral,
            [
                'scheduled_conference_at' => $validated['scheduled_conference_at'],
                'conference_location' => $validated['conference_location'],
            ],
            $validated['reschedule_reason'],
            auth()->id()
        );

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'UPCC conference rescheduled. A corrected Notice of Hearing was issued to the student.');
    }

    public function recordAttendance(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'scheduled') {
            return back()->with('error', 'No scheduled conference found for this case.');
        }

        if (! config('prism_demo.allow_early_upcc_attendance')
            && now()->lt($referral->scheduled_conference_at)) {
            return back()->with('error', 'Attendance can be recorded only when the scheduled conference begins.');
        }

        $validated = $request->validate([
            'attended' => ['required', 'in:1,0'],
        ]);

        $this->service->recordAttendance(
            $referral,
            (bool) $validated['attended'],
            auth()->id()
        );

        $message = $validated['attended']
            ? 'Marked the student as present. Next, record the UPCC decision.'
            : 'Marked the student as absent. The panel can still proceed and decide on the case record.';

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', $message);
    }

    public function recordDecision(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'in_progress') {
            return back()->with('error', 'Attendance must be recorded before entering the decision.');
        }

        $validated = $request->validate([
            'decision_summary' => ['required', 'string', 'min:50', 'max:5000'],
            'outcome' => ['required', 'in:violation_established,no_violation'],
        ]);

        $this->service->recordDecision(
            $referral,
            $validated['decision_summary'],
            $validated['outcome'],
            auth()->id()
        );

        if ($validated['outcome'] === 'no_violation') {
            $this->service->closeAsNoViolation($referral, auth()->id());

            return redirect()->route('coordinator.cases.progress', $case)
                ->with('success', 'No violation found. The case has been closed and the student notified.');
        }

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', "Decision recorded. Record the President's Council action before distributing.");
    }

    public function recordCouncilAction(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'concluded') {
            return back()->with('error', 'UPCC decision must be recorded first.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approved,modified'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->service->recordCouncilAction(
            $referral,
            $validated['action'],
            $validated['notes'] ?? null,
            auth()->id()
        );

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'President\'s Council action recorded. Proceed to distribute the decision.');
    }

    public function distributeDecision(DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'concluded') {
            return back()->with('error', 'Decision must be recorded before it can be distributed.');
        }

        // Handbook VI.G.3.b.5-6: every UPCC resolution goes to the President's
        // Council as a recommending body, and the student is informed only once
        // that approval is in. No category carve-out.
        if (! $referral->presidents_council_action) {
            return back()->with('error', "President's Council action must be recorded before the decision can be distributed.");
        }

        if ($referral->decision_distributed_at) {
            return back()->with('error', 'Decision has already been distributed.');
        }

        $this->service->distributeDecision($referral, auth()->id());

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'Decision distributed. The 5-calendar-day appeal window is now open.');
    }

    public function fileAppeal(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || ! $referral->decision_distributed_at) {
            return back()->with('error', 'Decision must be distributed before an appeal can be filed.');
        }

        if (today()->gt($referral->appeal_window_end)) {
            return back()->with('error', 'The appeal window has closed. The decision is now final.');
        }

        if ($referral->appeal_filed) {
            return back()->with('error', 'An appeal has already been filed for this case.');
        }

        $validated = $request->validate([
            'appeal_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->service->fileAppeal($referral, $validated['appeal_notes'] ?? null, auth()->id());

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'Appeal recorded. The case is pending reconsideration.');
    }

    public function viewAppealAttachment(
        DisciplinaryCase $case,
        UpccAppealAttachment $attachment
    ) {
        $referral = $case->upccReferral;

        if (! $referral || (int) $attachment->upcc_referral_id !== (int) $referral->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'Attachment file not found.');
        }

        return response()->file(Storage::disk('local')->path($attachment->file_path), [
            'Content-Type' => Storage::disk('local')->mimeType($attachment->file_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$attachment->file_name.'"',
        ]);
    }

    public function recordReconsiderationOutcome(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || $referral->status !== 'reconsideration_filed') {
            return back()->with('error', 'No active reconsideration found for this case.');
        }

        $validated = $request->validate([
            'outcome' => ['required', 'in:original_upheld,decision_modified'],
            'outcome_summary' => ['required', 'string', 'min:50', 'max:5000'],
        ]);

        $this->service->recordReconsiderationOutcome(
            $referral,
            $validated['outcome'],
            $validated['outcome_summary'],
            auth()->id()
        );

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'Reconsideration outcome recorded. You may now finalize the decision.');
    }

    public function finalizeDecision(DisciplinaryCase $case): RedirectResponse
    {
        $referral = $case->upccReferral;

        if (! $referral || ! $referral->decision_distributed_at) {
            return back()->with('error', 'Decision must be distributed before it can be finalized.');
        }

        $appealWindowStillOpen = $referral->appeal_window_end
            && today()->lte($referral->appeal_window_end);

        if ($appealWindowStillOpen && ! $referral->appeal_filed) {
            return back()->with('error',
                'The appeal window is still open ('
                .Carbon::parse($referral->appeal_window_end)->format('M d, Y')
                .'). Wait for it to close or record a filed appeal first.'
            );
        }

        if ($referral->appeal_filed && $referral->status === 'reconsideration_filed') {
            return back()->with('error', 'An appeal is pending. Resolve the reconsideration before finalizing.');
        }

        $this->service->finalizeDecision($case, $referral, auth()->id());

        return redirect()->route('coordinator.cases.progress', $case)
            ->with('success', 'Decision finalized. You may now record the formal resolution.');
    }

    public function resolution(DisciplinaryCase $case): View|RedirectResponse
    {
        $this->authorizeUpccCase($case);

        if ($case->status !== 'pending_resolution') {
            return redirect()
                ->route('coordinator.cases.progress', $case)
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
        if ($case->confirmed_offense_id) {
            $sanctionRecommendation = $this->sanctionService->recommend(
                $case->confirmed_offense_id,
                $case->prior_offense_count
            );
        }

        return view('coordinator.cases.resolution', [
            'case' => $case,
            'sanctionRecommendation' => $sanctionRecommendation,
        ]);
    }

    /**
     * Records the resolution of a case that went through UPCC.
     *
     * The Coordinator counterpart to the Officer method of the same
     * name. Additionally requires a recorded President's Council action
     * before the resolution can be finalized, and unlike the Officer
     * path does not constrain the category to a pre-assigned value -
     * the UPCC decision is what sets it.
     */
    public function storeResolution(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeUpccCase($case);

        if ($case->status !== 'pending_resolution') {
            return back()->with('error', 'This case is not currently pending resolution.');
        }

        $category = (int) $request->input('intervention_category');

        if (! $case->upccReferral?->presidents_council_action) {
            return back()
                ->withInput()
                ->with('error', "A recorded President's Council action is required before a UPCC resolution can be finalized.");
        }

        $rules = [
            'intervention_category' => ['required', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($category === 1) {
            $rules['probation_terms'] = ['required', 'integer', 'min:1'];
            // Derived from the academic calendar; the typed date is the fallback for a
            // calendar that does not yet reach that far. See Officer\CaseController.
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
                'after:now',
            ];
            $rules['evaluation_monitoring_required'] = ['nullable', 'boolean'];
            // Mirrors the officer resolution form: monitoring cannot be recorded without
            // the period it runs for.
            $rules['evaluation_monitoring_end_date'] = [
                'required_if:evaluation_monitoring_required,1',
                'nullable',
                'date',
                'after:today',
            ];
        }

        // The counseling referral's Guidance Officer is auto-matched by the student's
        // department (see GuidanceReferralService::matchOfficerForStudent()) rather than
        // picked here. Reason and urgency remain the coordinator's own input.
        if (in_array($category, [1, 2], true)) {
            $rules['referral_reason'] = ['required_if:counseling_referred,1', 'nullable', 'string', 'min:10', 'max:1000'];
            $rules['urgency'] = ['required_if:counseling_referred,1', 'nullable', Rule::in(array_keys(CounselingReferral::URGENCY_LEVELS))];
        }

        try {
            $validated = $request->validate($rules);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('error', $e->validator->errors()->first());
        }
        if (! \App\Models\OffenseCategory::whereKey($category)->where('is_active', true)->exists()) {
            return back()->withErrors(['intervention_category' => 'Choose an active offense category.'])->withInput();
        }

        $this->caseService->recordResolution(
            $case,
            array_merge($validated, [
                'resolution_level' => 'upcc',
                'upcc_referral_id' => $case->upccReferral?->id,
            ]),
            auth()->id()
        );

        return redirect()->route('coordinator.cases.show', $case)
            ->with('success', 'Resolution recorded.'.($category === 2 ? ' Review and issue the NFI to the student.' : ' Appeal window is now open.'));
    }

    public function issueNFI(DisciplinaryCase $case): RedirectResponse
    {
        $this->authorizeUpccCase($case);

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

        return redirect()->route('coordinator.cases.show', $case)
            ->with('success', 'Notice of Formative Intervention issued to the student.');
    }

    public function dismiss(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        abort_unless($case->forwarded_to_coordinator, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->caseService->dismiss($case, $validated['reason'], auth()->id());

        return redirect()->route('coordinator.cases.show', $case)
            ->with('success', 'Case dismissed. Reason recorded internally.');
    }

    public function requestVoid(Request $request, DisciplinaryCase $case): RedirectResponse
    {
        abort_unless($case->forwarded_to_coordinator, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        // The Coordinator is the void approver, so there is no one else to route this
        // to — it takes effect immediately rather than sitting on its own approval queue.
        $case->update([
            'void_reason' => $validated['reason'],
            'voided_by' => auth()->id(),
        ]);

        $this->service->approveVoid($case, auth()->id());

        return redirect()->route('coordinator.cases.show', $case)
            ->with('success', 'Case voided.');
    }

    private function authorizeUpccCase(DisciplinaryCase $case): void
    {
        abort_unless((bool) $case->upccReferral, 403, 'This case was not resolved through the UPCC process.');
    }
}
