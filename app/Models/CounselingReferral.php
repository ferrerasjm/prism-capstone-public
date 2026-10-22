<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingReferral extends Model
{
    protected $table = 'counseling_referrals';

    /**
     * How soon the Guidance Office is expected to schedule the student, set by the
     * Discipline Officer on the resolution form. A scheduling signal, not a clinical
     * severity rating — a student in crisis is escalated through other channels, not by
     * how this dropdown is set.
     *
     * @var array<string, array{label: string, turnaround: string, badge: string}>
     */
    public const URGENCY_LEVELS = [
        'routine' => [
            'label' => 'Non-Urgent',
            'turnaround' => 'Added to the regular counseling queue — no target date.',
            'badge' => 'prism-badge-blue',
        ],
        'priority' => [
            'label' => 'Priority',
            'turnaround' => 'Target: First session within 1–2 weeks.',
            'badge' => 'prism-badge-orange',
        ],
        'immediate' => [
            'label' => 'Immediate',
            'turnaround' => 'Target: First session within 2–3 school days.',
            'badge' => 'prism-badge-red',
        ],
    ];

    protected $fillable = [
        'sanction_id',
        'student_id',
        'guidance_officer_id',
        'referred_by',
        'referred_at',
        'referral_reason',
        'urgency',
        'status',
        'acknowledged_at',
        'completed_at',
        'confirmed_by',
        'incomplete_reason',
        'reassigned_from',
        'reassigned_at',
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'completed_at' => 'datetime',
        'reassigned_at' => 'datetime',
    ];

    public function sanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function guidanceOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guidance_officer_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isAcknowledged(): bool
    {
        return ! is_null($this->acknowledged_at);
    }

    /**
     * @return array{label: string, turnaround: string, badge: string}
     */
    public function urgencyDetails(): array
    {
        return self::URGENCY_LEVELS[$this->urgency] ?? self::URGENCY_LEVELS['routine'];
    }
}
