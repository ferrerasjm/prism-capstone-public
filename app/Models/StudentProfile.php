<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    use HasFactory;

    private const PROGRAM_NAME_ALIASES = [
        'BS Information Technology' => ['BSIT'],
    ];

    private const PROGRAM_ABBREVIATIONS = [
        'BS Architecture' => 'BSArc',
        'BS Civil Engineering' => 'BSCE',
        'BS Computer Engineering' => 'BSCpE',
        'BS Information Technology' => 'BSIT',
        'BA Communications' => 'BAComm',
        'BA Political Science' => 'BAPolSci',
        'BS Medical Technology' => 'BSMT',
        'BS Psychology' => 'BSPsy',
        'BS Accountancy' => 'BSA',
        'BS Management Accounting' => 'BSMA',
        'BSBA Marketing Management' => 'BSBA-MM',
        'BS Tourism Management' => 'BSTM',
        'Computing and Technologies (STEM_CT)' => 'STEM_CT',
        'Education, Arts and Social Sciences (AHSS_EASS)' => 'AHSS_EASS',
        'Engineering and Architecture (STEM_EA)' => 'STEM_EA',
        'Management and Accountancy (BUEN_MA)' => 'BUEN_MA',
        'Sciences and Allied Health (STEM_SAH)' => 'STEM_SAH',
    ];

    protected $fillable = [
        'user_id',
        'student_number',
        'program',
        'year_level',
        'contact_number',
        'guardian_name',
        'guardian_contact',
        'guardian_relationship',
        'data_accuracy_acknowledged_at',
    ];

    protected $casts = [
        'data_accuracy_acknowledged_at' => 'datetime',
    ];

    /*
     * Store the program name already trimmed, and treat a blank string as no
     * program. Report queries filter on `program <> ''` and rely on there being
     * no whitespace-padded values to match against an index.
     */
    protected function program(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                $value = $value === null ? null : trim($value);

                return $value === '' ? null : $value;
            },
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForOfficerDepartment(Builder $query, User $officer): Builder
    {
        if ($officer->role !== 'discipline_officer') {
            return $query;
        }

        $units = $officer->assignedAcademicUnits();

        if ($units->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $yearLevels = $units->pluck('scope_year_level')->filter()->values();
        $programUnitIds = $units->whereNull('scope_year_level')->pluck('id')->values();
        $programNames = $programUnitIds->isEmpty()
            ? collect()
            : AcademicProgram::query()
                ->whereIn('academic_unit_id', $programUnitIds)
                ->pluck('name')
                ->flatMap(fn (string $name) => [
                    $name,
                    ...(self::PROGRAM_NAME_ALIASES[$name] ?? []),
                ])
                ->unique()
                ->values();

        return $query->where(function (Builder $scope) use ($yearLevels, $programNames): void {
            if ($programNames->isNotEmpty()) {
                $scope->whereIn('program', $programNames);
            }

            if ($yearLevels->isNotEmpty()) {
                $method = $programNames->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                $scope->{$method}('year_level', $yearLevels);
            }
        });
    }

    public function scopeForAcademicUnit(Builder $query, AcademicUnit $unit): Builder
    {
        if ($unit->scope_year_level) {
            return $query->where('year_level', $unit->scope_year_level);
        }

        $programNames = AcademicProgram::query()
            ->where('academic_unit_id', $unit->id)
            ->pluck('name')
            ->flatMap(fn (string $name) => [
                $name,
                ...(self::PROGRAM_NAME_ALIASES[$name] ?? []),
            ])
            ->unique()
            ->values();

        if ($programNames->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('program', $programNames);
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    public function matchingAcademicUnitIds(): \Illuminate\Support\Collection
    {
        $canonical = self::canonicalProgramName($this->program);

        return AcademicUnit::query()
            ->where(function (Builder $query) use ($canonical): void {
                if ($this->year_level) {
                    $query->orWhere('scope_year_level', $this->year_level);
                }

                if ($canonical) {
                    $query->orWhereHas('programs', fn (Builder $programs) => $programs->where('name', $canonical));
                }
            })
            ->pluck('id');
    }

    public static function canonicalProgramName(?string $program): ?string
    {
        if ($program === null) {
            return null;
        }

        $program = trim($program);

        foreach (self::PROGRAM_NAME_ALIASES as $canonical => $aliases) {
            if ($program === $canonical || in_array($program, $aliases, true)) {
                return $canonical;
            }
        }

        return $program;
    }

    public static function abbreviatedProgramName(?string $program, string $fallback = '-'): string
    {
        if ($program === null || trim($program) === '') {
            return $fallback;
        }

        $program = trim($program);

        if (isset(self::PROGRAM_ABBREVIATIONS[$program])) {
            return self::PROGRAM_ABBREVIATIONS[$program];
        }

        foreach (self::PROGRAM_NAME_ALIASES as $canonical => $aliases) {
            if (in_array($program, $aliases, true)) {
                return self::PROGRAM_ABBREVIATIONS[$canonical] ?? $program;
            }
        }

        return $program;
    }

    public function isOnboardingComplete(): bool
    {
        return ! is_null($this->program)
            && ! is_null($this->year_level)
            && ! is_null($this->contact_number)
            && ! is_null($this->guardian_name)
            && ! is_null($this->data_accuracy_acknowledged_at);
    }
}
