<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffenseRecommendationAudit extends Model
{
    protected $fillable = [
        'case_id',
        'context_hash',
        'description_hash',
        'source',
        'status',
        'model_name',
        'model_version',
        'recommendations',
        'no_match',
        'guardrail_reason',
        'requested_by',
        'selected_offense_id',
        'decision_was_recommended',
        'decided_by',
        'decided_at',
    ];

    protected $casts = [
        'recommendations' => 'array',
        'no_match' => 'boolean',
        'decision_was_recommended' => 'boolean',
        'decided_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function selectedOffense(): BelongsTo
    {
        return $this->belongsTo(OffenseCatalog::class, 'selected_offense_id');
    }
}
