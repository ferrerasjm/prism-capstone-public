<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CaseResolution extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'upcc_referral_id',
        'resolution_level',
        'intervention_category',
        'us_hours_assigned',
        'counseling_referred',
        'discipline_education_required',
        'evaluation_monitoring_required',
        'probation_terms',
        'probation_end_date',
        'notes',
        'resolved_by',
        'resolved_at',
        'appeal_deadline',
        'appeal_filed',
        'appeal_filed_at',
        'appeal_outcome',
        'created_at',
    ];

    /**
     * Student Handbook VI.E category names. A bare "Category 3" tells a student
     * nothing, so anywhere the category is shown to them it carries its name.
     */
    public static function interventionLabel(?int $category): string
    {
        $default = match ($category) {
            1 => "Category 1 \u{2014} Probation",
            2 => "Category 2 \u{2014} Formative Intervention",
            3 => "Category 3 \u{2014} Non-Readmission",
            4 => "Category 4 \u{2014} Exclusion",
            5 => "Category 5 \u{2014} Expulsion",
            default => "Not yet recorded",
        };

        if ($category === null) {
            return $default;
        }

        $intervention = OffenseCategory::find($category)?->intervention;

        return $intervention ? "Category {$category} \u{2014} {$intervention}" : $default;
    }

    protected $casts = [
        'intervention_category' => 'integer',
        'us_hours_assigned' => 'decimal:2',
        'counseling_referred' => 'boolean',
        'discipline_education_required' => 'boolean',
        'evaluation_monitoring_required' => 'boolean',
        'probation_terms' => 'integer',
        'probation_end_date' => 'date',
        'resolved_at' => 'datetime',
        'appeal_deadline' => 'date',
        'appeal_filed' => 'boolean',
        'appeal_filed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function upccReferral(): BelongsTo
    {
        return $this->belongsTo(UpccReferral::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    public function usAssignment(): HasOne
    {
        return $this->hasOne(UsAssignment::class, 'case_resolution_id');
    }

    public function isAppealWindowOpen(): bool
    {
        return ! $this->appeal_filed
            && ! is_null($this->appeal_deadline)
            && today()->lte($this->appeal_deadline);
    }
}
