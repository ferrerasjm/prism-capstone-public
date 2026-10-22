<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const DISPLAY_DAYS = 3;

    public const DASHBOARD_LIMIT = 3;

    public const RECIPIENT_ROLES = [
        'discipline_coordinator',
        'discipline_officer',
        'reporting_user',
        'guidance_officer',
        'us_supervisor',
        'student',
    ];

    protected $fillable = [
        'created_by',
        'target_user_id',
        'target_role',
        'target_academic_unit_id',
        'kind',
        'title',
        'message',
        'url',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function targetAcademicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class, 'target_academic_unit_id');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        $query = $query->where(function (Builder $targets) use ($user): void {
            $targets->where('target_user_id', $user->id)
                ->orWhere(function (Builder $roleTarget) use ($user): void {
                    $roleTarget->whereNull('target_user_id')
                        ->where('target_role', $user->role);
                });
        });

        if ($user->role !== 'discipline_officer') {
            return $query;
        }

        $academicUnitIds = $user->assignedAcademicUnits()->pluck('id')->all();

        return $query->where(function (Builder $departmentTarget) use ($academicUnitIds): void {
            $departmentTarget->whereNull('target_academic_unit_id');

            if ($academicUnitIds !== []) {
                $departmentTarget->orWhereIn('target_academic_unit_id', $academicUnitIds);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(self::DISPLAY_DAYS));
    }
}
