<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\Sanction;
use App\Models\StudentHandbook;
use App\Models\StudentMonitoringFlag;
use App\Models\UpccAppealAttachment;
use App\Models\UsAssignment;
use App\Services\CoordinatorCaseService;
use App\Services\EscalationService;
use App\Services\NoticeDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CaseController extends Controller
{
    public function __construct(
        private readonly NoticeDocumentService $notices,
    ) {}

    public function index(EscalationService $escalations): View
    {
        $cases = DisciplinaryCase::where('student_id', auth()->id())
            // A void is the office declaring the case should not have existed;
            // keeping the row would re-tell the student about that error forever.
            ->where('status', '!=', 'voided')
            ->with(['resolution', 'notices', 'upccReferral', 'confirmedOffense'])
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        $currentAcademicPeriod = AcademicPeriod::active()->containing(today())->first();

        return view('student.cases.index', [
            'cases' => $cases,
            'activeNav' => 'cases',
            'currentAcademicPeriod' => $currentAcademicPeriod,
            // Students only ever heard about habitual disregard once it had
            // already escalated; this is the same counter the officers see.
            'escalation' => $escalations->studentProgress((int) auth()->id()),
        ]);
    }

    public function show(DisciplinaryCase $case): View|RedirectResponse
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load([
            'notices' => fn ($q) => $q->where('is_voided', false)->orderBy('created_at'),
            'explanation',
            'resolution',
            'confirmedOffense',
            'caseOffenses.offense',
            'incidentReport',
            'upccReferral.appealAttachments',
        ]);

        return view('student.cases.show', [
            'case' => $case,
            'activeNav' => 'cases',
        ]);
    }

    public function status(DisciplinaryCase $case): JsonResponse
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->refresh();
        $case->load('upccReferral');

        return response()->json([
            'status' => $case->status,
            'student_state' => $case->studentWorkflowState(),
            'updated_at' => $case->updated_at?->toISOString(),
            'upcc_referral_updated_at' => $case->upccReferral?->updated_at?->toISOString(),
        ]);
    }

    /**
     * Files the student's UPCC appeal.
     *
     * The decision must have been distributed first (the appeal window
     * opens on distribution, not on the decision itself) and must not
     * already be finalized, which is what closes the window.
     */
    public function submitAppeal(
        Request $request,
        DisciplinaryCase $case,
        CoordinatorCaseService $service
    ): RedirectResponse {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load(['student', 'upccReferral.referredBy']);
        $referral = $case->upccReferral;

        if (! $referral || ! $referral->decision_distributed_at) {
            return back()->with('error', 'The UPCC decision must be distributed before an appeal can be submitted.');
        }

        if ($referral->decision_final_at) {
            return back()->with('error', 'This UPCC decision has already been finalized.');
        }

        if ($referral->appeal_filed) {
            return back()->with('error', 'An appeal has already been submitted for this case.');
        }

        if (! $referral->isAppealWindowOpen()) {
            return back()->with('error', 'The appeal window has closed. Appeals can only be submitted before the deadline.');
        }

        $validated = $request->validate([
            'appeal_notes' => ['required', 'string', 'min:20', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
        ], [
            'attachments.max' => 'You may upload a maximum of 3 supporting files.',
            'attachments.*.mimes' => 'Appeal attachments must be PDF, JPG, PNG, DOC, or DOCX files.',
            'attachments.*.max' => 'Each appeal attachment must not exceed 5MB.',
        ]);

        $service->fileAppeal(
            $referral,
            $validated['appeal_notes'],
            (int) auth()->id(),
            'student_portal'
        );

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments', []) as $file) {
                if ($file instanceof UploadedFile) {
                    $service->storeAppealAttachment($file, $referral, (int) auth()->id());
                }
            }
        }

        $freshReferral = $referral->fresh(['case.student', 'referredBy', 'appealAttachments', 'appealFiledBy']);
        if ($freshReferral) {
            $service->notifyCoordinatorsOfAppeal($freshReferral);
        }

        return redirect()->route('student.cases.show', $case)
            ->with('success', 'Your appeal has been submitted. The Discipline Coordinator will review it for reconsideration.');
    }

    public function waiveAppeal(
        DisciplinaryCase $case,
        CoordinatorCaseService $service
    ): RedirectResponse {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load(['student', 'upccReferral']);
        $referral = $case->upccReferral;

        if (! $referral || ! $referral->decision_distributed_at) {
            return back()->with('error', 'The UPCC decision must be distributed before you can decline to appeal.');
        }

        if ($referral->decision_final_at || $referral->appeal_waived_at) {
            return back()->with('error', 'This UPCC decision has already been finalized.');
        }

        if ($referral->appeal_filed) {
            return back()->with('error', 'An appeal has already been submitted for this case.');
        }

        if (! $referral->isAppealWindowOpen()) {
            return back()->with('error', 'The appeal window is no longer open.');
        }

        $service->waiveAppealAndFinalize($case, $referral, (int) auth()->id());

        return redirect()->route('student.cases.show', $case)
            ->with('success', 'No appeal recorded. The UPCC decision is now final and the case may proceed.');
    }

    public function viewAppealAttachment(
        DisciplinaryCase $case,
        UpccAppealAttachment $attachment
    ) {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

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

    public function nfi(DisciplinaryCase $case): Response
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'assignedOfficer',
            'notices' => fn ($q) => $q->where('notice_type', 'notice_of_formative_intervention')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $nfi = $case->notices
            ->where('notice_type', 'notice_of_formative_intervention')
            ->first();

        if (! $nfi) {
            abort(404);
        }

        return $this->notices->stream($nfi, $case);
    }

    public function nteNotice(DisciplinaryCase $case): Response
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'notices' => fn ($q) => $q->where('notice_type', 'notice_to_explain')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $notice = $case->notices
            ->where('notice_type', 'notice_to_explain')
            ->first();

        if (! $notice) {
            abort(404);
        }

        return $this->notices->stream($notice, $case);
    }

    public function hearingNotice(DisciplinaryCase $case): Response
    {
        if ($case->student_id !== auth()->id()) {
            abort(403);
        }

        $case->load([
            'student.studentProfile',
            'confirmedOffense',
            'upccReferral',
            'notices' => fn ($q) => $q->where('notice_type', 'notice_of_hearing')
                ->where('is_voided', false)
                ->with('issuedBy'),
        ]);

        $notice = $case->notices
            ->where('notice_type', 'notice_of_hearing')
            ->first();

        if (! $notice) {
            abort(404);
        }

        return $this->notices->stream($notice, $case, referral: $case->upccReferral);
    }

    public function dashboard(): View
    {
        $studentId = auth()->id();

        $activeUS = UsAssignment::where('student_id', $studentId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->with('caseResolution.case')
            ->get();

        $hold = StudentMonitoringFlag::where('student_id', $studentId)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->first();

        $stats = [
            'active_cases' => DisciplinaryCase::query()
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['resolved', 'dismissed', 'voided'])
                ->count(),
            'active_sanctions' => Sanction::query()
                ->where('student_id', $studentId)
                ->whereIn('status', ['pending', 'in_progress', 'non_compliant'])
                ->count(),
            'complaints' => IncidentReport::query()
                ->where('reported_by_user_id', $studentId)
                ->where('source_type', 'student_complaint')
                ->count(),
            'service_assignments' => $activeUS->count(),
        ];

        $recentCases = DisciplinaryCase::query()
            ->with(['resolution', 'upccReferral'])
            ->where('student_id', $studentId)
            ->where('status', '!=', 'voided')
            ->latest('updated_at')
            ->limit(4)
            ->get();

        return view('student.dashboard', [
            'activeUS' => $activeUS,
            'hold' => $hold,
            'stats' => $stats,
            'recentCases' => $recentCases,
            'currentHandbook' => StudentHandbook::current(),
            'activeNav' => 'dashboard',
        ]);
    }
}
