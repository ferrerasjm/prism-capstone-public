<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Services\IncidentReportService;
use App\Support\SubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function __construct(private readonly IncidentReportService $service) {}

    public function index(): View
    {
        $complaints = IncidentReport::where('reported_by_user_id', auth()->id())
            ->where('source_type', 'student_complaint')
            ->orderByDesc('created_at')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('student.complaints.index', [
            'complaints' => $complaints,
            'activeNav' => 'complaints-index',
        ]);
    }

    public function show(IncidentReport $complaint): View
    {
        abort_unless((int) $complaint->reported_by_user_id === (int) auth()->id(), 403);
        abort_unless($complaint->source_type === 'student_complaint', 404);

        $complaint->load('attachments');

        return view('student.complaints.show', [
            'complaint' => $complaint,
            'activeNav' => 'complaints-index',
        ]);
    }

    public function create(): View
    {
        return view('student.complaints.create', [
            'activeNav' => 'complaints-create',
        ]);
    }

    public function viewAttachment(IncidentReport $complaint, IncidentAttachment $attachment)
    {
        abort_unless((int) $complaint->reported_by_user_id === (int) auth()->id(), 403);
        abort_unless($complaint->source_type === 'student_complaint', 404);
        abort_unless((int) $attachment->incident_report_id === (int) $complaint->id, 404);

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
        $validated = $request->validate([
            'date_of_incident' => ['required', 'date', 'before_or_equal:today'],
            'time_of_incident' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'disclaimer' => ['required', 'accepted'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'description.min' => 'Please provide a complete description of the incident (at least 50 characters).',
            'disclaimer.accepted' => 'You must acknowledge the disclaimer before submitting.',
        ]);

        // A double-clicked submit on a slow page posts this form twice. The
        // token is spent by the first request, so the second files nothing.
        $guardScope = 'student-complaint:'.auth()->id();
        $submissionToken = $request->input('submission_token');

        if (! SubmissionGuard::claim($guardScope, $submissionToken)) {
            $existing = SubmissionGuard::reference($guardScope, $submissionToken);

            return redirect()
                ->route('student.complaints.index')
                ->with('info', $existing
                    ? "Your complaint ({$existing}) was already submitted — we recorded it only once."
                    : 'Your complaint was already submitted — we recorded it only once.');
        }

        $report = IncidentReport::create([
            'report_number' => $this->service->generateReportNumber(),
            'source_type' => 'student_complaint',
            'reported_by_user_id' => auth()->id(),
            'student_id' => null, // complainant is not necessarily the subject
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
                'source_type' => 'student_complaint',
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        SubmissionGuard::remember($guardScope, $submissionToken, $report->report_number);

        return redirect()
            ->route('student.complaints.index')
            ->with('success', "Your complaint ({$report->report_number}) has been submitted successfully. You may track its status here.");
    }
}
