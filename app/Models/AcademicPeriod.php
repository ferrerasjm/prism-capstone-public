<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AcademicPeriod extends Model
{
    protected $fillable = [
        'academic_year',
        'term_name',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function scopeContaining(Builder $query, mixed $date): Builder
    {
        return $query
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The end date of the nth academic term counted from the one containing $from
     * (inclusive). Category 1 probation runs "three (3) academic terms" per Handbook
     * Section VI.E, which is a property of the academic calendar rather than something
     * an officer should be typing by hand.
     *
     * Returns null when the calendar does not yet reach that far, so callers can fall
     * back rather than silently inventing a date.
     */
    public static function endOfTermsFrom(mixed $from, int $terms): ?Carbon
    {
        if ($terms < 1) {
            return null;
        }

        $period = static::query()
            ->whereDate('ends_on', '>=', $from)
            ->orderBy('starts_on')
            ->skip($terms - 1)
            ->first();

        return $period?->ends_on;
    }

    public function getLabelAttribute(): string
    {
        return "{$this->term_name} A.Y. {$this->academic_year}";
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Archived';
        }

        if ($this->starts_on->isAfter(today())) {
            return 'Upcoming';
        }

        if ($this->ends_on->isBefore(today())) {
            return 'Ended';
        }

        return 'Active';
    }

    public function statusBadgeClass(): string
    {
        return match ($this->statusLabel()) {
            'Active' => 'prism-badge-green',
            'Upcoming' => 'prism-badge-blue',
            default => 'prism-badge-gray',
        };
    }
}
