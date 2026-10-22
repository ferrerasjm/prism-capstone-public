<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    protected $table = 'risk_scores';

    protected $fillable = [
        'student_id',
        'composite_score',
        'risk_band',
        'trend',
        'contributing_factors',
        'computed_at',
    ];

    protected $casts = [
        'composite_score' => 'decimal:2',
        'contributing_factors' => 'array',
        'computed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function scopeForOfficerDepartment(Builder $query, User $officer): Builder
    {
        if ($officer->role !== 'discipline_officer' || ! $officer->academic_unit_id) {
            return $query;
        }

        return $query->whereHas('student.studentProfile', fn (Builder $profile) => $profile
            ->forOfficerDepartment($officer));
    }
}
