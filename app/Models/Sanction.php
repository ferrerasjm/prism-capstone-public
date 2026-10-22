<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sanction extends Model
{
    /**
     * How an Evaluation period closes. Category 2 is formative intervention, so the
     * officer records a finding on the intervention -- there is deliberately no
     * failure outcome here, because only Category 1 probation carries an
     * escalation clause in Section VI.E of the handbook.
     */
    public const ASSESSMENT_OUTCOMES = [
        'satisfactory' => 'Satisfactory',
        'needs_further_intervention' => 'Needs Further Intervention',
    ];

    protected $table = 'sanctions';

    protected $fillable = [
        'case_resolution_id',
        'student_id',
        'sanction_type',
        'status',
        'due_date',
        'completed_at',
        'verified_by',
        'completion_document_path',
        'notes',
        'intervention_notification_sent_at',
        'intervention_reminder_sent_at',
        'monitoring_review_flagged_at',
        'assessment_outcome',
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'intervention_notification_sent_at' => 'datetime',
        'intervention_reminder_sent_at' => 'datetime',
        'monitoring_review_flagged_at' => 'datetime',
    ];

    public function caseResolution(): BelongsTo
    {
        return $this->belongsTo(CaseResolution::class);
    }

    public function resolution(): BelongsTo
    {
        return $this->caseResolution();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function counselingReferral(): HasOne
    {
        return $this->hasOne(CounselingReferral::class, 'sanction_id');
    }

    /**
     * The next counseling-referral sanction raised for the same case, if a
     * non-compliant referral on this sanction was later re-referred (see
     * GuidanceReferralService::raiseFromFinding()). Re-referring creates a
     * fresh Sanction row rather than reusing this one, so an officer viewing
     * this now-superseded sanction has no way to reach the new one otherwise.
     */
    public function successorReferralSanction(): ?self
    {
        if ($this->sanction_type !== 'counseling_referral' || ! $this->case_resolution_id) {
            return null;
        }

        return self::where('case_resolution_id', $this->case_resolution_id)
            ->where('sanction_type', 'counseling_referral')
            ->where('id', '!=', $this->id)
            ->where('created_at', '>', $this->created_at)
            ->orderBy('created_at')
            ->first();
    }

    public function usAssignment(): HasOne
    {
        return $this->hasOne(
            UsAssignment::class,
            'case_resolution_id',
            'case_resolution_id'
        );
    }

    public function disciplineEducationAttendances(): HasMany
    {
        return $this->hasMany(DisciplineEducationAttendance::class)
            ->orderByDesc('session_at');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress', 'non_compliant']);
    }

    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeForOfficerDepartment(Builder $query, User $officer): Builder
    {
        if ($officer->role !== 'discipline_officer' || ! $officer->academic_unit_id) {
            return $query;
        }

        return $query->whereHas('student.studentProfile', fn (Builder $profile) => $profile
            ->forOfficerDepartment($officer));
    }

    public function isVisibleToOfficer(User $officer): bool
    {
        return self::query()
            ->whereKey($this->getKey())
            ->forOfficerDepartment($officer)
            ->exists();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'non_compliant']);
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'completed' || ! $this->due_date) {
            return false;
        }

        return today()->gt($this->due_date);
    }

    public function typeLabel(): string
    {
        return match ($this->sanction_type) {
            'written_warning' => 'Written Warning',
            'university_service' => 'University Service',
            'counseling_referral' => 'Counseling Referral',
            'discipline_education' => 'Discipline Education Program',
            'evaluation_monitoring' => 'Evaluation Monitoring',
            'probation' => 'Probation',
            'non_readmission' => 'Non-Readmission',
            'exclusion' => 'Exclusion',
            'expulsion' => 'Expulsion',
            default => ucwords(str_replace('_', ' ', $this->sanction_type)),
        };
    }

    public function assessmentOutcomeLabel(): ?string
    {
        return self::ASSESSMENT_OUTCOMES[$this->assessment_outcome] ?? null;
    }
}
