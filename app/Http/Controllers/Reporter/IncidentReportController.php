<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reporter;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinaryCase;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Models\StudentProfile;
use App\Models\UpccAppealAttachment;
use App\Services\CoordinatorCaseService;
use App\Services\IncidentReportService;
use App\Support\SubmissionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IncidentReportController extends Controller
{
    public function __construct(private readonly IncidentReportService $service) {}

    public function dashboard(): View
    {
        $reports = IncidentReport::query()
            ->where('reported_by_user_id', auth()->id());

        $stats = [
            'total' => (clone $reports)->count(),
            'pending' => (clone $reports)->where('status', 'pending_review')->count(),
            'under_review' => (clone $reports)->where('status', 'under_review')->count(),
            'processed' => (clone $reports)->where('status', 'converted_to_case')->count(),
        ];

        $recentlyProcessed = (clone $reports)
            ->where('status', 'converted_to_case')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('reporter.dashboard', [
            'stats' => $stats,
            'recentlyProcessed' => $recentlyProcessed,
            'activeNav' => 'dashboard',
        ]);
    }

    public function index(): View
    {
        $reports = IncidentReport::query()
            ->where('reported_by_user_id', auth()->id())
            ->latest()
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('reporter.reports.index', [
            'reports' => $reports,
            'activeNav' => 'reports',
        ]);
    }

    public function create(): View
    {
        $user = auth()->user();

        return view('reporter.reports.create', [
            'activeNav' => 'submit-report',
            'sourceLabel' => $user->reportingSourceLabel(),
            'sourceType' => $user->reportingSourceType(),
            'reportingAffiliation' => $user->reportingAffiliationLabel(),
        ]);
    }

    public function show(IncidentReport $report): View
    {
        abort_unless((int) $report->reported_by_user_id === (int) auth()->id(), 403);

        $report->load([
            'attachments',
            'student.studentProfile',
            'cases.student',
            'cases.upccReferral.appealAttachments',
        ]);

        return view('reporter.reports.show', [
            'report' => $report,
            'activeNav' => 'reports',
        ]);
    }

    public function submitAppeal(
        Request $request,
        DisciplinaryCase $case,
        CoordinatorCaseService $service
    ): RedirectResponse {
        $report = $case->incidentReport;

        if (! $report || (int) $report->reported_by_user_id !== (int) auth()->id()) {
            abort(403);
        }

        $case->load(['incidentReport', 'upccReferral.referredBy']);
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
            'reporting_user_portal'
        );

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments', []) as $file) {
                if ($file instanceof UploadedFile) {
                    $service->storeAppealAttachment($file, $referral, (int) auth()->id());
                }
            }
        }

        $freshReferral = $referral->fresh(['case.incidentReport', 'referredBy', 'appealAttachments', 'appealFiledBy']);
        if ($freshReferral) {
            $service->notifyCoordinatorsOfAppeal($freshReferral);
        }

        return redirect()->route('reporter.reports.show', $report)
            ->with('success', 'Your appeal has been submitted. The Discipline Coordinator will review it for reconsideration.');
    }

    public function viewCaseAppealAttachment(
        DisciplinaryCase $case,
        UpccAppealAttachment $attachment
    ) {
        $report = $case->incidentReport;

        if (! $report || (int) $report->reported_by_user_id !== (int) auth()->id()) {
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

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'date_of_incident' => ['required', 'date', 'before_or_equal:today'],
            'time_of_incident' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'disclaimer' => ['required', 'accepted'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'description.min' => 'Please provide a detailed description (at least 30 characters).',
            'disclaimer.accepted' => 'You must acknowledge the disclaimer to submit this report.',
            'attachments.max' => 'You may upload a maximum of 3 attachments.',
            'attachments.*.mimes' => 'Attachments must be PDF, JPG, or PNG files.',
            'attachments.*.max' => 'Each attachment must not exceed 5MB.',
        ]);

        // A double-clicked submit on a slow page posts this form twice. The
        // token is spent by the first request, so the second files nothing.
        $guardScope = 'incident-report:'.$user->id;
        $submissionToken = $request->input('submission_token');

        if (! SubmissionGuard::claim($guardScope, $submissionToken)) {
            $existing = SubmissionGuard::reference($guardScope, $submissionToken);

            return redirect()
                ->route('reporter.reports.index')
                ->with('info', $existing
                    ? "Your report ({$existing}) was already submitted — we recorded it only once."
                    : 'Your report was already submitted — we recorded it only once.');
        }

        $report = IncidentReport::create([
            'report_number' => $this->service->generateReportNumber(),
            'source_type' => $user->reportingSourceType(),
            'reported_by_user_id' => $user->id,
            'reported_by_name' => $user->full_name,
            'student_id' => $validated['student_id'] ?? null,
            'date_of_incident' => $validated['date_of_incident'],
            'time_of_incident' => $validated['time_of_incident'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'],
            'status' => 'pending_review',
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $this->service->storeAttachment($file, $report, auth()->id());
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'incident_reports',
            'record_id' => $report->id,
            'new_values' => [
                'report_number' => $report->report_number,
                'source_type' => $report->source_type,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        SubmissionGuard::remember($guardScope, $submissionToken, $report->report_number);

        return redirect()
            ->route('reporter.reports.index')
            ->with('success', "Your report ({$report->report_number}) has been submitted and is pending review.");
    }

    public function studentLookup(Request $request): JsonResponse
    {
        $term = trim((string) ($request->query('student_number') ?: $request->query('q', '')));

        if (mb_strlen($term) < 1) {
            return response()->json(['results' => []]);
        }

        $profiles = StudentProfile::query()
            ->with('user')
            ->where(function ($query) use ($term) {
                $query->where('student_number', 'like', "%{$term}%")
                    ->orWhereHas('user', function ($userQuery) use ($term) {
                        $userQuery
                            ->where('first_name', 'like', "%{$term}%")
                            ->orWhere('last_name', 'like', "%{$term}%")
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"]);
                    });
            })
            ->whereHas('user', fn ($u) => $u->where('status', '!=', 'inactive'))
            ->limit(8)
            ->get();

        $results = $profiles
            ->map(function ($profile) {
                $user = $profile->user;
                if (! $user) {
                    return null;
                }

                return [
                    'user_id' => $user->id,
                    'full_name' => $user->list_name,
                    'student_number' => $profile->student_number,
                    'program' => $profile->program ?? 'Program not set',
                    'year_level' => $profile->year_level ?? '',
                    'status' => $user->status,
                    'initials' => strtoupper(
                        substr($user->first_name, 0, 1).substr($user->last_name, 0, 1)
                    ),
                ];
            })
            ->filter()
            ->values();

        return response()->json(['results' => $results]);
    }

    public function viewAttachment(IncidentReport $report, IncidentAttachment $attachment)
    {
        abort_unless((int) $report->reported_by_user_id === (int) auth()->id(), 403);

        if ((int) $attachment->incident_report_id !== (int) $report->id) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($attachment->file_path)) {
            abort(404, 'Attachment file not found.');
        }

        $absolutePath = Storage::disk('local')->path($attachment->file_path);
        $mimeType = Storage::disk('local')->mimeType($attachment->file_path) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$attachment->file_name.'"',
        ]);
    }
}
