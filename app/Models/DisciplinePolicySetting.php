<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinePolicySetting extends Model
{
    protected $table = 'discipline_policy_settings';

    protected $fillable = [
        'same_type_minor_threshold',
        'different_type_minor_threshold',
        'policy_reference',
    ];

    protected $casts = [
        'same_type_minor_threshold' => 'integer',
        'different_type_minor_threshold' => 'integer',
    ];

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }
}
