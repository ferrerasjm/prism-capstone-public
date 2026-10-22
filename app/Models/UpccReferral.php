<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UpccReferral extends Model
{
    use HasFactory;

    protected $fillable = [
        'case_id',
        'referred_by',
        'referred_at',
        'scheduled_conference_at',
        'conference_location',
        'student_attended',
        'student_no_show_recorded_by',
        'proceedings_started_at',
        'proceedings_ended_at',
        'status',
        'decision_summary',
        'outcome',
        'decision_issued_at',
        'final_report_submitted_at',
        'appeal_window_start',
        'appeal_window_end',
        'appeal_filed',
        'appeal_filed_by',
        'appeal_filed_at',
        'appeal_source',
        'appeal_waived_by',
        'appeal_waived_at',
        'decision_final_at',
        'records_filed_at',
        'submitted_to_presidents_council_at',
        'presidents_council_action',
        'presidents_council_notes',
        'presidents_council_acted_at',
        'decision_distributed_at',
        'proceedings_type',
        'appeal_notes',
        'sdao_head_name',
        'dean_or_program_chair_name',
        'cross_program_faculty_name',
        'student_representative_name',
        'presiding_officer_name',
    ];

    protected $casts = [
        'referred_at' => 'datetime',
        'scheduled_conference_at' => 'datetime',
        'student_attended' => 'boolean',
        'proceedings_started_at' => 'datetime',
        'proceedings_ended_at' => 'datetime',
        'decision_issued_at' => 'datetime',
        'final_report_submitted_at' => 'datetime',
        'appeal_window_start' => 'date',
        'appeal_window_end' => 'date',
        'appeal_filed' => 'boolean',
        'appeal_filed_at' => 'datetime',
        'appeal_waived_at' => 'datetime',
        'decision_final_at' => 'datetime',
        'records_filed_at' => 'datetime',
        'submitted_to_presidents_council_at' => 'datetime',
        'presidents_council_acted_at' => 'datetime',
        'decision_distributed_at' => 'datetime',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function noShowRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_no_show_recorded_by');
    }

    public function appealFiledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appeal_filed_by');
    }

    public function appealWaivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'appeal_waived_by');
    }

    public function resolution(): HasOne
    {
        return $this->hasOne(CaseResolution::class, 'upcc_referral_id');
    }

    public function appealAttachments(): HasMany
    {
        return $this->hasMany(UpccAppealAttachment::class, 'upcc_referral_id');
    }

    /**
     * Splits appeal_notes into the student's original text and the reconsideration
     * outcome appended by recordReconsiderationOutcome(), so views can render the
     * outcome as its own block instead of the raw "[Reconsideration Outcome — ...]"
     * marker text.
     */
    public function appealNotesParts(): array
    {
        $notes = $this->appeal_notes ?? '';

        if (preg_match('/^(.*)\n\n\[Reconsideration Outcome — ([^\]]+)\]: (.*)$/s', $notes, $matches)) {
            return [
                'original' => trim($matches[1]),
                'reconsideration_date' => $matches[2],
                'reconsideration_summary' => trim($matches[3]),
            ];
        }

        return [
            'original' => $notes,
            'reconsideration_date' => null,
            'reconsideration_summary' => null,
        ];
    }

    public function isAppealWindowOpen(): bool
    {
        if (is_null($this->appeal_window_end)) {
            return false;
        }

        return today()->lte($this->appeal_window_end)
            && ! $this->appeal_filed
            && ! $this->appeal_waived_at
            && ! $this->decision_final_at;
    }
}
