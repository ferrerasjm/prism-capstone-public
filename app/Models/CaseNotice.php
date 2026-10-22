<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\MajorOffenseWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseNotice extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'notice_type',
        'issued_by',
        'issued_at',
        'student_handbook_id',
        'handbook_section',
        'handbook_page',
        'incident_date',
        'response_deadline',
        'deadline_reminder_sent_at',
        'overdue_flagged_at',
        'scheduled_at',
        'scheduled_location',
        'additional_content',
        'student_acknowledged_at',
        'is_voided',
        'voided_by',
        'voided_at',
        'void_reason',
        'created_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'incident_date' => 'date',
        'response_deadline' => 'date',
        'deadline_reminder_sent_at' => 'datetime',
        'overdue_flagged_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'additional_content' => 'array',
        'student_acknowledged_at' => 'datetime',
        'is_voided' => 'boolean',
        'voided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function studentHandbook(): BelongsTo
    {
        return $this->belongsTo(StudentHandbook::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function isOverdue(): bool
    {
        return $this->notice_type === 'notice_to_explain'
            && is_null($this->student_acknowledged_at)
            && ! is_null($this->response_deadline)
            && today()->gt($this->response_deadline);
    }

    public function responseDaysRemaining(): ?int
    {
        return $this->response_deadline
            ? MajorOffenseWorkflow::wholeDaysRemaining($this->response_deadline)
            : null;
    }
}
