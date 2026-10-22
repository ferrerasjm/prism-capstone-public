<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicProgram extends Model
{
    protected $fillable = ['academic_unit_id', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function scopeCatalog(Builder $query): Builder
    {
        return $query
            ->orderBy(
                AcademicUnit::query()
                    ->select('name')
                    ->whereColumn('academic_units.id', 'academic_programs.academic_unit_id')
            )
            ->orderBy('name');
    }
}
