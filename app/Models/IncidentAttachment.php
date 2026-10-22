<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'incident_report_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size_bytes',
        'file_hash',
        'scan_status',
        'scanned_at',
        'uploaded_by',
        'created_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'scanned_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
