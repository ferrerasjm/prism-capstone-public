<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UsAssignment extends Model
{
    protected $table = 'us_assignments';

    protected $fillable = [
        'case_resolution_id',
        'student_id',
        'supervisor_id',
        'contact_person_name',
        'contact_person_email',
        'assigned_by',
        'office_name',
        'total_hours_required',
        'hours_rendered',
        'start_date',
        'target_completion_date',
        'deadline_review_flagged_at',
        'student_deadline_reminded_at',
        'student_deadline_missed_notified_at',
        'status',
        'totp_secret',
        'totp_secret_generated_at',
    ];

    protected $casts = [
        'total_hours_required' => 'decimal:2',
        'hours_rendered' => 'decimal:2',
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'deadline_review_flagged_at' => 'datetime',
        'student_deadline_reminded_at' => 'datetime',
        'student_deadline_missed_notified_at' => 'datetime',
        'totp_secret_generated_at' => 'datetime',
    ];

    protected $hidden = ['totp_secret'];

    public function caseResolution(): BelongsTo
    {
        return $this->belongsTo(CaseResolution::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function getSupervisorDisplayNameAttribute(): string
    {
        return $this->supervisor?->list_name
            ?? $this->contact_person_name
            ?? 'To be assigned';
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(UsAttendanceLog::class, 'us_assignment_id');
    }

    /**
     * The currently open QR-scan session (time-in logged, time-out not yet
     * scanned), if any. Used to gate whether a new time-in/time-out is valid.
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(UsAttendanceLog::class, 'us_assignment_id')
            ->where('log_method', 'qr_scan')
            ->whereNotNull('time_in')
            ->whereNull('time_out')
            ->latestOfMany();
    }

    public function hoursRemaining(): float
    {
        return max(0, (float) $this->total_hours_required - (float) $this->hours_rendered);
    }

    public static function formatDuration(float|int|string|null $decimalHours): string
    {
        $totalMinutes = max(0, (int) round((float) $decimalHours * 60));
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }

        if ($minutes > 0 || $hours === 0) {
            $parts[] = $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
        }

        return implode(' ', $parts);
    }

    public function renderedDuration(): string
    {
        return self::formatDuration($this->hours_rendered);
    }

    public function requiredDuration(): string
    {
        return self::formatDuration($this->total_hours_required);
    }

    public function remainingDuration(): string
    {
        return self::formatDuration($this->hoursRemaining());
    }

    public function completionPercentage(): int
    {
        if ((float) $this->total_hours_required === 0.0) {
            return 100;
        }

        return (int) min(100, round(
            ((float) $this->hours_rendered / (float) $this->total_hours_required) * 100
        ));
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'completed'
            && today()->gt($this->target_completion_date);
    }

    public function scopeForOfficerDepartment(Builder $query, User $officer): Builder
    {
        if ($officer->role !== 'discipline_officer' || ! $officer->academic_unit_id) {
            return $query;
        }

        return $query->whereHas('student.studentProfile', fn (Builder $profile) => $profile
            ->forOfficerDepartment($officer));
    }

    /**
     * Whether $user may time-in/time-out students and otherwise act on this
     * assignment: either the one supervisor it was assigned to, or any other
     * us_supervisor covering the same office (so a student can, say, time-in
     * on one staff member's device and time-out on another's).
     */
    public function isCoveredBy(User $user): bool
    {
        if ((int) $this->supervisor_id === (int) $user->id) {
            return true;
        }

        return $user->role === 'us_supervisor'
            && $this->office_name !== null
            && $this->office_name === $user->office_name;
    }

    /** Assignments $user may act on — see isCoveredBy(). */
    public function scopeVisibleToSupervisor(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('supervisor_id', $user->id);

            if ($user->office_name !== null) {
                $q->orWhere('office_name', $user->office_name);
            }
        });
    }
}
