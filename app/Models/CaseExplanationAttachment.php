<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseExplanationAttachment extends Model
{
    protected $fillable = [
        'case_explanation_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'file_type',
        'file_size_bytes',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    public function explanation(): BelongsTo
    {
        return $this->belongsTo(CaseExplanation::class, 'case_explanation_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
