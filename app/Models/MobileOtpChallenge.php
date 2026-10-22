<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileOtpChallenge extends Model
{
    protected $fillable = [
        'otp_token',
        'user_id',
        'code_hash',
        'attempts',
        'expires_at',
        'resend_available_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'resend_available_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isLocked(): bool
    {
        return $this->attempts >= 5;
    }
}
