<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseResolution;
use App\Models\Sanction;
use App\Models\UsAssignment;
use App\Models\UsAttendanceLog;
use App\Notifications\UniversityServiceStatusNotification;
use App\Notifications\UsSupervisorAssignmentNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OTPHP\TOTP;

/**
 * University Service assignments end to end: creation, time-in/time-out
 * sessions, no-shows, non-compliance and registration holds, overdue
 * flagging, and the rotating TOTP secret behind the attendance QR code
 * (120-second period).
 */
class USAssignmentService
{
    public function createAssignment(
        CaseResolution $resolution,
        array $data,
        int $assignedBy
    ): UsAssignment {
        DB::transaction(function () use ($resolution, $data, $assignedBy, &$assignment) {
            $totp = TOTP::create();
            $totp->setPeriod(120);
            $secret = $totp->getSecret();

            $assignment = UsAssignment::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $resolution->case->student_id,
                'supervisor_id' => $data['supervisor_id'] ?? null,
                'contact_person_name' => $data['contact_person_name'] ?? null,
                'contact_person_email' => $data['contact_person_email'] ?? null,
                'assigned_by' => $assignedBy,
                'office_name' => $data['office_name'],
                'total_hours_required' => $resolution->us_hours_assigned,
                'hours_rendered' => 0,
                'start_date' => $data['start_date'],
                'target_completion_date' => $data['target_completion_date'],
                'status' => 'pending',
                'totp_secret' => encrypt($secret),
                'totp_secret_generated_at' => now(),
            ]);

            Sanction::where('case_resolution_id', $resolution->id)
                ->where('sanction_type', 'university_service')
                ->update(['status' => 'pending', 'updated_at' => now()]);

            AuditLog::create([
                'user_id' => $assignedBy,
                'action' => 'create',
                'module' => 'us_assignments',
                'record_id' => $assignment->id,
                'new_values' => [
                    'student_id' => $assignment->student_id,
                    'supervisor_id' => $assignment->supervisor_id,
                    'contact_person_name' => $assignment->contact_person_name,
                    'contact_person_email' => $assignment->contact_person_email,
                    'office_name' => $assignment->office_name,
                    'total_hours_required' => $assignment->total_hours_required,
                    'target_completion_date' => $assignment->target_completion_date->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $assignment->student?->notify(
                new UniversityServiceStatusNotification($assignment, 'assigned')
            );

            $assignment->loadMissing(['student', 'supervisor']);
            $assignment->supervisor?->notify(
                new UsSupervisorAssignmentNotification($assignment)
            );
        });

        return $assignment;
    }

    public function logSession(
        UsAssignment $assignment,
        array $data,
        int $loggedBy
    ): UsAttendanceLog {
        $wasNonCompliant = $assignment->status === 'non_compliant';

        DB::transaction(function () use ($assignment, $data, $loggedBy, &$log) {
            $log = UsAttendanceLog::create([
                'us_assignment_id' => $assignment->id,
                'student_id' => $assignment->student_id,
                'date_of_service' => $data['date_of_service'],
                'time_in' => $data['time_in'] ?? null,
                'time_out' => $data['time_out'] ?? null,
                'hours_rendered' => $data['hours_rendered'],
                'tasks_performed' => $data['tasks_performed'] ?? null,
                'log_method' => $data['log_method'] ?? 'manual_web',
                'totp_verified' => $data['totp_verified'] ?? null,
                'client_scanned_at' => $data['client_scanned_at'] ?? null,
                'override_reason' => $data['override_reason'] ?? null,
                'logged_by' => $loggedBy,
                'verified_by' => $loggedBy,
                'verified_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->finalizeAttendanceLog($assignment, $log, $loggedBy);
        });

        $this->recomputeRiskIfRestored($assignment, $wasNonCompliant, $loggedBy);

        return $log;
    }

    /**
     * Open a new QR-scan session ("time in"). Hours aren't known yet, so this
     * doesn't touch assignment totals/status — that happens on close.
     */
    public function openUsSession(
        UsAssignment $assignment,
        array $data,
        int $loggedBy
    ): UsAttendanceLog {
        /** @var Carbon $occurredAt */
        $occurredAt = $data['occurred_at'];

        return UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => $occurredAt->toDateString(),
            'time_in' => $this->roundToMinute($occurredAt)->format('H:i'),
            'time_out' => null,
            'hours_rendered' => null,
            'tasks_performed' => $data['tasks_performed'] ?? null,
            'log_method' => 'qr_scan',
            'totp_verified' => $data['totp_verified'] ?? false,
            'client_scanned_at' => $occurredAt,
            'override_reason' => $data['override_reason'] ?? null,
            'logged_by' => $loggedBy,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Close an open QR-scan session ("time out"), computing the rendered
     * hours from the matched time-in/time-out instants rather than trusting
     * a client-supplied duration.
     */
    public function closeUsSession(
        UsAssignment $assignment,
        UsAttendanceLog $openLog,
        array $data,
        int $loggedBy
    ): UsAttendanceLog {
        $wasNonCompliant = $assignment->status === 'non_compliant';

        $startedAt = $openLog->client_scanned_at
            ?? Carbon::parse($openLog->date_of_service->toDateString().' '.$openLog->time_in);

        /** @var Carbon $endedAt */
        $endedAt = $data['occurred_at'];

        // Round the true elapsed duration once, then anchor the displayed Time Out to
        // Time In + that duration -- rounding each raw endpoint independently (as an
        // earlier version of this did) can drift the total by up to a minute versus
        // what actually elapsed, since the two roundings don't cancel out.
        $totalMinutes = (int) round($endedAt->diffInSeconds($startedAt, true) / 60);
        $roundedEnd = $this->roundToMinute($startedAt)->addMinutes($totalMinutes);
        $hours = round($totalMinutes / 60, 2);

        DB::transaction(function () use ($assignment, $openLog, $data, $loggedBy, $roundedEnd, $hours, &$log) {
            $openLog->update([
                'time_out' => $roundedEnd->format('H:i'),
                'hours_rendered' => $hours,
                'totp_verified' => $openLog->totp_verified && ($data['totp_verified'] ?? false),
                'tasks_performed' => $data['tasks_performed'] ?? $openLog->tasks_performed,
                'notes' => $data['notes'] ?? $openLog->notes,
                'override_reason' => $data['override_reason'] ?? $openLog->override_reason,
                'verified_by' => $loggedBy,
                'verified_at' => now(),
            ]);

            $log = $openLog->fresh();

            $this->finalizeAttendanceLog($assignment, $log, $loggedBy);
        });

        $this->recomputeRiskIfRestored($assignment, $wasNonCompliant, $loggedBy);

        return $log;
    }

    /** Round-half-up to the nearest minute, so a scan a few seconds past :30 doesn't get floored away. */
    private function roundToMinute(Carbon $time): Carbon
    {
        return $time->copy()->addSeconds(30)->startOfMinute();
    }

    /**
     * Recomputes an assignment's rendered-hours total from its non-rejected,
     * non-no-show logs and derives status from it, rolling both onto the
     * assignment and its linked Sanction. Bidirectional — unlike a simple
     * "only escalate" rule, this also handles hours going *down* (a
     * rejected session, see rejectLog), so status can move back from
     * completed/in_progress to pending when the hours behind it are
     * retracted. 'non_compliant' is preserved rather than silently cleared
     * by the math — that status reflects a deadline issue, lifted only via
     * the deliberate restore path (recomputeRiskIfRestored /
     * RegistrationHoldService::liftIfClear). Must run inside the caller's
     * DB::transaction().
     *
     * @return array{old: string, new: string, total: float}
     */
    private function recomputeAssignmentTotals(UsAssignment $assignment): array
    {
        $totalRendered = UsAttendanceLog::where('us_assignment_id', $assignment->id)
            ->whereNotNull('hours_rendered')
            ->where('log_method', '!=', 'no_show')
            ->whereNull('rejected_at')
            ->sum('hours_rendered');

        $oldStatus = $assignment->status;
        $newStatus = match (true) {
            (float) $totalRendered >= (float) $assignment->total_hours_required => 'completed',
            $oldStatus === 'non_compliant' => 'non_compliant',
            (float) $totalRendered > 0 => 'in_progress',
            default => 'pending',
        };

        $assignment->update([
            'hours_rendered' => $totalRendered,
            'status' => $newStatus,
        ]);

        Sanction::where('case_resolution_id', $assignment->case_resolution_id)
            ->where('sanction_type', 'university_service')
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        return ['old' => $oldStatus, 'new' => $newStatus, 'total' => (float) $totalRendered];
    }

    /**
     * Shared "a session's hours are now known" bookkeeping: recompute
     * totals, notify on completion, and audit-log the outcome. Must run
     * inside the caller's DB::transaction().
     */
    private function finalizeAttendanceLog(UsAssignment $assignment, UsAttendanceLog $log, int $loggedBy): void
    {
        ['old' => $oldStatus, 'new' => $newStatus, 'total' => $totalRendered] =
            $this->recomputeAssignmentTotals($assignment);

        if ($newStatus === 'completed') {
            Sanction::where('case_resolution_id', $assignment->case_resolution_id)
                ->where('sanction_type', 'university_service')
                ->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'verified_by' => $loggedBy,
                    'updated_at' => now(),
                ]);

            if ($oldStatus !== 'completed') {
                $assignment->student?->notify(
                    new UniversityServiceStatusNotification($assignment->fresh(), 'completed')
                );
            }
        }

        // Re-check the hold whenever the sanction isn't (or is no longer) non-compliant —
        // covers both full completion and a partial catch-up that only reaches
        // in_progress, so a hold never outlives the compliance issue that raised it.
        // liftIfClear() is a no-op unless another obligation is still non-compliant,
        // so this is safe to call even when nothing was ever flagged.
        if ($newStatus !== 'non_compliant') {
            app(RegistrationHoldService::class)->liftIfClear($assignment->student_id);
        }

        AuditLog::create([
            'user_id' => $loggedBy,
            'action' => 'create',
            'module' => 'us_attendance_logs',
            'record_id' => $log->id,
            'new_values' => [
                'hours_rendered' => $log->hours_rendered,
                'date_of_service' => $log->date_of_service?->toDateString(),
                'log_method' => $log->log_method,
                'new_total_rendered' => $totalRendered,
                'assignment_status' => $newStatus,
            ],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Void a completed session a supervisor has reviewed and found invalid —
     * the tasks weren't actually performed, or nothing happened during the
     * logged time-in/time-out window. The log itself is kept (audit trail,
     * see UsAttendanceLog::isRejected); only its hours stop counting toward
     * the assignment. Can retract a 'completed' assignment back to
     * 'in_progress'/'pending' if the rejected session was what pushed it
     * over the required total — recomputeAssignmentTotals() handles that.
     */
    public function rejectLog(
        UsAttendanceLog $log,
        string $reason,
        int $rejectedBy
    ): void {
        if (! $log->isRejectable()) {
            throw new \RuntimeException('This session cannot be rejected.');
        }

        $assignment = $log->assignment;

        DB::transaction(function () use ($assignment, $log, $reason, $rejectedBy) {
            $log->update([
                'rejected_at' => now(),
                'rejected_by' => $rejectedBy,
                'rejection_reason' => $reason,
            ]);

            ['old' => $oldStatus, 'new' => $newStatus, 'total' => $totalRendered] =
                $this->recomputeAssignmentTotals($assignment);

            // Rejection only ever removes hours, so it can never newly
            // trigger non-compliance on its own — but it can retract a
            // completed/in-progress status, and any deadline consequence
            // of that surfaces on its own through the existing overdue job
            // (flagOverdueAssignments), not something to duplicate here.
            if ($newStatus !== 'non_compliant') {
                app(RegistrationHoldService::class)->liftIfClear($assignment->student_id);
            }

            $assignment->student?->notify(
                new UniversityServiceStatusNotification($assignment->fresh(), 'session_rejected', $reason)
            );

            AuditLog::create([
                'user_id' => $rejectedBy,
                'action' => 'update',
                'module' => 'us_attendance_logs',
                'record_id' => $log->id,
                'old_values' => ['assignment_status' => $oldStatus],
                'new_values' => [
                    'rejected' => true,
                    'new_total_rendered' => $totalRendered,
                    'assignment_status' => $newStatus,
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    private function recomputeRiskIfRestored(UsAssignment $assignment, bool $wasNonCompliant, int $loggedBy): void
    {
        if ($wasNonCompliant && $assignment->fresh()->status !== 'non_compliant') {
            app(RiskScoringService::class)->computeForStudent(
                $assignment->student_id,
                'sanction_compliance_restored',
                $loggedBy
            );
        }
    }

    public function recordNoShow(
        UsAssignment $assignment,
        string $date,
        string $notes,
        int $loggedBy
    ): UsAttendanceLog {
        $log = UsAttendanceLog::create([
            'us_assignment_id' => $assignment->id,
            'student_id' => $assignment->student_id,
            'date_of_service' => $date,
            'time_in' => null,
            'time_out' => null,
            'hours_rendered' => 0,
            'tasks_performed' => null,
            'log_method' => 'no_show',
            'logged_by' => $loggedBy,
            'notes' => $notes,
        ]);

        AuditLog::create([
            'user_id' => $loggedBy,
            'action' => 'create',
            'module' => 'us_attendance_logs',
            'record_id' => $log->id,
            'new_values' => ['log_method' => 'no_show', 'date' => $date],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return $log;
    }

    public function markNonCompliant(
        UsAssignment $assignment,
        string $reason,
        int $markedBy
    ): void {
        if ($assignment->status === 'completed') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Completed University Service cannot be marked non-compliant.',
            ]);
        }
        if ($assignment->status === 'non_compliant') {
            return;
        }

        DB::transaction(function () use ($assignment, $reason, $markedBy) {
            $oldStatus = $assignment->status;
            $assignment->update(['status' => 'non_compliant']);

            Sanction::where('case_resolution_id', $assignment->case_resolution_id)
                ->where('sanction_type', 'university_service')
                ->update([
                    'status' => 'non_compliant',
                    'notes' => $reason,
                    'updated_at' => now(),
                ]);

            app(RegistrationHoldService::class)->raise($assignment->student_id, $reason, $markedBy);

            AuditLog::create([
                'user_id' => $markedBy,
                'action' => 'update',
                'module' => 'us_assignments',
                'record_id' => $assignment->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'non_compliant'],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            app(RiskScoringService::class)
                ->computeForStudent(
                    $assignment->student_id,
                    'sanction_non_compliance',
                    $markedBy
                );

            $assignment->student?->notify(
                new UniversityServiceStatusNotification($assignment->fresh(), 'non_compliant')
            );
        });
    }

    /**
     * Push the target completion date out, e.g. when the receiving office was
     * unavailable through no fault of the student. If the assignment had
     * already been flagged non-compliant, this restores it to the status its
     * hours actually reflect. The registration hold is lifted only when no
     * other sanction still requires it.
     */
    public function extendDeadline(
        UsAssignment $assignment,
        string $newTargetDate,
        string $reason,
        int $extendedBy
    ): void {
        if ($assignment->status === 'completed') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => 'Completed University Service cannot be extended.',
            ]);
        }
        validator(['target_completion_date' => $newTargetDate], [
            'target_completion_date' => ['required', 'date', 'after:'.$assignment->target_completion_date->toDateString(), 'after_or_equal:today'],
        ])->validate();
        $wasNonCompliant = $assignment->status === 'non_compliant';

        DB::transaction(function () use ($assignment, $newTargetDate, $reason, $extendedBy, $wasNonCompliant) {
            $oldDate = $assignment->target_completion_date->toDateString();
            $newStatus = $wasNonCompliant
                ? ((float) $assignment->hours_rendered > 0 ? 'in_progress' : 'pending')
                : $assignment->status;

            $assignment->update([
                'target_completion_date' => $newTargetDate,
                'deadline_review_flagged_at' => null,
                'student_deadline_reminded_at' => null,
                'student_deadline_missed_notified_at' => null,
                'status' => $newStatus,
            ]);

            if ($wasNonCompliant) {
                Sanction::where('case_resolution_id', $assignment->case_resolution_id)
                    ->where('sanction_type', 'university_service')
                    ->update([
                        'status' => $newStatus,
                        'updated_at' => now(),
                    ]);

                app(RegistrationHoldService::class)->liftIfClear($assignment->student_id);
            }

            AuditLog::create([
                'user_id' => $extendedBy,
                'action' => 'update',
                'module' => 'us_assignments',
                'record_id' => $assignment->id,
                'old_values' => [
                    'target_completion_date' => $oldDate,
                    'status' => $wasNonCompliant ? 'non_compliant' : $newStatus,
                ],
                'new_values' => [
                    'target_completion_date' => $newTargetDate,
                    'status' => $newStatus,
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $assignment->student?->notify(
                new UniversityServiceStatusNotification($assignment->fresh(), 'extended')
            );
        });

        $this->recomputeRiskIfRestored($assignment, $wasNonCompliant, $extendedBy);
    }

    /** @deprecated Kept for backward compatibility; delegates to RegistrationHoldService. */
    public function checkAndLiftHold(int $studentId): void
    {
        app(RegistrationHoldService::class)->liftIfClear($studentId);
    }

    public function generateTotpUri(UsAssignment $assignment): string
    {
        $secret = decrypt($assignment->totp_secret);
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(120);

        $currentOtp = $totp->now();

        return "PRISM_US:{$assignment->id}:{$assignment->student_id}:{$currentOtp}";
    }

    /**
     * @param  int|null  $atTimestamp  Unix timestamp the code should be checked against.
     *                                 Pass the client's recorded scan time for deferred
     *                                 (offline) syncs instead of the upload time, since the
     *                                 120s window will otherwise have long since rotated past.
     * @param  int  $window  Number of adjacent 120-second TOTP periods accepted on either side.
     */
    public function validateTotp(
        UsAssignment $assignment,
        string $code,
        ?int $atTimestamp = null,
        int $window = 0
    ): bool
    {
        $secret = decrypt($assignment->totp_secret);
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(120);

        $timestamp = $atTimestamp ?? now()->timestamp;
        $window = max(0, $window);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if ($timestamp + ($offset * 120) < 0) {
                continue;
            }

            if ($totp->verify($code, $timestamp + ($offset * 120))) {
                return true;
            }
        }

        return false;
    }

    public function sendStudentDeadlineNotifications(): int
    {
        $sent = 0;
        UsAssignment::whereIn('status', ['pending', 'in_progress'])
            ->whereDate('target_completion_date', '<=', today()->addDay())
            ->each(function (UsAssignment $assignment) use (&$sent) {
                DB::transaction(function () use ($assignment, &$sent) {
                    $assignment = UsAssignment::lockForUpdate()->findOrFail($assignment->id);
                    if (! in_array($assignment->status, ['pending', 'in_progress'], true)
                        || ! $assignment->student) {
                        return;
                    }

                    if ($assignment->isOverdue()) {
                        $field = 'student_deadline_missed_notified_at';
                        $event = 'deadline_missed';
                    } elseif ($assignment->target_completion_date->isSameDay(today()->addDay())) {
                        $field = 'student_deadline_reminded_at';
                        $event = 'deadline_reminder';
                    } else {
                        return;
                    }

                    if ($assignment->{$field}) {
                        return;
                    }
                    $assignment->student->notify(new UniversityServiceStatusNotification($assignment, $event));
                    $assignment->update([$field => now()]);
                    $sent++;
                });
            });

        return $sent;
    }

    public function flagOverdueAssignments(): int
    {
        $flagged = 0;

        UsAssignment::whereIn('status', ['in_progress', 'pending'])
            ->whereDate('target_completion_date', '<', now()->toDateString())
            ->whereNull('deadline_review_flagged_at')
            ->each(function (UsAssignment $assignment) use (&$flagged) {
                DB::transaction(function () use ($assignment, &$flagged) {
                    $assignment = UsAssignment::lockForUpdate()->findOrFail($assignment->id);
                    if ($assignment->deadline_review_flagged_at
                        || ! in_array($assignment->status, ['pending', 'in_progress'], true)
                        || ! $assignment->isOverdue()) {
                        return;
                    }

                    $officer = $assignment->caseResolution?->case?->assignedOfficer
                        ?? $assignment->assignedBy;
                    if (! $officer) {
                        return;
                    }

                    $officer->notify(new \App\Notifications\UsDeadlineReviewNotification($assignment));
                    $assignment->update(['deadline_review_flagged_at' => now()]);
                    $flagged++;
                });
            });

        return $flagged;
    }

    public function regenerateTotpSecret(
        UsAssignment $assignment,
        int $regeneratedBy
    ): void {
        $totp = TOTP::create();
        $totp->setPeriod(120);
        $newSecret = $totp->getSecret();

        $old = $assignment->totp_secret_generated_at;

        $assignment->update([
            'totp_secret' => encrypt($newSecret),
            'totp_secret_generated_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $regeneratedBy,
            'action' => 'update',
            'module' => 'us_assignments',
            'record_id' => $assignment->id,
            'old_values' => [
                'totp_secret_generated_at' => $old?->toDateTimeString(),
            ],
            'new_values' => [
                'totp_secret_generated_at' => now()->toDateTimeString(),
                'reason' => 'QR secret regenerated by staff',
            ],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
