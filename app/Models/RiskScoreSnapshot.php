<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScoreSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'risk_score_snapshots';

    protected $fillable = [
        'student_id',
        'snapshot_type',
        'composite_score',
        'previous_composite_score',
        'risk_band',
        'previous_risk_band',
        'contributing_factors',
        'factor_changes',
        'methodology_version',
        'trigger',
        'triggered_by',
        'term',
        'snapshot_date',
        'created_at',
    ];

    protected $casts = [
        'composite_score' => 'decimal:2',
        'previous_composite_score' => 'decimal:2',
        'contributing_factors' => 'array',
        'factor_changes' => 'array',
        'snapshot_date' => 'date',
        'created_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function scoreDelta(): ?float
    {
        if ($this->previous_composite_score === null) {
            return null;
        }

        return round((float) $this->composite_score - (float) $this->previous_composite_score, 2);
    }

    public function triggerLabel(): string
    {
        return match ($this->trigger) {
            'scheduled_recompute' => 'Scheduled recomputation',
            'manual_recompute' => 'Manual recomputation',
            'risk_weights_updated' => 'Risk weights updated',
            'case_created' => 'Case created',
            'case_escalation_flagged' => 'Case flagged for escalation',
            'case_resolved' => 'Case resolved',
            'case_dismissed' => 'Case dismissed',
            'case_voided' => 'Case voided',
            'sanction_non_compliance' => 'Sanction marked non-compliant',
            'sanction_compliance_restored' => 'Sanction compliance restored',
            'counseling_non_compliance' => 'Counseling marked incomplete',
            'term_archive' => 'Term archive',
            default => 'Risk score recomputed',
        };
    }
}
