<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class DisciplinaryCase extends Model
{
    use HasFactory;

    // 'case' is a reserved PHP keyword — table name must be explicit
    protected $table = 'cases';

    protected $fillable = [
        'case_number',
        'incident_report_id',
        'student_id',
        'case_type',
        'case_type_locked',
        'case_type_change_reason',
        'assigned_to',
        'status',
        'suggested_offense_id',
        'confirmed_offense_id',
        'classification_override_reason',
        'prior_offense_count',
        'suggested_sanction_category',
        'assigned_sanction_category',
        'sanction_override_reason',
        'escalation_flagged',
        'escalation_flag_reason',
        'escalation_acknowledged_by',
        'escalation_acknowledged_at',
        'path_recommendation',
        'path_recommendation_reason',
        'path_override_reason',
        'forwarded_to_coordinator',
        'forwarded_at',
        'forwarded_reason',
        'resolution_notes',
        'resolved_by',
        'resolved_at',
        'void_requires_coordinator_approval',
        'void_coordinator_approved_by',
        'void_coordinator_approved_at',
        'voided_by',
        'void_reason',
        'voided_at',
        'dismissal_reason',
    ];

    protected $casts = [
        'case_type_locked' => 'boolean',
        'escalation_flagged' => 'boolean',
        'forwarded_to_coordinator' => 'boolean',
        'void_requires_coordinator_approval' => 'boolean',
        'prior_offense_count' => 'integer',
        'suggested_sanction_category' => 'integer',
        'assigned_sanction_category' => 'integer',
        'escalation_acknowledged_at' => 'datetime',
        'forwarded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'void_coordinator_approved_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function suggestedOffense(): BelongsTo
    {
        return $this->belongsTo(OffenseCatalog::class, 'suggested_offense_id');
    }

    public function confirmedOffense(): BelongsTo
    {
        return $this->belongsTo(OffenseCatalog::class, 'confirmed_offense_id');
    }

    public function caseOffenses(): HasMany
    {
        return $this->hasMany(CaseOffense::class, 'case_id');
    }

    public function offenseRecommendationAudits(): HasMany
    {
        return $this->hasMany(OffenseRecommendationAudit::class, 'case_id');
    }

    public function confirmedOffenses(): BelongsToMany
    {
        return $this->belongsToMany(OffenseCatalog::class, 'case_offenses', 'case_id', 'offense_catalog_id')
            ->wherePivot('status', 'confirmed')
            ->withPivot(['prior_offense_count', 'classification_reason', 'confirmed_by', 'confirmed_at'])
            ->withTimestamps();
    }

    public function escalationAcknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalation_acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function voidCoordinatorApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'void_coordinator_approved_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function notices(): HasMany
    {
        return $this->hasMany(CaseNotice::class, 'case_id');
    }

    public function activeNotices(): HasMany
    {
        return $this->hasMany(CaseNotice::class, 'case_id')
            ->where('is_voided', false);
    }

    public function explanation(): HasOne
    {
        return $this->hasOne(CaseExplanation::class, 'case_id');
    }

    public function upccReferral(): HasOne
    {
        return $this->hasOne(UpccReferral::class, 'case_id');
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(CaseResolution::class, 'case_id');
    }

    public function scopeMinor($query)
    {
        return $query->where('case_type', 'minor');
    }

    public function scopeMajor($query)
    {
        return $query->where('case_type', 'major');
    }

    public function scopeForwardedToUpcc($query)
    {
        return $query->where('forwarded_to_coordinator', true);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeEscalationFlagged($query)
    {
        return $query->where('escalation_flagged', true)
            ->whereNull('escalation_acknowledged_at');
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

    public function isVoidable(): bool
    {
        return ! $this->isClosed();
    }

    /**
     * Whether the case has reached a terminal status. Once closed, nothing about the
     * case — offenses, evaluation, path, resolution — can be changed again; the only
     * way to revisit a closed case is to reopen it through an explicit, audited
     * action elsewhere, not by mutating it directly.
     */
    public function isClosed(): bool
    {
        return in_array($this->status, ['resolved', 'dismissed', 'voided'], true);
    }

    public function requiresCoordinatorVoidApproval(): bool
    {
        return ! in_array($this->status, ['open', 'pending_nte']);
    }

    public function studentWorkflowState(): string
    {
        if ($this->status !== 'pending_upcc') {
            return $this->status;
        }

        $referral = $this->relationLoaded('upccReferral')
            ? $this->upccReferral
            : $this->upccReferral()->first();

        if (! $referral) {
            return 'upcc_awaiting_schedule';
        }

        if ($referral->decision_final_at) {
            return 'upcc_decision_final';
        }

        if ($referral->appeal_filed) {
            return 'upcc_appeal_filed';
        }

        if ($referral->decision_distributed_at && $referral->isAppealWindowOpen()) {
            return 'upcc_appeal_window';
        }

        if ($referral->decision_distributed_at) {
            return 'upcc_appeal_window_closed';
        }

        return match ($referral->status) {
            'scheduled' => 'upcc_scheduled',
            'in_progress' => 'upcc_in_progress',
            'concluded' => 'upcc_decision_pending_release',
            'reconsideration_filed' => 'upcc_appeal_filed',
            default => 'upcc_awaiting_schedule',
        };
    }

    /**
     * The label and badge class a student sees for a workflow state.
     *
     * Lives here because the cases list and the dashboard each carried their own
     * copy, and both fell through to a generic "In Progress" for open and
     * under_review — a case with no confirmed offense read as though something
     * were already moving against the student.
     *
     * @return array{0: string, 1: string}
     */
    public static function studentStateBadge(string $state): array
    {
        return match ($state) {
            'open', 'under_review' => ['Under Review', 'prism-badge-blue'],
            'awaiting_explanation' => ['Explanation Under Review', 'prism-badge-violet'],
            'pending_nte' => ['Explanation Required', 'prism-badge-orange'],
            'upcc_awaiting_schedule' => ['Awaiting Conference', 'prism-badge-orange'],
            'upcc_scheduled' => ['Conference Scheduled', 'prism-badge-blue'],
            'upcc_in_progress' => ['Conference in Progress', 'prism-badge-blue'],
            'upcc_decision_pending_release' => ['Decision Pending', 'prism-badge-violet'],
            'upcc_appeal_window' => ['Appeal Window Open', 'prism-badge-orange'],
            'upcc_appeal_filed' => ['Appeal Filed', 'prism-badge-violet'],
            'upcc_appeal_window_closed' => ['Appeal Window Closed', 'prism-badge-gray'],
            'upcc_decision_final' => ['Decision Finalized', 'prism-badge-green'],
            'pending_resolution' => ['Pending Resolution', 'prism-badge-amber'],
            'resolved' => ['Resolved', 'prism-badge-green'],
            'dismissed' => ['Dismissed', 'prism-badge-gray'],
            'voided' => ['Voided', 'prism-badge-red'],
            default => ['Under Review', 'prism-badge-blue'],
        };
    }

    public static function sanctionCategoryBadgeClass(?int $category): string
    {
        return match ($category) {
            1 => 'prism-badge-blue',
            2 => 'prism-badge-violet',
            3 => 'prism-badge-amber',
            4 => 'prism-badge-orange',
            5 => 'prism-badge-red',
            default => 'prism-badge-gray',
        };
    }
}
