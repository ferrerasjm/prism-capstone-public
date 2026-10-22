<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsAttendanceLog extends Model
{
    protected $table = 'us_attendance_logs';

    protected $fillable = [
        'us_assignment_id',
        'student_id',
        'date_of_service',
        'time_in',
        'time_out',
        'hours_rendered',
        'tasks_performed',
        'log_method',
        'totp_verified',
        'client_scanned_at',
        'override_reason',
        'logged_by',
        'verified_by',
        'verified_at',
        'notes',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
    ];

    protected $casts = [
        'date_of_service' => 'date',
        'hours_rendered' => 'decimal:2',
        'verified_at' => 'datetime',
        'totp_verified' => 'boolean',
        'client_scanned_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(UsAssignment::class, 'us_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    /**
     * A completed, not-yet-rejected session with real hours to reject —
     * i.e. not still open (no time-out yet), not a no-show (nothing to
     * reject, already zero), and not already rejected.
     */
    public function isRejectable(): bool
    {
        return $this->log_method !== 'no_show'
            && $this->time_out !== null
            && ! $this->isRejected();
    }

    public function renderedDuration(): string
    {
        return UsAssignment::formatDuration($this->hours_rendered);
    }
}
