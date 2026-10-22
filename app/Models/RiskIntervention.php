<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskIntervention extends Model
{
    public const ACTION_TYPES = [
        'staff_review' => 'Risk Review',
        'student_support_meeting' => 'Student Support Meeting',
        'monitoring_plan' => 'Monitoring Plan',
        'referral_consideration' => 'Referral for Support Services',
        'compliance_follow_up' => 'Compliance Follow-up',
        'other_support' => 'Other Support Action',
    ];

    public const STATUSES = [
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'student_id',
        'risk_score_snapshot_id',
        'created_by',
        'assigned_to',
        'action_type',
        'action_plan',
        'risk_score',
        'risk_band',
        'methodology_version',
        'status',
        'follow_up_on',
        'outcome',
        'started_at',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'risk_score' => 'decimal:2',
        'follow_up_on' => 'date',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function riskScoreSnapshot(): BelongsTo
    {
        return $this->belongsTo(RiskScoreSnapshot::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && $this->follow_up_on->isBefore(today());
    }

    public function actionLabel(): string
    {
        return self::ACTION_TYPES[$this->action_type] ?? 'Support Action';
    }

    public function statusLabel(): string
    {
        return $this->isOverdue()
            ? 'Overdue'
            : (self::STATUSES[$this->status] ?? ucfirst($this->status));
    }
}
