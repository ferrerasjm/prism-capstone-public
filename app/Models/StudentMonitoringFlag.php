<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMonitoringFlag extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'flag_type',
        'flagged_by',
        'flag_reason',
        'is_active',
        'created_at',
        'deactivated_by',
        'deactivated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
