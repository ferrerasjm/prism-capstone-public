<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseExplanation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'submitted_at',
        'submission_channel',
        'admission_type',
        'explanation_text',
        'word_count',
        'evaluated_by',
        'evaluated_at',
        'evaluation_notes',
        'created_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'evaluated_at' => 'datetime',
        'created_at' => 'datetime',
        'word_count' => 'integer',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CaseExplanationAttachment::class);
    }

    /**
     * Label + badge class for an evaluation outcome. Colour tracks how contested
     * the case is: an admission is cooperative (green), a denial points to a
     * conference (red), partial sits between, and no-response is neutral.
     *
     * @return array{0: string, 1: string}
     */
    public static function admissionBadge(?string $type): array
    {
        return match ($type) {
            'full_admission' => ['Full Admission', 'prism-badge-green'],
            'partial_admission' => ['Partial Admission', 'prism-badge-amber'],
            'full_denial' => ['Full Denial', 'prism-badge-red'],
            'no_response' => ['No Response', 'prism-badge-gray'],
            default => ['—', 'prism-badge-gray'],
        };
    }
}
