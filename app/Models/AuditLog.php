<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    // Append-only: no updates, no deletes; only created_at is stored.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'module',
        'record_id',
        'old_values',
        'new_values',
        'reason',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
        'record_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(fn (AuditLog $audit) => app(\App\Services\AnnouncementService::class)
            ->publishFromAudit($audit));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function summary(?array $oldValues = null, ?array $newValues = null): string
    {
        $oldValues ??= $this->old_values;
        $newValues ??= $this->new_values;

        return match ($this->module) {
            'users' => $this->accountSummary($newValues),
            'university_service_offices' => $this->serviceOfficeSummary($oldValues, $newValues),
            'auth' => $this->authenticationSummary(),
            default => $this->resourceSummary(),
        };
    }

    public static function formatValue(mixed $value): string
    {
        if (is_null($value) || $value === '') {
            return 'Not set';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return collect($value)->map(function (mixed $item, mixed $key): string {
                $display = is_array($item)
                    ? 'Multiple values'
                    : self::formatValue($item);

                return is_string($key)
                    ? ucwords(str_replace(['_', '-'], ' ', $key)).': '.$display
                    : $display;
            })->implode(', ');
        }

        $display = (string) $value;
        if (! str_contains($display, '@') && preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)+$/i', $display)) {
            return ucwords(str_replace('_', ' ', $display));
        }

        return preg_match('/^[a-z]+$/', $display) ? ucfirst($display) : $display;
    }

    public static function formatField(string $field, ?string $accountRole = null): string
    {
        if ($field === 'institutional_id') {
            return $accountRole === 'student' ? 'Student ID' : 'University ID';
        }

        if ($field === 'student_number') {
            return 'Student ID';
        }

        return Str::of($field)
            ->replace(['_', '-'], ' ')
            ->title()
            ->replace(' Id', ' ID')
            ->toString();
    }

    private function accountSummary(?array $newValues): string
    {
        if ($this->action === 'import') {
            return 'Student accounts have been imported.';
        }

        if ($this->action === 'update' && array_key_exists('status', $newValues ?? [])) {
            return match ($newValues['status']) {
                'active' => 'An account has been activated.',
                'inactive' => 'An account has been deactivated.',
                'pending_setup' => 'An account has been returned to pending setup.',
                default => 'An account status has been updated.',
            };
        }

        return match ($this->action) {
            'create' => 'An account has been created.',
            'delete' => 'An account has been removed.',
            default => 'An account has been updated.',
        };
    }

    private function serviceOfficeSummary(?array $oldValues, ?array $newValues): string
    {
        if ($this->action === 'create') {
            return 'A University Service Office has been added.';
        }

        if (($oldValues['is_active'] ?? null) === true && ($newValues['is_active'] ?? null) === false) {
            return 'A University Service Office has been removed.';
        }

        if (($oldValues['is_active'] ?? null) === false && ($newValues['is_active'] ?? null) === true) {
            return 'A University Service Office has been restored.';
        }

        return 'A University Service Office has been updated.';
    }

    private function authenticationSummary(): string
    {
        return match ($this->action) {
            'login' => 'A user has signed in.',
            'logout' => 'A user has signed out.',
            default => 'An account security setting has been updated.',
        };
    }

    private function resourceSummary(): string
    {
        $resource = match ($this->module) {
            'academic_units' => 'academic unit',
            'academic_programs' => 'academic program',
            'academic_periods' => 'academic period',
            'student_handbooks' => 'student handbook',
            'incident_reports' => 'incident report',
            'cases' => 'case',
            'case_notices' => 'case notice',
            'case_resolutions' => 'case resolution',
            'case_explanations' => 'student explanation',
            'counseling_referrals' => 'counseling referral',
            'upcc_referrals' => 'UPCC referral',
            'risk_interventions' => 'risk intervention',
            'risk_factor_weights' => 'risk scoring configuration',
            'us_assignments' => 'University Service assignment',
            'us_attendance_logs' => 'University Service attendance record',
            default => Str::of((string) $this->module)
                ->replace(['_', '-'], ' ')
                ->singular()
                ->lower()
                ->toString() ?: 'record',
        };

        $verb = match ($this->action) {
            'create' => 'created',
            'delete' => 'removed',
            'void' => 'voided',
            'override' => 'overridden',
            'archive' => 'archived',
            'restore' => 'restored',
            'import' => 'imported',
            default => 'updated',
        };
        $article = $this->articleFor($resource);

        return "{$article} {$resource} has been {$verb}.";
    }

    private function articleFor(string $resource): string
    {
        $firstWord = strtolower(Str::of($resource)->trim()->explode(' ')->first() ?? '');

        $consonantSoundingWords = ['university', 'upcc'];

        if (in_array($firstWord, $consonantSoundingWords, true)) {
            return 'A';
        }

        return preg_match('/^[aeiou]/i', $resource) ? 'An' : 'A';
    }
}
