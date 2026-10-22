<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseOffense extends Model
{
    protected $fillable = [
        'case_id',
        'offense_catalog_id',
        'status',
        'prior_offense_count',
        'classification_reason',
        'confirmed_by',
        'confirmed_at',
        'removed_by',
        'removed_at',
        'removal_reason',
    ];

    protected $casts = [
        'prior_offense_count' => 'integer',
        'confirmed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function offense(): BelongsTo
    {
        return $this->belongsTo(OffenseCatalog::class, 'offense_catalog_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function removedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'removed_by');
    }
}
