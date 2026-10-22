<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpccAppealAttachment extends Model
{
    protected $fillable = [
        'upcc_referral_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'file_size_bytes',
        'file_hash',
        'scan_status',
        'scanned_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'scanned_at' => 'datetime',
    ];

    public function referral(): BelongsTo
    {
        return $this->belongsTo(UpccReferral::class, 'upcc_referral_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
