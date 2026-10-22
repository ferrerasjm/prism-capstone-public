<?php

declare(strict_types=1);

namespace App\Http\Controllers\Officer;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\IncidentAttachment;
use App\Models\IncidentReport;
use App\Models\IncidentReportSubject;
use App\Models\StudentProfile;
use App\Rules\StudentInOfficerDepartment;
use App\Services\IncidentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class IncidentReportController extends Controller
{
    private const INCIDENT_SORTS = [
        'incident_date' => 'desc',
    ];

    public function __construct(private readonly IncidentReportService $service) {}

    public function index(Request $request): View
    {
        [$sort, $direction] = $this->resolveIncidentSort($request);
        $officer = $request->user()->loadMissing(['academicUnit', 'academicUnits']);

        $query = IncidentReport::with(['student.studentProfile', 'reportedByUser'])
            ->forOfficerDepartment($officer)
            ->orderBy('date_of_incident', $direction)
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction);

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->source_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('academic_period_id')) {
            $query->where('academic_period_id', $request->integer('academic_period_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_of_incident', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_of_incident', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('report_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reported_by_name', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->whereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%{$search}%"])
                    );
            });
        }

        $reports = $query->paginate(PerPage::resolve())->withQueryString();

        $raw = IncidentReport::query()
            ->forOfficerDepartment($officer)
            ->when($request->filled('academic_period_id'), fn ($statsQuery) => $statsQuery
                ->where('academic_period_id', $request->integer('academic_period_id')))
            ->selectRaw("
                SUM(status = 'pending_review') as pending,
                SUM(status = 'under_review') as reviewing,
                SUM(status = 'converted_to_case') as converted,
                SUM(status = 'dismissed') as dismissed
            ")
            ->first();

        $stats = [
            'pending' => (int) $raw->pending,
            'reviewing' => (int) $raw->reviewing,
            'converted' => (int) $raw->converted,
            'dismissed' => (int) $raw->dismissed,
        ];

        // Ajax requests (see public/js/live-ux.js [data-ajax-table]) get only the stats/table fragment, not the full page.
        if ($request->ajax()) {
            return view('officer.incidents._results', [
                'reports' => $reports,
                'stats' => $stats,
                'sort' => $sort,
                'direction' => $direction,
            ]);
        }

        return view('officer.incidents.index', [
            'reports' => $reports,
            'stats' => $stats,
            'filters' => $request->only([
                'source_type',
                'status',
                'academic_period_id',
                'date_from',
                'date_to',
                'search',
            ]),
            'assignedDepartments' => $officer->assignedAcademicUnits(),
            'academicPeriods' => AcademicPeriod::query()->orderByDesc('starts_on')->get(),
            'sort' => $sort,
            'direction' => $direction,
            'activeNav' => 'incidents',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function resolveIncidentSort(Request $request): array
    {
        $sort = (string) $request->input('sort');

        if (! array_key_exists($sort, self::INCIDENT_SORTS)) {
            $sort = 'incident_date';
        }

        $direction = (string) $request->input('direction');

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = self::INCIDENT_SORTS[$sort];
        }

        return [$sort, $direction];
    }

    public function searchSuggestions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $officer = $request->user()->loadMissing(['academicUnit', 'academicUnits']);

        if (mb_strlen($search) < 1) {
            return response()->json(['results' => []]);
        }

        $results = IncidentReport::query()
            ->with(['student.studentProfile', 'reportedByUser'])
            ->forOfficerDepartment($officer)
            ->where(function ($query) use ($search): void {
                $query->where('report_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('reported_by_name', 'like', "%{$search}%")
                    ->orWhereHas('reportedByUser', fn ($user) => $user
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('student', fn ($student) => $student
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereHas('studentProfile', fn ($profile) => $profile
                            ->where('student_number', 'like', "%{$search}%")));
            })
            ->orderByDesc('date_of_incident')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(fn (IncidentReport $report) => [
                'report_number' => $report->report_number,
                'student_name' => $report->student?->list_name,
                'reported_by' => $report->reportedByUser?->list_name ?? $report->reported_by_name,
                'description' => mb_strimwidth($report->description, 0, 90, '...'),
            ])
            ->values();

        return response()->json(['results' => $results]);
    }

    public function show(IncidentReport $report): View|RedirectResponse
    {
        $this->ensureVisibleToOfficer($report);

        $report->load([
            'student.studentProfile',
            'reportedByUser',
            'reviewedBy',
            'subjects.student.studentProfile',
            'attachments',
            'cases',
        ]);

        if ($report->status === 'pending_review') {
            $report->update([
                'status' => 'under_review',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'module' => 'incident_reports',
                'record_id' => $report->id,
                'old_values' => ['status' => 'pending_review'],
                'new_values' => ['status' => 'under_review'],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        $reviewTrail = AuditLog::with('user')
            ->where('module', 'incident_reports')
            ->where('record_id', $report->id)
            ->orderByDesc('created_at')
            ->get();

        return view('officer.incidents.show', [
            'report' => $report,
            'reviewTrail' => $reviewTrail,
            'activeNav' => 'incidents',
        ]);
    }

    public function create(): View
    {
        return view('officer.incidents.create', [
            'activeNav' => 'incidents',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['nullable', new StudentInOfficerDepartment($request->user())],
            'date_of_incident' => ['required', 'date', 'before_or_equal:today'],
            'time_of_incident' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'violation_type' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $report = IncidentReport::create([
            'report_number' => $this->service->generateReportNumber(),
            'source_type' => 'direct_encoding',
            'reported_by_user_id' => auth()->id(),
            'student_id' => $validated['student_id'] ?? null,
            'date_of_incident' => $validated['date_of_incident'],
            'time_of_incident' => $validated['time_of_incident'] ?? null,
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'],
            'violation_type' => $validated['violation_type'] ?? null,
            'status' => 'under_review',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments', []) as $file) {
                $this->service->storeAttachment($file, $report, (int) auth()->id());
            }
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'incident_reports',
            'record_id' => $report->id,
            'new_values' => ['report_number' => $report->report_number, 'source_type' => 'direct_encoding'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('officer.incidents.show', $report)
            ->with('success', "Report {$report->report_number} created.");
    }

    public function dismiss(Request $request, IncidentReport $report): RedirectResponse
    {
        $this->ensureVisibleToOfficer($report);

        if (in_array($report->status, ['converted_to_case', 'dismissed'])) {
            return back()->with('error', 'This report cannot be dismissed in its current status.');
        }

        $request->validate([
            'dismissal_reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $oldStatus = $report->status;

        $report->update([
            'status' => 'dismissed',
            'dismissal_reason' => $request->dismissal_reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'incident_reports',
            'record_id' => $report->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'dismissed'],
            'reason' => $request->dismissal_reason,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('officer.incidents.index')
            ->with('success', "Report {$report->report_number} has been dismissed.");
    }

    public function addSubject(Request $request, IncidentReport $report): RedirectResponse
    {
        $this->ensureVisibleToOfficer($report);

        if (in_array($report->status, ['converted_to_case', 'dismissed'], true)) {
            return back()->with('error', 'Cannot add a subject to a converted or dismissed report.');
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer', new StudentInOfficerDepartment($request->user())],
        ]);

        $studentId = (int) $validated['student_id'];

        if ((int) $report->student_id === $studentId) {
            return back()->with('error', 'This student is already the primary subject of the report.');
        }

        $alreadyLinked = IncidentReportSubject::where('incident_report_id', $report->id)
            ->where('student_id', $studentId)
            ->exists();

        if ($alreadyLinked) {
            return back()->with('error', 'This student is already added as a subject.');
        }

        $subject = IncidentReportSubject::create([
            'incident_report_id' => $report->id,
            'student_id' => $studentId,
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'create',
            'module' => 'incident_report_subjects',
            'record_id' => $subject->id,
            'new_values' => [
                'incident_report_id' => $report->id,
                'student_id' => $studentId,
            ],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('officer.incidents.show', $report)
            ->with('success', 'Subject added successfully.');
    }

    public function removeSubject(Request $request, IncidentReport $report, IncidentReportSubject $subject): RedirectResponse
    {
        $this->ensureVisibleToOfficer($report);

        if ((int) $subject->incident_report_id !== (int) $report->id) {
            abort(404);
        }

        if (in_array($report->status, ['converted_to_case', 'dismissed'], true)) {
            return back()->with('error', 'Cannot modify subjects on a converted or dismissed report.');
        }

        $validated = $request->validate([
            'removal_reason' => ['required', 'string', 'min:20', 'max:1000'],
        ]);

        $oldValues = [
            'incident_report_id' => $subject->incident_report_id,
            'student_id' => $subject->student_id,
        ];

        $subject->delete();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'module' => 'incident_report_subjects',
            'record_id' => $subject->id,
            'old_values' => $oldValues,
            'reason' => $validated['removal_reason'],
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);

        return redirect()
            ->route('officer.incidents.show', $report)
            ->with('success', 'Subject removed successfully.');
    }

    public function convertToCase(IncidentReport $report): RedirectResponse
    {
        $this->ensureVisibleToOfficer($report);

        if ($report->status === 'converted_to_case') {
            return back()->with('error', 'This report has already been converted to a case.');
        }

        if ($report->status === 'dismissed') {
            return back()->with('error', 'A dismissed report cannot be converted to a case.');
        }

        $studentIds = collect([$report->student_id])
            ->merge($report->subjects()->pluck('student_id'))
            ->filter()
            ->map(fn ($studentId) => (int) $studentId)
            ->unique();

        $eligibleStudentCount = StudentProfile::query()
            ->forOfficerDepartment(request()->user())
            ->whereIn('user_id', $studentIds)
            ->count();

        if ($studentIds->isNotEmpty() && $eligibleStudentCount !== $studentIds->count()) {
            return back()->with('error', 'This report includes a student outside your assigned department and cannot be converted.');
        }

        try {
            $cases = $this->service->convertToCase($report, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $caseNumbers = collect($cases)->pluck('case_number')->join(', ');

        return redirect()
            ->route('officer.incidents.show', $report)
            ->with('success', "Converted to case(s): {$caseNumbers}. You can now open each case to proceed.");
    }

    public function viewAttachment(IncidentReport $report, IncidentAttachment $attachment)
    {
        $this->ensureVisibleToOfficer($report);

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

    private function ensureVisibleToOfficer(IncidentReport $report): void
    {
        abort_unless($report->isVisibleToOfficer(request()->user()), 403);
    }
}
