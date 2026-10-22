<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OffenseCategory extends Model
{
    protected $primaryKey = 'number';

    public $incrementing = false;

    protected $fillable = ['number', 'intervention', 'description', 'is_active'];

    protected $casts = ['number' => 'integer', 'is_active' => 'boolean'];
}
