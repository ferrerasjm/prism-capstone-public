<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\PasswordResetNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    public const STUDENT_STATUSES = [
        'enrolled' => 'Enrolled',
        'graduated' => 'Graduated',
        'transferred' => 'Transferred',
        'withdrawn' => 'Withdrawn',
        'not_enrolled' => 'Not Currently Enrolled',
    ];
    public const DEACTIVATION_CATEGORIES = [
        'graduated' => 'Graduated',
        'transferred' => 'Transferred',
        'withdrawn' => 'Withdrawn',
        'not_enrolled' => 'Not Currently Enrolled',
        'temporary' => 'Temporary Access Suspension',
        'other' => 'Other',
    ];

    use HasFactory, Notifiable;

    public const REPORTING_AFFILIATIONS = [
        'Faculty / Academic Department' => 'Faculty / Academic Department',
        'Security Office' => 'Security Office',
    ];

    protected $fillable = [
        'last_name',
        'first_name',
        'middle_name',
        'institutional_id',
        'email',
        'contact_number',
        'profile_photo',
        'password',
        'role',
        'office_name',
        'academic_unit_id',
        'status',
        'deactivation_category',
        'deactivation_effective_on',
        'status_before_deactivation',
        'student_status',
        'student_status_on',
        'created_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'status' => 'string',
        'role' => 'string',
        'privacy_accepted_at' => 'datetime',
    ];

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'status' => $this->status,
        ];
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token)
    {
        $this->notify(new PasswordResetNotification($token));
    }

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])
            ->map(fn (?string $name): string => trim((string) $name))
            ->filter()
            ->implode(' ');
    }

    public function getListNameAttribute(): string
    {
        return self::formatListName($this->last_name, $this->first_name, $this->middle_name);
    }

    public static function formatListName(?string $lastName, ?string $firstName, ?string $middleName = null): string
    {
        $lastName = trim((string) $lastName);
        $firstName = trim((string) $firstName);
        $middleName = trim((string) $middleName);
        $middleInitial = $middleName !== '' ? Str::upper(Str::substr($middleName, 0, 1)).'.' : '';
        $givenNames = collect([$firstName, $middleInitial])->filter()->implode(' ');

        return $lastName !== '' && $givenNames !== ''
            ? "{$lastName}, {$givenNames}"
            : $lastName.$givenNames;
    }

    public function reportingSourceType(): string
    {
        if ($this->role !== 'reporting_user') {
            return 'faculty_report';
        }

        $affiliation = strtolower((string) $this->office_name);

        return str_contains($affiliation, 'security')
            ? 'security_report'
            : 'faculty_report';
    }

    public function reportingSourceLabel(): string
    {
        return match ($this->reportingSourceType()) {
            'security_report' => 'Security Report',
            default => 'Faculty Report',
        };
    }

    public function reportingAffiliationLabel(): string
    {
        return $this->office_name ?: self::REPORTING_AFFILIATIONS['Faculty / Academic Department'];
    }

    public function reportingTypeShortLabel(): string
    {
        return $this->reportingSourceType() === 'security_report' ? 'Security' : 'Faculty';
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function academicUnits(): BelongsToMany
    {
        return $this->belongsToMany(AcademicUnit::class, 'discipline_officer_academic_unit')
            ->withTimestamps();
    }

    public function guidanceAcademicUnits(): BelongsToMany
    {
        return $this->belongsToMany(AcademicUnit::class, 'guidance_officer_academic_unit')
            ->withTimestamps();
    }

    /**
     * Whether this user's report pages, filters, lookups and exports are limited
     * to the students in their assigned academic units. A discipline officer
     * with no unit assignments is treated as unscoped, matching the Sanctions
     * and Cases queues.
     */
    public function scopesReportsByDepartment(): bool
    {
        return $this->role === 'discipline_officer'
            && $this->assignedAcademicUnits()->isNotEmpty();
    }

    public function assignedAcademicUnits(): Collection
    {
        $relation = $this->role === 'guidance_officer' ? 'guidanceAcademicUnits' : 'academicUnits';

        $units = $this->relationLoaded($relation)
            ? $this->getRelation($relation)
            : $this->{$relation}()->get();

        if ($units->isEmpty() && $this->academic_unit_id) {
            $legacyUnit = $this->relationLoaded('academicUnit')
                ? $this->academicUnit
                : $this->academicUnit()->first();

            if ($legacyUnit) {
                $units = collect([$legacyUnit]);
            }
        }

        return $units->unique('id')->values();
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function accountSetupToken(): HasOne
    {
        return $this->hasOne(AccountSetupToken::class);
    }

    public function hasUnfinishedSetup(): bool
    {
        if ($this->privacy_accepted_at) {
            return false;
        }

        return $this->accountSetupToken()
            ->whereNull('consumed_at')
            ->exists();
    }

    public function riskScore(): HasOne
    {
        return $this->hasOne(RiskScore::class, 'student_id');
    }

    public function riskScoreSnapshots(): HasMany
    {
        return $this->hasMany(RiskScoreSnapshot::class, 'student_id');
    }

    public function riskInterventions(): HasMany
    {
        return $this->hasMany(RiskIntervention::class, 'student_id');
    }

    public function assignedRiskInterventions(): HasMany
    {
        return $this->hasMany(RiskIntervention::class, 'assigned_to');
    }

    public function nextRiskIntervention(): HasOne
    {
        return $this->hasOne(RiskIntervention::class, 'student_id')
            ->where('status', 'in_progress')
            ->ofMany([
                'follow_up_on' => 'min',
                'id' => 'min',
            ]);
    }

    public function latestRiskIntervention(): HasOne
    {
        return $this->hasOne(RiskIntervention::class, 'student_id')->latestOfMany();
    }

    public function monitoringFlags(): HasMany
    {
        return $this->hasMany(StudentMonitoringFlag::class, 'student_id');
    }

    public function activeMonitoringFlag(): HasOne
    {
        return $this->hasOne(StudentMonitoringFlag::class, 'student_id')
            ->where('is_active', true);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'student_id');
    }

    public function assignedCases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'assigned_to');
    }

    public function usAssignments(): HasMany
    {
        return $this->hasMany(UsAssignment::class, 'student_id');
    }

    public function supervisedAssignments(): HasMany
    {
        return $this->hasMany(UsAssignment::class, 'supervisor_id');
    }

    public function counselingReferrals(): HasMany
    {
        return $this->hasMany(CounselingReferral::class, 'student_id');
    }

    public function assignedReferrals(): HasMany
    {
        return $this->hasMany(CounselingReferral::class, 'guidance_officer_id');
    }

    public function csvImportLogs(): HasMany
    {
        return $this->hasMany(CsvImportLog::class, 'imported_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function scopeStudents($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePendingSetup($query)
    {
        return $query->where('status', 'pending_setup');
    }
}
