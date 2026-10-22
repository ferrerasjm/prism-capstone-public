<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_number',
        'source_type',
        'reported_by_user_id',
        'reported_by_name',
        'student_id',
        'academic_period_id',
        'date_of_incident',
        'time_of_incident',
        'location',
        'description',
        'violation_type',
        'status',
        'reviewed_by',
        'reviewed_at',
        'dismissal_reason',
    ];

    protected $casts = [
        'date_of_incident' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IncidentReport $report): void {
            if (! $report->academic_period_id && $report->date_of_incident) {
                $report->academic_period_id = AcademicPeriod::query()
                    ->active()
                    ->containing($report->date_of_incident)
                    ->value('id');
            }
        });
    }

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(IncidentReportSubject::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(DisciplinaryCase::class, 'incident_report_id');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeBySourceType($query, string $type)
    {
        return $query->where('source_type', $type);
    }

    public function scopeForOfficerDepartment(Builder $query, User $officer): Builder
    {
        if ($officer->role !== 'discipline_officer') {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($officer): void {
            $scope->whereHas('student.studentProfile', fn (Builder $profile) => $profile
                ->forOfficerDepartment($officer))
                ->orWhere(function (Builder $unidentified) use ($officer): void {
                    $unidentified->whereNull('student_id')
                        ->where(function (Builder $source) use ($officer): void {
                            // Direct encoding permits an unidentified subject. Keep that
                            // report available only to its creator until a student can be
                            // identified.
                            $source->where(function (Builder $direct) use ($officer): void {
                                $direct->where('source_type', 'direct_encoding')
                                    ->where('reported_by_user_id', $officer->id);
                            })
                                // A student's own complaint names no subject up front, so
                                // there's no student to route by — fall back to routing by
                                // the complainant's own department instead of losing the
                                // report entirely.
                                ->orWhere(function (Builder $complaint) use ($officer): void {
                                    $complaint->where('source_type', 'student_complaint')
                                        ->whereHas('reportedByUser.studentProfile', fn (Builder $profile) => $profile
                                            ->forOfficerDepartment($officer));
                                });
                        });
                });
        });
    }

    public function isVisibleToOfficer(User $officer): bool
    {
        return self::query()
            ->whereKey($this->getKey())
            ->forOfficerDepartment($officer)
            ->exists();
    }
}
