<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplineEducationAttendance;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;
use App\Models\UsAssignment;
use App\Notifications\DisciplineEducationAttendanceNotification;
use App\Notifications\DisciplineEducationScheduleNotification;
use App\Notifications\EvaluationMonitoringNotification;
use App\Services\GuidanceReferralService;
use App\Services\MonitoringPeriodService;
use App\Services\RiskScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SanctionController extends Controller
{
    private const TYPE_LABELS = [
        'probation' => 'Probation',
        'counseling_referral' => 'Counseling Referral',
        'university_service' => 'University Service',
        'discipline_education' => 'Discipline Education',
        'evaluation_monitoring' => 'Evaluation / Monitoring',
        'non_readmission' => 'Non-Readmission',
        'exclusion' => 'Exclusion',
        'expulsion' => 'Expulsion',
        'written_warning' => 'Written Warning',
    ];

    public function __construct(
        private readonly GuidanceReferralService $referralService,
        private readonly MonitoringPeriodService $periods
    ) {
        $this->middleware(function (Request $request, $next) {
            $sanction = $request->route('sanction');

            if ($sanction instanceof Sanction) {
                abort_unless($sanction->isVisibleToOfficer($request->user()), 403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['type', 'search', 'status']);
        $type = $filters['type'] ?? null;
        $officer = $request->user()->loadMissing('academicUnit');

        $query = Sanction::with([
            'student.studentProfile',
            'resolution.case',
            'usAssignment.supervisor',
            'counselingReferral.guidanceOfficer',
        ])
            ->forOfficerDepartment($officer)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(fn ($searchQuery) => $searchQuery
                    ->whereHas('student', fn ($studentQuery) => $studentQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereHas('studentProfile', fn ($profileQuery) => $profileQuery
                            ->where('student_number', 'like', "%{$search}%")))
                    ->orWhereHas('resolution.case', fn ($caseQuery) => $caseQuery
                        ->where('case_number', 'like', "%{$search}%")));
            })
            ->when($type, fn ($q, $value) => $q->where('sanctions.sanction_type', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('sanctions.status', $value))
            ->select('sanctions.*')
            ->orderByDesc('sanctions.created_at')
            ->orderByDesc('sanctions.id');

        $sanctions = $query->paginate(PerPage::resolve())->withQueryString();

        // Ajax requests (see public/js/live-ux.js [data-ajax-table]) get only the table fragment, not the full page.
        if ($request->ajax()) {
            return view('officer.sanctions._table', [
                'sanctions' => $sanctions,
                'type' => $type,
                'filters' => $filters,
                'typeLabels' => self::TYPE_LABELS,
            ]);
        }

        $raw = Sanction::forOfficerDepartment($officer)
            ->selectRaw("
            SUM(status = 'pending') as pending,
            SUM(status = 'in_progress') as in_progress,
            SUM(status = 'completed') as completed,
            SUM(sanction_type != 'university_service' AND status IN ('pending', 'in_progress') AND due_date IS NOT NULL AND due_date < CURDATE()) as overdue
        ")->first();

        $stats = [
            'pending' => (int) $raw->pending,
            'in_progress' => (int) $raw->in_progress,
            'completed' => (int) $raw->completed,
            'overdue' => (int) $raw->overdue + UsAssignment::forOfficerDepartment($officer)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereDate('target_completion_date', '<', today())
                ->count(),
        ];

        return view('officer.sanctions.index', [
            'sanctions' => $sanctions,
            'stats' => $stats,
            'type' => $type,
            'filters' => $filters,
            'assignedDepartments' => $officer->assignedAcademicUnits(),
            'typeLabels' => self::TYPE_LABELS,
            'activeNav' => 'sanctions',
        ]);
    }

    public function show(Sanction $sanction): View
    {
        $sanction->load([
            'student.studentProfile',
            'resolution.case',
            'verifiedBy',
            'usAssignment.supervisor',
            'counselingReferral.guidanceOfficer',
            'disciplineEducationAttendances.recordedBy',
        ]);

        // Other sanctions issued on the same case resolution -- e.g. a counseling referral
        // raised from an adverse evaluation finding. Without this the officer who just
        // raised one had no way back to it except searching the full sanctions list.
        $relatedSanctions = $sanction->case_resolution_id
            ? Sanction::where('case_resolution_id', $sanction->case_resolution_id)
                ->whereKeyNot($sanction->getKey())
                ->with('counselingReferral.guidanceOfficer')
                ->orderBy('created_at')
                ->get()
            : collect();

        $monitoringCases = collect();
        $probationSlips = collect();

        if ($sanction->sanction_type === 'evaluation_monitoring') {
            $monitoringCases = $this->periods->casesDuring($sanction);
        }

        // Probation escalates on "any major offense and/or infringement" (Section VI.E),
        // so its panel shows violation slips alongside cases.
        if ($sanction->sanction_type === 'probation') {
            $monitoringCases = $this->periods->casesDuring($sanction);
            $probationSlips = $this->periods->violationSlipsDuring($sanction);
        }

        $activeRegistrationHold = StudentMonitoringFlag::where('student_id', $sanction->student_id)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->first();

        return view('officer.sanctions.show', [
            'sanction' => $sanction,
            'typeLabel' => self::TYPE_LABELS[$sanction->sanction_type]
                ?? ucwords(str_replace('_', ' ', $sanction->sanction_type)),
            'monitoringCases' => $monitoringCases,
            'probationSlips' => $probationSlips,
            'relatedSanctions' => $relatedSanctions,
            'typeLabels' => self::TYPE_LABELS,
            'activeRegistrationHold' => $activeRegistrationHold,
            'activeNav' => 'sanctions',
        ]);
    }

    public function recordDisciplineEducationAttendance(Request $request, Sanction $sanction): RedirectResponse
    {
        abort_unless($sanction->sanction_type === 'discipline_education', 404);
        $sanction->loadMissing(['student', 'resolution.case']);

        $attendance = $sanction->disciplineEducationAttendances()
            ->where('status', 'scheduled')
            ->latest('session_at')
            ->first();

        if (! $attendance) {
            return back()->with('error', 'No scheduled Discipline Education session is available for attendance recording.');
        }

        if ($attendance->session_at->isFuture()) {
            return back()->with('error', 'Attendance can only be recorded once the scheduled session has started.');
        }

        $validated = $request->validate([
            'attendance_status' => ['required', Rule::in(['present', 'absent'])],
        ]);

        DB::transaction(function () use ($sanction, $attendance, $validated): void {
            $oldStatus = $sanction->status;
            $newStatus = $validated['attendance_status'] === 'present' ? 'completed' : 'non_compliant';

            $attendance->update([
                'status' => $validated['attendance_status'],
                'recorded_by' => auth()->id(),
            ]);
            $sanction->update([
                'status' => $newStatus,
                'completed_at' => $newStatus === 'completed' ? now() : null,
                'verified_by' => $newStatus === 'completed' ? auth()->id() : null,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'sanctions',
                'record_id' => $sanction->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => $newStatus,
                    'discipline_education_attendance' => $validated['attendance_status'],
                    'session_at' => $attendance->session_at,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            if ($sanction->student) {
                $sanction->student->notify(
                    new DisciplineEducationAttendanceNotification($sanction, $attendance)
                );
            }
        });

        return back()->with('success', 'Discipline Education attendance recorded.');
    }

    /**
     * Schedules a new Discipline Education session, replacing the current one.
     *
     * Not derived from a specific handbook step — no scheduled DO process
     * currently spells out what happens after a missed session, and marking a
     * single absence permanently Non-Compliant with no way back was an
     * implementation gap, not a deliberate policy choice. The prior attendance
     * record is left untouched (a missed session is still true and stays on
     * record) and a new one is created instead of edited in place, mirroring
     * how a Notice to Explain is voided and reissued rather than rewritten -
     * the same reasoning applies here: an already-notified date shouldn't be
     * silently changed underneath the student.
     */
    public function rescheduleDisciplineEducationAttendance(Request $request, Sanction $sanction): RedirectResponse
    {
        abort_unless($sanction->sanction_type === 'discipline_education', 404);

        if (! in_array($sanction->status, ['pending', 'non_compliant'], true)) {
            return back()->with('error', 'This sanction is already completed and cannot be rescheduled.');
        }

        $sanction->loadMissing(['student', 'resolution.case']);

        $validated = $request->validate([
            'session_at' => ['required', 'date', 'after:now'],
            'reschedule_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ], [
            'session_at.after' => 'The new session must be scheduled in the future.',
        ]);

        DB::transaction(function () use ($sanction, $validated): void {
            $oldStatus = $sanction->status;

            $attendance = DisciplineEducationAttendance::create([
                'sanction_id' => $sanction->id,
                'session_at' => $validated['session_at'],
                'status' => 'scheduled',
                'notes' => $validated['reschedule_reason'],
                'recorded_by' => auth()->id(),
            ]);

            $sanction->update([
                'status' => 'pending',
                'due_date' => \Carbon\Carbon::parse($validated['session_at'])->toDateString(),
                'completed_at' => null,
                'verified_by' => null,
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'sanctions',
                'record_id' => $sanction->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => 'pending',
                    'discipline_education_rescheduled_to' => $attendance->session_at,
                ],
                'reason' => $validated['reschedule_reason'],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            if ($sanction->student) {
                $sanction->student->notify(
                    new DisciplineEducationScheduleNotification($sanction, $attendance)
                );
                $attendance->update(['notification_sent_at' => now()]);
            }
        });

        return back()->with('success', 'Discipline Education session rescheduled. The student has been notified.');
    }

    /**
     * Updates a Category 2 evaluation-monitoring sanction.
     *
     * Category 2 is formative intervention, so a period closes on the
     * officer's assessment outcome and there is no failure status to
     * record - escalation belongs to Category 1 probation alone
     * (Handbook Section VI.E). Validation is done by hand rather than
     * via $request->validate() so a rejection can surface as one toast
     * message on both routes this form takes: the ajax submit and the
     * plain-HTML fallback used when that fetch fails.
     */
    public function updateEvaluationMonitoring(Request $request, Sanction $sanction): RedirectResponse|JsonResponse
    {
        abort_unless($sanction->sanction_type === 'evaluation_monitoring', 404);
        $sanction->loadMissing(['student', 'resolution.case']);

        // Category 2 is formative intervention, so a period closes on the officer's
        // finding about the intervention -- there is no failure status to record here.
        // Escalation belongs to Category 1 probation alone (Handbook Section VI.E).
        //
        // Validated by hand rather than $request->validate() so a rejection can be
        // reported as a single toast message on both paths this form can take: the
        // ajax submit (see [data-ajax-toast-form] in live-ux.js), and the plain-HTML
        // fallback for when that fetch itself fails.
        $validator = validator($request->all(), [
            'due_date' => ['required', 'date', 'after_or_equal:'.$sanction->created_at->toDateString()],
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'assessment_outcome' => [
                'required_if:status,completed',
                'nullable',
                Rule::in(array_keys(Sanction::ASSESSMENT_OUTCOMES)),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();

            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            // withErrors() keeps $errors populated for assertSessionHasErrors() and any
            // future inline use; with('error', ...) is what the toast actually reads.
            return back()->withInput()->withErrors($validator)->with('error', $message);
        }

        $validated = $validator->validated();

        $isCompleted = $validated['status'] === 'completed';

        $oldValues = $sanction->only(['due_date', 'status', 'notes', 'assessment_outcome']);

        // Only a fresh transition into the finding raises a referral -- an officer
        // amending notes or extending an already-closed period must not open a second one.
        $isNewAdverseFinding = $isCompleted
            && $validated['assessment_outcome'] === 'needs_further_intervention'
            && $oldValues['assessment_outcome'] !== 'needs_further_intervention';

        $referralRaised = false;

        DB::transaction(function () use ($sanction, $validated, $isCompleted, $isNewAdverseFinding, &$referralRaised): void {
            $sanction->update([
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'assessment_outcome' => $isCompleted ? $validated['assessment_outcome'] : null,
                'completed_at' => $isCompleted ? now() : null,
                'verified_by' => $isCompleted ? auth()->id() : null,
                'intervention_reminder_sent_at' => null,
                // The officer's assessment answers the flag the scheduler raised; extending an
                // in-progress period re-arms it for the new end date.
                'monitoring_review_flagged_at' => null,
            ]);

            if ($isNewAdverseFinding && ! $this->referralService->hasPendingReferral($sanction->case_resolution_id)) {
                $caseNumber = $sanction->resolution?->case?->case_number ?? 'this case';

                $this->referralService->raiseFromFinding(
                    $sanction,
                    "Evaluation monitoring for {$caseNumber} closed with a finding of Needs Further "
                        .'Intervention: the formative period did not resolve the underlying concern.',
                    auth()->id()
                );

                $referralRaised = true;
            }

            if ($isNewAdverseFinding) {
                // The finding is now a scored risk factor (see RiskScoringService), so it
                // must recompute immediately rather than waiting for an unrelated event to.
                app(RiskScoringService::class)->computeForStudent(
                    $sanction->student_id,
                    'evaluation_needs_intervention',
                    auth()->id()
                );
            }
        });

        if ($sanction->student) {
            $notificationEvent = match (true) {
                ! $isCompleted => EvaluationMonitoringNotification::EXTENDED,
                $validated['assessment_outcome'] === 'needs_further_intervention' => EvaluationMonitoringNotification::CLOSED_NEEDS_INTERVENTION,
                default => EvaluationMonitoringNotification::CLOSED_SATISFACTORY,
            };

            $sanction->student->notify(new EvaluationMonitoringNotification($sanction, $notificationEvent));
            $sanction->update(['intervention_notification_sent_at' => now()]);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'sanctions',
            'record_id' => $sanction->id,
            'old_values' => $oldValues,
            'new_values' => $sanction->only(['due_date', 'status', 'notes', 'assessment_outcome']),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $message = 'Evaluation monitoring period updated.';
        if ($referralRaised) {
            $message .= ' A counseling referral was raised from this finding and is awaiting assignment.';
        }

        if ($request->wantsJson()) {
            // The ajax submit reloads the page on success rather than patching the DOM --
            // saving this sanction can change more of the page than this one form (status
            // badges, the case list, a newly-raised referral card). Flashing here means
            // that reload's own render is what turns this into the success toast, the same
            // mechanism every other page on the site already uses.
            session()->flash('success', $message);

            return response()->json(['message' => $message]);
        }

        return back()->with('success', $message);
    }

    /**
     * Raises a fresh counseling referral off one the Guidance Office already returned
     * incomplete. Without this, a Non-Compliant counseling referral was a dead end for
     * the officer -- the sanction just sat flagged with no way to give the student
     * another chance at the underlying requirement.
     */
    public function reReferToGuidance(Request $request, Sanction $sanction): RedirectResponse
    {
        abort_unless($sanction->sanction_type === 'counseling_referral', 404);

        $referral = $sanction->counselingReferral;

        abort_if(! $referral || $referral->status !== 'incomplete', 422, 'Only a referral returned incomplete can be re-referred.');

        if ($this->referralService->hasPendingReferral($sanction->case_resolution_id)) {
            return back()->with('error', 'A counseling referral is already pending on this case.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        $this->referralService->raiseFromFinding($sanction, $validated['reason'], auth()->id());

        return back()->with('success', 'A new counseling referral was raised and assigned to the Guidance Office.');
    }
}
