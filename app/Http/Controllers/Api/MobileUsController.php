<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Services\AttendanceProof;
use App\Services\USAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OTPHP\TOTP;

class MobileUsController extends Controller
{
    public function __construct(
        private readonly USAssignmentService $service
    ) {}

    public function assignments(Request $request): JsonResponse
    {
        $assignments = UsAssignment::with(['student.studentProfile', 'openSession'])
            ->visibleToSupervisor($request->user('api'))
            ->whereIn('status', ['pending', 'in_progress', 'non_compliant', 'completed'])
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'pending' THEN 2 WHEN 'non_compliant' THEN 3 WHEN 'completed' THEN 4 ELSE 5 END")
            ->orderBy('target_completion_date')
            ->get()
            ->map(fn (UsAssignment $assignment) => $this->serializeAssignment($assignment));

        return response()->json([
            'data' => $assignments,
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user('api')->id;

        $logs = UsAttendanceLog::query()
            ->with(['assignment.student.studentProfile', 'student.studentProfile'])
            ->where(fn ($q) => $q->where('logged_by', $userId)->orWhere('verified_by', $userId))
            ->where('log_method', '!=', 'no_show')
            ->orderByDesc('date_of_service')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (UsAttendanceLog $log) {
                $student = $log->student ?? $log->assignment?->student;
                $profile = $student?->studentProfile;

                return [
                    'id' => (string) $log->id,
                    'assignment_id' => (string) $log->us_assignment_id,
                    'student_name' => $student?->list_name ?? 'Unknown student',
                    'student_id' => $profile?->student_number ?? $student?->institutional_id ?? '',
                    'hours_rendered' => (float) ($log->hours_rendered ?? 0),
                    'date_of_service' => optional($log->date_of_service)?->toDateString(),
                    'time_in' => $log->time_in,
                    'time_out' => $log->time_out,
                    'log_method' => $log->log_method,
                    'totpVerified' => $log->totp_verified,
                    'overrideReason' => $log->override_reason,
                    'notes' => $log->notes,
                    'created_at' => optional($log->created_at)?->toIso8601String(),
                ];
            });

        return response()->json(['data' => $logs]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user('api');

        $notifications = $user->notifications()
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'kind' => $notification->data['kind'] ?? $notification->type,
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'actionLabel' => $notification->data['action_label'] ?? null,
                'url' => $notification->data['url'] ?? null,
                'readAt' => optional($notification->read_at)?->toIso8601String(),
                'createdAt' => $notification->created_at->toIso8601String(),
            ]);

        return response()->json([
            'data' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function markNotificationRead(Request $request, DatabaseNotification $notification): JsonResponse
    {
        $user = $request->user('api');

        abort_unless(
            $notification->notifiable_type === $user::class
                && (int) $notification->notifiable_id === (int) $user->id,
            403
        );

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user('api')->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * The full server-side attendance log for an assignment, across every
     * officer/device — unlike history(), which is scoped to the current
     * officer's own scans. Backs the mobile "reject a session" review flow.
     */
    public function assignmentLogs(Request $request, UsAssignment $assignment): JsonResponse
    {
        if (! $assignment->isCoveredBy($request->user('api'))) {
            return $this->rejection('Unauthorized assignment.', 'not_authorized', 403);
        }

        $logs = UsAttendanceLog::query()
            ->where('us_assignment_id', $assignment->id)
            ->with('rejectedBy')
            ->orderByDesc('date_of_service')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UsAttendanceLog $log) => [
                'id' => (string) $log->id,
                'assignment_id' => (string) $log->us_assignment_id,
                'hours_rendered' => (float) ($log->hours_rendered ?? 0),
                'date_of_service' => optional($log->date_of_service)?->toDateString(),
                'time_in' => $log->time_in,
                'time_out' => $log->time_out,
                'log_method' => $log->log_method,
                'totpVerified' => $log->totp_verified,
                'overrideReason' => $log->override_reason,
                'notes' => $log->notes,
                'logged_by_name' => $log->loggedBy?->name,
                'is_rejectable' => $log->isRejectable(),
                'is_rejected' => $log->isRejected(),
                'rejection_reason' => $log->rejection_reason,
                'rejected_at' => optional($log->rejected_at)?->toIso8601String(),
                'rejected_by_name' => $log->rejectedBy?->name,
                'created_at' => optional($log->created_at)?->toIso8601String(),
            ]);

        return response()->json(['data' => $logs]);
    }

    /**
     * Rejects an already-logged, already-credited session after supervisor
     * review — e.g. tasks weren't actually done. Mirrors
     * Supervisor\USAssignmentController::rejectLog() exactly, returning JSON
     * instead of a redirect.
     */
    public function rejectLog(Request $request, UsAssignment $assignment, UsAttendanceLog $log): JsonResponse
    {
        if (! $assignment->isCoveredBy($request->user('api'))) {
            return $this->rejection('Unauthorized assignment.', 'not_authorized', 403);
        }

        // Route-model binding doesn't scope $log to $assignment on its own —
        // without this an officer could reject any log ID by editing the
        // request, as long as they cover the assignment named in it.
        if ($log->us_assignment_id !== $assignment->id) {
            return $this->rejection('Log not found for this assignment.', 'not_found', 404);
        }

        if (! $log->isRejectable()) {
            return $this->rejection('This session can no longer be rejected.', 'not_rejectable', 422);
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $this->service->rejectLog($log, $validated['rejection_reason'], $request->user('api')->id);

        return response()->json([
            'message' => 'Session rejected.',
            'log_id' => $log->id,
            'assignment' => $this->serializeAssignment($assignment->fresh(['student.studentProfile', 'openSession'])),
        ]);
    }

    /**
     * Records a time-in or time-out scan from the mobile companion.
     *
     * The bulk of this method is offline-sync defence rather than the
     * happy path. A queued scan can arrive late, twice, or from a second
     * device working off a stale cache, so it: replays safely via
     * client_log_id (a repeat returns `already_synced` rather than an
     * error), rejects impossible session states with the event type the
     * server would actually accept next, and verifies the TOTP against the
     * device's recorded scan time for offline entries because the 120s
     * window has long rotated by sync time. A failing or missing TOTP is
     * rejected the same way whether the device is online or offline -- an
     * officer regenerating an assignment's QR because it may have leaked
     * needs that to actually lock the old code out, not just the online
     * path; a device with no signal has no way to know a scan won't count
     * until it syncs anyway, so nothing legitimate is lost by rejecting it
     * then instead of accepting-and-flagging it for after-the-fact review.
     */
    public function timeAnchor(Request $request, AttendanceProof $proof): JsonResponse
    {
        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:2048'],
            'nonce' => ['required', 'uuid'],
        ]);

        return response()->json($proof->issue($data['public_key'], (string) $request->user('api')->id, $data['nonce']))
            ->header('Cache-Control', 'no-store');
    }

    public function logSession(Request $request, UsAssignment $assignment): JsonResponse
    {
        if (! $assignment->isCoveredBy($request->user('api'))) {
            return $this->rejection('Unauthorized assignment.', 'not_authorized', 403);
        }
        if (! $request->filled('signed_payload') || ! $request->filled('signature')) {
            return $this->rejection('This attendance record requires verification review. Refresh the app before new scans.', 'attendance_proof_required', 422);
        }
        $input = $request->validate([
            'signed_payload' => ['required', 'string', 'max:16384'],
            'signature' => ['required', 'string', 'max:256'],
        ]);
        try {
            $event = app(AttendanceProof::class)->verify($input['signed_payload'], $input['signature'],
                (string) $request->user('api')->id, (string) $assignment->id);
        } catch (ValidationException $e) {
            return $this->rejection('Attendance signature or scan time is invalid.', 'invalid_attendance_proof', 422);
        }
        if (! isset($event['totp']) || $event['totp'] === '') {
            return $this->rejection('TOTP code is required.', 'totp_required', 422);
        }
        $fields = validator($event, [
            'client_log_id' => ['required', 'uuid'],
            'event_type' => ['required', 'in:time_in,time_out'],
            'totp' => ['required', 'regex:/^[0-9]{6}$/'],
            'tasks_performed' => ['nullable', 'string', 'min:3', 'max:1000'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ])->validate();

        // Serialize on the uploader AND assignment: stable retries, cross-device
        // session ordering and QR reuse are checked in the same transaction.
        return DB::transaction(function () use ($request, $assignment, $event, $fields, $input) {
            $userId = $request->user('api')->id;
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $assignment = UsAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $digest = hash('sha256', $input['signed_payload']);
            $receipt = DB::table('mobile_attendance_receipts')->where('user_id', $userId)
                ->where('client_log_id', $fields['client_log_id'])->first();
            if ($receipt) {
                if (! hash_equals($receipt->payload_hash, $digest)) {
                    return $this->rejection('An event ID was reused with different data.', 'attendance_replay', 409);
                }

                return response()->json(array_merge(json_decode($receipt->response_json, true), [
                    'message' => 'Attendance already synced.',
                    'error_code' => 'already_synced',
                ]));
            }

            $scan = Carbon::createFromTimestampMs($event['captured_at_ms'])->setTimezone(config('app.timezone'));
            $secret = decrypt($assignment->totp_secret);
            $totp = TOTP::createFromSecret($secret);
            $totp->setPeriod(120);
            // Historical current step only: no additional +/-240-second window.
            $step = intdiv($scan->timestamp, 120);
            if (! $totp->verify($fields['totp'], $scan->timestamp)) {
                return $this->rejection('Invalid QR at the recorded scan time.', 'invalid_totp', 422);
            }
            $qrIdentity = hash('sha256', $secret);
            if (DB::table('mobile_attendance_receipts')->where('assignment_id', $assignment->id)
                ->where('qr_identity', $qrIdentity)->where('totp_step', $step)->exists()) {
                return $this->rejection('This QR time window was already used.', 'attendance_replay', 409);
            }

            // All business fields come from authenticated bytes. Ignore unsigned
            // outer fields including offline flags, timestamps and claimed hours.
            $trusted = $request->duplicate();
            $trusted->replace($fields + ['occurred_at' => $scan->toIso8601String(), 'offline' => true]);
            $response = $this->recordVerifiedSession($trusted, $assignment);
            if ($response->getStatusCode() === 201) {
                $body = $response->getData(true);
                DB::table('mobile_attendance_receipts')->insert([
                    'user_id' => $userId, 'assignment_id' => $assignment->id,
                    'client_log_id' => $fields['client_log_id'], 'payload_hash' => $digest,
                    'qr_identity' => $qrIdentity, 'totp_step' => $step, 'log_id' => $body['log_id'],
                    'response_json' => json_encode($body, JSON_THROW_ON_ERROR),
                    'signed_payload' => $input['signed_payload'], 'signature' => $input['signature'],
                    'created_at' => now(),
                ]);
            }

            return $response;
        });
    }

    private function recordVerifiedSession(Request $request, UsAssignment $assignment): JsonResponse
    {
        if (! $assignment->isCoveredBy($request->user('api'))) {
            return $this->rejection('Unauthorized assignment.', 'not_authorized', 403);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'in:time_in,time_out'],
            'occurred_at' => ['required', 'date'],
            'totp' => ['nullable', 'string', 'size:6'],
            'offline' => ['sometimes', 'boolean'],
            'tasks_performed' => ['nullable', 'string', 'min:3', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_log_id' => ['nullable', 'string', 'max:64'],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $eventType = $validated['event_type'];
        $loggedBy = $request->user('api')->id;

        // Check idempotency before any mutable assignment/session-state rule. The
        // original request may have committed successfully while its response was
        // lost (for example, as the mobile client recovered from an expired token).
        // A time-out can complete the assignment, so checking its current status
        // first would turn every retry into assignment_closed and strand the item
        // in the device's pending-sync queue.
        if (! empty($validated['client_log_id'])) {
            $marker = '[client_log_id:'.$validated['client_log_id'].':'.$eventType.']';
            $existing = UsAttendanceLog::query()
                ->where('us_assignment_id', $assignment->id)
                ->where(function ($query) use ($loggedBy) {
                    $query->where('logged_by', $loggedBy)
                        ->orWhere('verified_by', $loggedBy);
                })
                ->where('notes', 'like', '%'.$marker.'%')
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Attendance already synced.',
                    // Not a failure: a retried queue entry that already landed. Carries a
                    // code so the app can drop it from the queue without string-matching.
                    'error_code' => 'already_synced',
                    'eventType' => $eventType,
                    'assignment' => $this->serializeAssignment($assignment->fresh(['student.studentProfile', 'openSession'])),
                    'log_id' => $existing->id,
                ]);
            }
        }

        // A non-compliant assignment missed its deadline and needs an officer's
        // extendDeadline() review before it resumes earning hours — a Time-In
        // starts a *new* session, so it's blocked here. A Time-Out is exempt: a
        // session can still be open from before the deadline lapsed (the officer's
        // markNonCompliant sweep doesn't check for one), and refusing to close it
        // would strand it open forever with no way back in to log the Time-Out.
        if ($eventType === 'time_in' && $assignment->status === 'non_compliant') {
            return $this->rejection(
                'This assignment is non-compliant due to a missed deadline. Ask your discipline officer to extend the deadline before logging further hours.',
                'deadline_non_compliant',
                422,
            );
        }

        $openStatuses = $eventType === 'time_out'
            ? ['pending', 'in_progress', 'non_compliant']
            : ['pending', 'in_progress'];

        if (! in_array($assignment->status, $openStatuses, true)) {
            return $this->rejection('This assignment can no longer accept attendance.', 'assignment_closed', 422);
        }
        $isOffline = (bool) ($validated['offline'] ?? false);
        $occurredAt = Carbon::parse($validated['occurred_at']);

        if ($isOffline && $occurredAt->greaterThan(now()->addMinutes(5))) {
            return $this->rejection(
                'Offline scan time is ahead of the server clock. Check the device time and try again.',
                'invalid_scan_time',
                422,
            );
        }

        $openLog = $assignment->openSession()->first();

        // Two offline devices covering the same office can each queue a scan against a
        // stale local cache and both guess the same event type; whichever syncs second
        // lands here. The code lets the app say "someone else already logged this
        // student" instead of a generic rejection, and expected_event_type tells it
        // which scan the server would actually accept next.
        if ($eventType === 'time_in' && $openLog) {
            return $this->rejection(
                'An open session already exists. Log a time-out before starting a new one.',
                'session_state_conflict',
                409,
                ['expected_event_type' => 'time_out'],
            );
        }

        if ($eventType === 'time_out' && ! $openLog) {
            return $this->rejection(
                'No open time-in session found to close. Scan the time-in QR first.',
                'session_state_conflict',
                409,
                ['expected_event_type' => 'time_in'],
            );
        }

        if ($eventType === 'time_out') {
            $startedAt = $openLog->client_scanned_at
                ?? Carbon::parse($openLog->date_of_service->toDateString().' '.$openLog->time_in);

            if ($occurredAt->lessThanOrEqualTo($startedAt)) {
                return $this->rejection('Time-out must be after the matching time-in.', 'invalid_time_range', 422);
            }

            if ($occurredAt->diffInSeconds($startedAt, true) / 3600 > 12) {
                return $this->rejection(
                    'Session exceeds the maximum allowed duration (12 hours). Please log a time-out sooner or contact your discipline officer.',
                    'session_too_long',
                    422,
                );
            }
        }

        if (empty($validated['totp'])) {
            return $this->rejection('TOTP code is required.', 'totp_required', 422);
        }

        // This method is reached only after the signed time evidence and the
        // historical current TOTP step have been verified by logSession.
        $checkAt = $isOffline ? $occurredAt->timestamp : null;
        $totpWindow = 0;
        $totpVerified = $this->service->validateTotp($assignment, $validated['totp'], $checkAt, $totpWindow);

        if (! $totpVerified) {
            return $this->rejection('Invalid or expired QR code. Ask the student to refresh their QR.', 'invalid_totp', 422);
        }

        // Only authenticated and TOTP-verified events reach session mutation.
        $notes = trim((string) ($validated['notes'] ?? ''));
        if (! empty($validated['client_log_id'])) {
            $notes = trim($notes.' [client_log_id:'.$validated['client_log_id'].':'.$eventType.']');
        }

        $sessionData = [
            'occurred_at' => $occurredAt,
            'tasks_performed' => $validated['tasks_performed'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
            'totp_verified' => $totpVerified,
            'override_reason' => $validated['override_reason'] ?? null,
        ];

        $log = $eventType === 'time_in'
            ? $this->service->openUsSession($assignment, $sessionData, $loggedBy)
            : $this->service->closeUsSession($assignment, $openLog, $sessionData, $loggedBy);

        $fresh = $assignment->fresh(['student.studentProfile', 'openSession']);

        return response()->json([
            'message' => $eventType === 'time_in' ? 'Time-in recorded.' : 'Attendance recorded.',
            'eventType' => $eventType,
            'log_id' => $log->id,
            'hoursRendered' => $log->hours_rendered !== null ? (float) $log->hours_rendered : null,
            'totpVerified' => $totpVerified,
            'assignment' => $this->serializeAssignment($fresh),
        ], 201);
    }

    /**
     * A rejected scan. The message stays as-is for older builds that still match on it;
     * error_code is what new builds should branch on.
     *
     * @param  array<string, mixed>  $extra
     */
    private function rejection(string $message, string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['message' => $message, 'error_code' => $code] + $extra, $status);
    }

    private function serializeAssignment(UsAssignment $assignment): array
    {
        $student = $assignment->student;
        $profile = $student?->studentProfile;

        $current = (float) $assignment->hours_rendered;
        $total = (float) $assignment->total_hours_required;
        $status = match ($assignment->status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            'non_compliant' => 'Non-Compliant',
            default => 'Not Started',
        };

        return [
            'id' => (string) $assignment->id,
            'studentId' => $profile?->student_number ?? $student?->institutional_id ?? '',
            'name' => $student?->list_name ?? 'Unknown student',
            'course' => $profile?->program ?? '',
            'currentHours' => (int) floor($current),
            'currentHoursExact' => $current,
            'totalHours' => (int) ceil($total),
            'totalHoursExact' => $total,
            'dueDate' => optional($assignment->target_completion_date)?->toDateString() ?? '',
            'status' => $status,
            'assignmentStatus' => $assignment->status,
            'officeName' => $assignment->office_name,
            'openSessionStartedAt' => $assignment->openSession?->client_scanned_at?->toIso8601String(),
        ];
    }
}
