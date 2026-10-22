<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AcademicUnit extends Model
{
    protected $fillable = ['name', 'scope_year_level', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    private const NAME_ABBREVIATIONS = [
        'School of Architecture, Technology, and Computing' => 'SATC',
        'School of Education, Arts, and Sciences' => 'SEAS',
        'School of Accountancy, Business, and Management' => 'SABM',
        'School of Engineering' => 'SENG',
        'School of Dentistry and Allied Health' => 'SDAH',
        'Grade 11 (Senior High School)' => 'Grade 11',
        'Grade 12 (Senior High School)' => 'Grade 12',
    ];

    public static function abbreviatedName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $name = trim($name);

        if (isset(self::NAME_ABBREVIATIONS[$name])) {
            return self::NAME_ABBREVIATIONS[$name];
        }

        $stripped = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $name));
        $words = collect(preg_split('/[\s,]+/', $stripped))
            ->filter(fn ($word) => $word !== '' && ! in_array(mb_strtolower($word), ['of', 'and', 'the', '&'], true));

        if ($words->count() < 2) {
            return $name;
        }

        return $words->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    }

    public function programs(): HasMany
    {
        return $this->hasMany(AcademicProgram::class);
    }

    public function disciplineOfficers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'discipline_officer_academic_unit')
            ->withTimestamps();
    }

    public function guidanceOfficers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guidance_officer_academic_unit')
            ->withTimestamps();
    }

    public function scopeCatalog(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * Units that own programs but whose discipline coverage is handled by year-level units
     * instead. Senior High School keeps its programs and its place in student onboarding;
     * officers covering it are assigned Grade 11 or Grade 12, never the unit itself.
     *
     * Matched by name because nothing in the schema links a year-level unit to its parent.
     */
    public const NOT_OFFICER_SCOPES = [
        'Senior High School',
    ];

    /** The departments an officer can actually be assigned to. */
    public function scopeOfficerAssignable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotIn('name', self::NOT_OFFICER_SCOPES)
            ->orderBy('name');
    }
}
