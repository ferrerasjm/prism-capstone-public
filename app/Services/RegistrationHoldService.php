<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Sanction;
use App\Models\StudentMonitoringFlag;

/**
 * Single source of truth for the `registration_hold` monitoring flag, raised
 * whenever a student cannot enroll for the next term (University Service
 * non-compliance, or a Non-Readmission/Exclusion/Expulsion sanction) and
 * consumed by the Registrar-facing Registration Hold report.
 */
class RegistrationHoldService
{
    public function raise(int $studentId, string $reason, ?int $raisedBy): void
    {
        $exists = StudentMonitoringFlag::where('student_id', $studentId)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            return;
        }

        $hold = StudentMonitoringFlag::create([
            'student_id' => $studentId,
            'flagged_by' => $raisedBy,
            'flag_type' => 'registration_hold',
            'flag_reason' => $reason,
            'is_active' => true,
            'created_at' => now(),
        ]);

        $hold->student?->notify(new \App\Notifications\RegistrationHoldNotification);
    }

    /**
     * System-driven lift: only clears the hold once no sanction is non-compliant
     * and no unwaived enrollment restriction remains. Safe on every status change,
     * not just on full completion — it is a no-op if compliance issues remain.
     */
    public function liftIfClear(int $studentId): void
    {
        $hasActiveObligations = Sanction::where('student_id', $studentId)
            ->where(function ($query) {
                $query->where('status', 'non_compliant')
                    ->orWhere(function ($query) {
                        $query->whereIn('sanction_type', ['non_readmission', 'exclusion', 'expulsion'])
                            ->where('status', '!=', 'waived');
                    });
            })
            ->exists();

        if ($hasActiveObligations) {
            return;
        }

        $this->deactivate($studentId, null, null);
    }

    /**
     * Officer-driven override: lifts the hold regardless of the underlying
     * sanction status (e.g. a waiver, a negotiated resolution, a data-entry
     * correction). Requires a reason and is audit-logged.
     */
    public function liftManually(int $studentId, string $reason, int $liftedBy): void
    {
        $this->deactivate($studentId, $reason, $liftedBy);
    }

    private function deactivate(int $studentId, ?string $reason, ?int $liftedBy): void
    {
        $hold = StudentMonitoringFlag::where('student_id', $studentId)
            ->where('flag_type', 'registration_hold')
            ->where('is_active', true)
            ->first();

        if (! $hold) {
            return;
        }

        $hold->update([
            'is_active' => false,
            'deactivated_by' => $liftedBy,
            'deactivated_at' => now(),
        ]);

        if ($liftedBy) {
            AuditLog::create([
                'user_id' => $liftedBy,
                'action' => 'update',
                'module' => 'student_monitoring_flags',
                'record_id' => $hold->id,
                'old_values' => ['is_active' => true],
                'new_values' => ['is_active' => false],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }
    }
}
