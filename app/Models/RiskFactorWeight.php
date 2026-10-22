<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskFactorWeight extends Model
{
    public $timestamps = false;

    protected $table = 'risk_factor_weights';

    protected $fillable = [
        'rule',
        'factor_code',
        'factor_name',
        'factor_type',
        'count_cap',
        'weight',
        'description',
        'is_active',
        'updated_at',
    ];

    protected $casts = [
        'rule' => 'array',
        'weight' => 'decimal:4',
        'count_cap' => 'integer',
        'is_active' => 'boolean',
        'updated_at' => 'datetime',
    ];
}
