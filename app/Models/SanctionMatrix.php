<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SanctionMatrix extends Model
{
    public $timestamps = false;

    protected $table = 'sanction_matrix';

    protected $fillable = [
        'offense_catalog_id',
        'offense_count',
        'sanction_category',
        'us_hours_recommended',
        'us_hours_min',
        'us_hours_max',
        'us_hours_guidance',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'offense_count' => 'integer',
        'sanction_category' => 'integer',
        'us_hours_recommended' => 'float',
        'us_hours_min' => 'float',
        'us_hours_max' => 'float',
        'is_active' => 'boolean',
    ];

    public function offense(): BelongsTo
    {
        return $this->belongsTo(OffenseCatalog::class, 'offense_catalog_id');
    }
}
