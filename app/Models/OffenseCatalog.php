<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OffenseCatalog extends Model
{
    public $timestamps = false;

    protected $table = 'offense_catalog';

    protected $fillable = [
        'code',
        'offense_type',
        'title',
        'description',
        'handbook_section',
        'handbook_page',
        'keywords',
        'special_consequence',
        'is_active',
        'created_at',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function sanctionMatrix(): HasMany
    {
        return $this->hasMany(SanctionMatrix::class, 'offense_catalog_id');
    }

    public function suggestedCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'suggested_offense_id');
    }

    public function confirmedCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'confirmed_offense_id');
    }

    public function chargedCases(): BelongsToMany
    {
        return $this->belongsToMany(DisciplinaryCase::class, 'case_offenses', 'offense_catalog_id', 'case_id')
            ->withPivot(['status', 'prior_offense_count', 'classification_reason', 'confirmed_by', 'confirmed_at'])
            ->withTimestamps();
    }

    public function scopeMinor($query)
    {
        return $query->where('offense_type', 'minor');
    }

    public function scopeMajor($query)
    {
        return $query->where('offense_type', 'major');
    }

    public function defaultHandbookSection(): string
    {
        return $this->offense_type === 'minor' ? 'Section VI.D' : 'Section VI.E';
    }
}
