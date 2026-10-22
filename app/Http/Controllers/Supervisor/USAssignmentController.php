<?php

declare(strict_types=1);

namespace App\Http\Controllers\Supervisor;

use App\Support\PerPage;
use App\Http\Controllers\Controller;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Services\USAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class USAssignmentController extends Controller
{
    public function __construct(
        private readonly USAssignmentService $service
    ) {}

    public function index(): View
    {
        $assignments = UsAssignment::with(['student.studentProfile'])
            ->visibleToSupervisor(auth()->user())
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('target_completion_date')
            ->paginate(PerPage::resolve())
            ->withQueryString();

        return view('supervisor.us.index', [
            'assignments' => $assignments,
            'activeNav' => 'assignments',
        ]);
    }

    public function show(UsAssignment $assignment): View
    {
        if (! $assignment->isCoveredBy(auth()->user())) {
            abort(403);
        }

        $assignment->load([
            'student.studentProfile',
            'attendanceLogs.loggedBy',
            'attendanceLogs.verifiedBy',
            'attendanceLogs.rejectedBy',
        ]);

        return view('supervisor.us.show', [
            'assignment' => $assignment,
            'activeNav' => 'assignments',
        ]);
    }

    public function logSession(Request $request, UsAssignment $assignment): RedirectResponse
    {
        if (! $assignment->isCoveredBy(auth()->user())) {
            abort(403);
        }

        if ($assignment->status === 'non_compliant') {
            throw ValidationException::withMessages([
                'status' => 'This assignment is non-compliant due to a missed deadline. Extend the deadline before logging further hours.',
            ]);
        }

        if (! in_array($assignment->status, ['pending', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This assignment can no longer accept attendance.',
            ]);
        }

        $validated = $request->validate([
            'date_of_service' => ['required', 'date', 'before_or_equal:today'],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i', 'after:time_in'],
            'duration_hours' => ['required', 'integer', 'min:0', 'max:12'],
            'duration_minutes' => ['required', 'integer', 'min:0', 'max:59'],
            'tasks_performed' => ['required', 'string', 'min:20', 'max:2000'],
            'notes' => ['nullable', 'string'],
        ]);

        $totalMinutes = ((int) $validated['duration_hours'] * 60) + (int) $validated['duration_minutes'];
        if ($totalMinutes < 15 || $totalMinutes > 720) {
            throw ValidationException::withMessages([
                'duration_minutes' => 'The session duration must be between 15 minutes and 12 hours.',
            ]);
        }

        $validated['log_method'] = 'manual_web';
        $validated['hours_rendered'] = round($totalMinutes / 60, 2);
        unset($validated['duration_hours'], $validated['duration_minutes']);

        $this->service->logSession($assignment, $validated, auth()->id());

        return redirect()->route('supervisor.us.show', $assignment)
            ->with('success', 'Session logged successfully.');
    }

    public function recordNoShow(Request $request, UsAssignment $assignment): RedirectResponse
    {
        if (! $assignment->isCoveredBy(auth()->user())) {
            abort(403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->service->recordNoShow(
            $assignment,
            $validated['date'],
            $validated['notes'],
            auth()->id()
        );

        return redirect()->route('supervisor.us.show', $assignment)
            ->with('success', 'No-show recorded.');
    }

    public function rejectLog(Request $request, UsAssignment $assignment, UsAttendanceLog $log): RedirectResponse
    {
        if (! $assignment->isCoveredBy(auth()->user())) {
            abort(403);
        }

        // Route-model binding doesn't scope $log to $assignment on its own —
        // without this a supervisor could reject any log ID by editing the
        // URL, as long as they cover the assignment named in it.
        if ($log->us_assignment_id !== $assignment->id) {
            abort(404);
        }

        if (! $log->isRejectable()) {
            return redirect()->route('supervisor.us.show', $assignment)
                ->with('error', 'This session can no longer be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->service->rejectLog($log, $validated['rejection_reason'], auth()->id());

        return redirect()->route('supervisor.us.show', $assignment)
            ->with('success', 'Session rejected — it no longer counts toward the student\'s hours.');
    }
}
