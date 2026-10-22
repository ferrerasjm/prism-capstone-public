<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineEducationAttendance extends Model
{
    protected $fillable = [
        'sanction_id',
        'session_at',
        'status',
        'notes',
        'recorded_by',
        'notification_sent_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'session_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function sanction(): BelongsTo
    {
        return $this->belongsTo(Sanction::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
