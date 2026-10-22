<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\CaseExplanation;
use App\Models\CaseNotice;
use App\Models\CaseOffense;
use App\Models\CaseResolution;
use App\Models\CounselingReferral;
use App\Models\DisciplinaryCase;
use App\Models\DisciplineEducationAttendance;
use App\Models\OffenseCatalog;
use App\Models\Sanction;
use App\Models\SanctionMatrix;
use App\Models\StudentHandbook;
use App\Models\UsAssignment;
use App\Models\User;
use App\Notifications\CaseEscalatedNotification;
use App\Notifications\CaseResolvedNotification;
use App\Notifications\CoordinatorCaseNotification;
use App\Notifications\DisciplineEducationScheduleNotification;
use App\Notifications\EvaluationMonitoringNotification;
use App\Notifications\NfiIssuedNotification;
use App\Notifications\NteIssuedNotification;
use App\Notifications\SanctionsAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The core disciplinary case lifecycle: NTE issuance, correction and
 * reissue, offense confirmation and removal, escalation, evaluation,
 * resolution and NFI. The largest service in the system and the one most
 * other case workflows delegate into.
 */
class CaseService
{
    /**
     * Issues a Notice to Explain.
     *
     * Refuses cases with no confirmed offense, and refuses minor cases
     * outright: the NTE procedure applies to major offenses only, since
     * minor cases are resolved directly without a formal explanation
     * window.
     */
    public function issueNTE(DisciplinaryCase $case, array $noticeData, int $issuedBy): CaseNotice
    {
        if (! $case->confirmed_offense_id) {
            throw new \RuntimeException('Cannot issue NTE: no confirmed offense on this case.');
        }

        if ($case->case_type !== 'major') {
            throw new \RuntimeException('The Notice to Explain procedure applies to major offense cases only. Minor cases are resolved directly.');
        }

        $case->loadMissing('confirmedOffense');

        $issuedAt = now();
        $responseDeadline = MajorOffenseWorkflow::nteResponseDeadline($issuedAt);
        $handbookReference = $this->noticeHandbookReference($case, $noticeData);
        $studentHandbookId = StudentHandbook::applicableOn($noticeData['incident_date'])?->id;
        $offenses = $case->caseOffenses()
            ->where('status', 'confirmed')
            ->with('offense')
            ->get()
            ->map(fn ($charge) => [
                'id' => $charge->offense_catalog_id,
                'code' => $charge->offense?->code,
                'title' => $charge->offense?->title,
                'type' => $charge->offense?->offense_type,
                'handbook_section' => $charge->offense?->handbook_section,
                'handbook_page' => $charge->offense?->handbook_page,
            ])
            ->values()
            ->all();

        DB::transaction(function () use ($case, $noticeData, $issuedBy, $issuedAt, $responseDeadline, $handbookReference, $studentHandbookId, $offenses, &$notice) {
            $notice = CaseNotice::create([
                'case_id' => $case->id,
                'notice_type' => 'notice_to_explain',
                'issued_by' => $issuedBy,
                'issued_at' => $issuedAt,
                'student_handbook_id' => $studentHandbookId,
                'handbook_section' => $handbookReference['section'],
                'handbook_page' => $handbookReference['page'],
                'incident_date' => $noticeData['incident_date'],
                'response_deadline' => $responseDeadline,
                'additional_content' => [
                    'offenses' => $offenses,
                    'notes' => $noticeData['additional_content'] ?? null,
                ],
                'is_voided' => false,
                'created_at' => $issuedAt,
            ]);

            $case->update([
                'status' => 'pending_nte',
                'case_type_locked' => true,
            ]);

            AuditLog::create([
                'user_id' => $issuedBy,
                'action' => 'create',
                'module' => 'case_notices',
                'record_id' => $notice->id,
                'new_values' => [
                    'notice_type' => 'notice_to_explain',
                    'case_number' => $case->case_number,
                    'response_deadline' => $responseDeadline->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $student = $case->student;
            if ($student) {
                $student->notify(new NteIssuedNotification($case, $notice));
            }
        });

        return $notice;
    }

    private function noticeHandbookReference(DisciplinaryCase $case, array $noticeData): array
    {
        return [
            'section' => $case->confirmedOffense?->handbook_section
                ?: ($noticeData['handbook_section'] ?? $case->confirmedOffense?->defaultHandbookSection() ?? 'Section VI.E'),
            'page' => $case->confirmedOffense?->handbook_page
                ?: ($noticeData['handbook_page'] ?? ''),
        ];
    }

    public function voidNTE(
        DisciplinaryCase $case,
        CaseNotice $notice,
        string $reason,
        int $voidedBy
    ): void {
        $this->assertActiveNTE($case, $notice);

        DB::transaction(function () use ($case, $notice, $reason, $voidedBy) {
            $notice->update([
                'is_voided' => true,
                'voided_by' => $voidedBy,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            $case->update(['status' => 'open']);

            AuditLog::create([
                'user_id' => $voidedBy,
                'action' => 'void',
                'module' => 'case_notices',
                'record_id' => $notice->id,
                'old_values' => [
                    'is_voided' => false,
                    'response_deadline' => $notice->response_deadline?->toDateString(),
                ],
                'new_values' => ['is_voided' => true, 'case_status' => 'open'],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

        });
    }

    public function extendNTEDeadline(
        DisciplinaryCase $case,
        CaseNotice $notice,
        Carbon $newDeadline,
        string $reason,
        int $extendedBy
    ): void {
        $this->assertActiveNTE($case, $notice);

        $oldDeadline = $notice->response_deadline?->copy();
        if (! $oldDeadline || ! $newDeadline->isAfter($oldDeadline)) {
            throw new \RuntimeException('The extended deadline must be after the current deadline.');
        }

        DB::transaction(function () use ($notice, $oldDeadline, $newDeadline, $reason, $extendedBy) {
            $notice->update([
                'response_deadline' => $newDeadline->toDateString(),
                'deadline_reminder_sent_at' => null,
                'overdue_flagged_at' => null,
            ]);

            AuditLog::create([
                'user_id' => $extendedBy,
                'action' => 'update',
                'module' => 'case_notices',
                'record_id' => $notice->id,
                'old_values' => ['response_deadline' => $oldDeadline->toDateString()],
                'new_values' => ['response_deadline' => $newDeadline->toDateString()],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    public function reissueNTE(
        DisciplinaryCase $case,
        CaseNotice $notice,
        array $noticeData,
        string $reason,
        int $issuedBy
    ): CaseNotice {
        $this->assertActiveNTE($case, $notice);

        return DB::transaction(function () use ($case, $notice, $noticeData, $reason, $issuedBy) {
            $this->voidNTE($case, $notice, $reason, $issuedBy);
            $replacement = $this->issueNTE($case->fresh(), $noticeData, $issuedBy);

            AuditLog::create([
                'user_id' => $issuedBy,
                'action' => 'update',
                'module' => 'case_notices',
                'record_id' => $replacement->id,
                'old_values' => ['replaces_notice_id' => $notice->id],
                'new_values' => ['replacement_notice_id' => $replacement->id],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return $replacement;
        });
    }

    private function assertActiveNTE(DisciplinaryCase $case, CaseNotice $notice): void
    {
        if ((int) $notice->case_id !== (int) $case->id
            || $notice->notice_type !== 'notice_to_explain'
            || $notice->is_voided
            || $case->status !== 'pending_nte'
            || $case->explanation()->exists()) {
            throw new \RuntimeException('Only the active NTE of a case awaiting response can be changed.');
        }
    }

    public function confirmOffense(
        DisciplinaryCase $case,
        int $offenseId,
        int $issuedBy,
        ?string $overrideReason = null
    ): void {
        $this->confirmOffenses($case, [$offenseId], $issuedBy, $overrideReason);
    }

    /**
     * Confirm one or more offense charges on a case in a single transaction,
     * recomputing the primary charge, sanction category, and case type once
     * across the full resulting set of confirmed charges.
     *
     * @param  int[]  $offenseIds
     */
    public function confirmOffenses(
        DisciplinaryCase $case,
        array $offenseIds,
        int $issuedBy,
        ?string $overrideReason = null
    ): void {
        $this->assertOffenseChargesEditable($case);

        $offenseIds = array_values(array_unique(array_map('intval', $offenseIds)));

        if (empty($offenseIds)) {
            throw new \RuntimeException('Select at least one offense to confirm.');
        }

        // Only offenses that are an active (confirmed) charge block re-selection. A
        // previously dismissed charge keeps its row for the audit trail, but the
        // (case_id, offense_catalog_id) unique key means re-adding it has to revive
        // that same row rather than insert a second one.
        $existingCharges = $case->caseOffenses()->get();
        $confirmedIds = $existingCharges
            ->where('status', 'confirmed')
            ->pluck('offense_catalog_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($offenseIds as $offenseId) {
            OffenseCatalog::query()->where('is_active', true)->findOrFail($offenseId);

            if (in_array($offenseId, $confirmedIds, true)) {
                throw new \RuntimeException('One or more selected offenses are already an active charge on this case.');
            }
        }

        DB::transaction(function () use ($case, $offenseIds, $issuedBy, $overrideReason) {
            foreach ($offenseIds as $offenseId) {
                $priorOffenseCount = CaseOffense::where('offense_catalog_id', $offenseId)
                    ->whereHas('case', fn ($query) => $query
                        ->where('student_id', $case->student_id)
                        ->where('id', '!=', $case->id)
                        ->whereNotIn('status', ['dismissed', 'voided']))
                    ->where('status', 'confirmed')
                    ->count();

                $attributes = [
                    'status' => 'confirmed',
                    'prior_offense_count' => $priorOffenseCount,
                    'classification_reason' => $overrideReason,
                    'confirmed_by' => $issuedBy,
                    'confirmed_at' => now(),
                    'removed_by' => null,
                    'removed_at' => null,
                    'removal_reason' => null,
                ];

                $revived = $case->caseOffenses()
                    ->where('offense_catalog_id', $offenseId)
                    ->first();

                if ($revived) {
                    $revived->update($attributes);
                } else {
                    $case->caseOffenses()->create(['offense_catalog_id' => $offenseId] + $attributes);
                }
            }

            $oldValues = [
                'confirmed_offense_id' => $case->confirmed_offense_id,
                'case_type' => $case->case_type,
            ];

            $this->recomputeConfirmedCharges($case);
            $case->refresh();

            if (($oldValues['case_type'] ?? null) !== $case->case_type) {
                $this->notifyCoordinatorsOfCaseOpening(
                    $case->fresh(['student']),
                    $case->case_type === 'major'
                        ? 'A new major case has been opened'
                        : 'A new minor case has been opened',
                    'Case '.$case->case_number.' has been opened as a '.$case->case_type.' case and is ready for coordinator review.',
                    $case->case_type === 'major' ? 'major_case_opened' : 'minor_case_opened',
                );
            }

            AuditLog::create([
                'user_id' => $issuedBy,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'added_offense_ids' => $offenseIds,
                    'confirmed_offense_ids' => $case->caseOffenses()->where('status', 'confirmed')->pluck('offense_catalog_id')->all(),
                    'case_type' => $case->case_type,
                    'suggested_sanction_category' => $case->suggested_sanction_category,
                ],
                'reason' => $overrideReason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Remove a confirmed offense charge that turned out to be wrong — the correction
     * counterpart to confirmOffenses(). Only available in the same window as adding a
     * charge: before the case type locks, i.e. before any notice naming it has gone
     * out to the student. The row is kept (marked 'dismissed'), not deleted, so the
     * original classification and who removed it both stay on the record.
     */
    public function removeOffenseCharge(
        DisciplinaryCase $case,
        CaseOffense $charge,
        string $reason,
        int $removedBy
    ): void {
        $this->assertOffenseChargesEditable($case);

        if ((int) $charge->case_id !== (int) $case->id || $charge->status !== 'confirmed') {
            throw new \RuntimeException('This offense is not an active charge on this case.');
        }

        DB::transaction(function () use ($case, $charge, $reason, $removedBy) {
            $oldValues = [
                'confirmed_offense_id' => $case->confirmed_offense_id,
                'case_type' => $case->case_type,
            ];

            $charge->update([
                'status' => 'dismissed',
                'removed_by' => $removedBy,
                'removed_at' => now(),
                'removal_reason' => $reason,
            ]);

            $this->recomputeConfirmedCharges($case);
            $case->refresh();

            AuditLog::create([
                'user_id' => $removedBy,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => $oldValues + ['removed_offense_id' => $charge->offense_catalog_id],
                'new_values' => [
                    'confirmed_offense_id' => $case->confirmed_offense_id,
                    'case_type' => $case->case_type,
                    'suggested_sanction_category' => $case->suggested_sanction_category,
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    private function assertOffenseChargesEditable(DisciplinaryCase $case): void
    {
        if ($case->case_type_locked) {
            throw new \RuntimeException('Offense charges cannot be changed after the case type is locked.');
        }

        if ($case->status !== 'open') {
            throw new \RuntimeException('Offense charges can only be changed while the case is still open.');
        }
    }

    /**
     * Recompute the primary charge, suggested sanction category, and case type from
     * every currently-confirmed offense on the case. Shared by confirmOffenses() and
     * removeOffenseCharge() so adding and removing a charge always agree on the result.
     */
    private function recomputeConfirmedCharges(DisciplinaryCase $case): void
    {
        $charges = $case->caseOffenses()->where('status', 'confirmed')->with('offense')->get();

        if ($charges->isEmpty()) {
            $case->update([
                'confirmed_offense_id' => null,
                'prior_offense_count' => 0,
                'suggested_sanction_category' => null,
                'classification_override_reason' => null,
                'case_type' => null,
            ]);

            return;
        }

        $highestCategory = null;
        $primaryCharge = $charges->firstWhere('offense.offense_type', 'major') ?? $charges->first();

        foreach ($charges as $charge) {
            $matrix = SanctionMatrix::where('offense_catalog_id', $charge->offense_catalog_id)
                ->where('is_active', true)
                ->where('offense_count', $charge->prior_offense_count + 1)
                ->first()
                ?? SanctionMatrix::where('offense_catalog_id', $charge->offense_catalog_id)
                    ->where('is_active', true)
                    ->where('offense_count', '<=', $charge->prior_offense_count + 1)
                    ->orderByDesc('offense_count')
                    ->first();

            if ($matrix && ($highestCategory === null || $matrix->sanction_category > $highestCategory)) {
                $highestCategory = $matrix->sanction_category;
                $primaryCharge = $charge;
            }
        }

        $caseType = $charges->contains(fn ($charge) => $charge->offense?->offense_type === 'major')
            ? 'major'
            : 'minor';

        $case->update([
            'confirmed_offense_id' => $primaryCharge?->offense_catalog_id,
            'prior_offense_count' => $primaryCharge?->prior_offense_count ?? 0,
            'suggested_sanction_category' => $highestCategory,
            'classification_override_reason' => $primaryCharge?->classification_reason,
            'case_type' => $caseType,
        ]);
    }

    public function confirmSanction(
        DisciplinaryCase $case,
        int $sanctionCategory,
        int $issuedBy,
        ?string $overrideReason = null
    ): void {
        if ($case->case_type !== 'major') {
            throw new \RuntimeException('Sanction category confirmation applies to major offense cases only. Minor cases are resolved directly.');
        }

        if ($case->status !== 'open') {
            throw new \RuntimeException('The sanction category can only be set while the case is still open.');
        }

        $oldCategory = $case->assigned_sanction_category;

        $case->update([
            'assigned_sanction_category' => $sanctionCategory,
            'sanction_override_reason' => $overrideReason,
        ]);

        AuditLog::create([
            'user_id' => $issuedBy,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => $case->id,
            'old_values' => ['assigned_sanction_category' => $oldCategory],
            'new_values' => ['assigned_sanction_category' => $sanctionCategory, 'override_reason' => $overrideReason],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function acknowledgeEscalation(DisciplinaryCase $case, int $userId, string $reason): void
    {
        $case->update([
            'escalation_acknowledged_by' => $userId,
            'escalation_acknowledged_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'override',
            'module' => 'cases',
            'record_id' => $case->id,
            'old_values' => [
                'escalation_flagged' => true,
                'escalation_flag_reason' => $case->escalation_flag_reason,
            ],
            'new_values' => ['escalation_dismissed' => true],
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Applies a MAJOR-19 habitual-disregard escalation to a case.
     *
     * Only valid while an escalation alert is active and unacknowledged,
     * and only while the case is still open and its type unlocked -
     * escalation changes the case type, so a locked case cannot be
     * escalated after the fact.
     */
    public function escalateHabitualDisregard(DisciplinaryCase $case, int $userId): void
    {
        if (! $case->escalation_flagged || $case->escalation_acknowledged_at) {
            throw new \RuntimeException('This case does not have an active escalation alert.');
        }

        if ($case->status !== 'open' || $case->case_type_locked) {
            throw new \RuntimeException('Escalation can only be applied while the case is open and unlocked.');
        }

        $major19 = OffenseCatalog::where('code', 'MAJOR-19')->firstOrFail();
        $reason = $case->escalation_flag_reason
            ?? 'Habitual disregard threshold reached under the Student Handbook.';
        $oldCaseType = $case->case_type;

        DB::transaction(function () use ($case, $major19, $reason, $userId, $oldCaseType) {
            if (! $case->caseOffenses()->where('offense_catalog_id', $major19->id)->exists()) {
                $this->confirmOffense($case, $major19->id, $userId, $reason);
            }

            $case->update([
                'case_type' => 'major',
                'case_type_change_reason' => $reason,
                'escalation_acknowledged_by' => $userId,
                'escalation_acknowledged_at' => now(),
            ]);

            if ($oldCaseType !== 'major') {
                $this->notifyCoordinatorsOfCaseOpening(
                    $case->fresh(['student']),
                    'A minor case has been escalated to major review',
                    'Case '.$case->case_number.' was escalated from minor to major review and is ready for coordinator review.',
                    'major_case_escalated',
                );
            }

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'override',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => [
                    'case_type' => $oldCaseType,
                    'escalation_flagged' => true,
                ],
                'new_values' => [
                    'case_type' => 'major',
                    'habitual_disregard_applied' => true,
                    'offense_id' => $major19->id,
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $case->student?->notify(
                new CaseEscalatedNotification($case->fresh())
            );
        });
    }

    public function initiateResolution(
        DisciplinaryCase $case,
        int $officerId,
        ?string $overrideReason = null
    ): void {
        if ($case->status !== 'under_review') {
            throw new \RuntimeException('This action is only available for cases under review.');
        }

        $oldStatus = $case->status;

        $case->update(array_filter([
            'status' => 'pending_resolution',
            'path_override_reason' => $overrideReason,
        ], fn ($value) => ! is_null($value)));

        AuditLog::create([
            'user_id' => $officerId,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => $case->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => array_filter([
                'status' => 'pending_resolution',
                'path_override_reason' => $overrideReason,
            ], fn ($value) => ! is_null($value)),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function saveEvaluation(
        DisciplinaryCase $case,
        CaseExplanation $explanation,
        string $admissionType,
        ?string $evaluationNotes,
        int $evaluatedBy,
        SanctionRecommendationService $sanctionService
    ): void {
        if (! is_null($explanation->evaluated_at)) {
            throw new \RuntimeException(
                'This explanation has already been evaluated. Evaluations are immutable after saving.'
            );
        }

        DB::transaction(function () use (
            $case, $explanation, $admissionType, $evaluationNotes,
            $evaluatedBy, $sanctionService
        ) {
            $explanation->update([
                'admission_type' => $admissionType,
                'evaluation_notes' => $evaluationNotes,
                'evaluated_by' => $evaluatedBy,
                'evaluated_at' => now(),
            ]);

            $category = $case->assigned_sanction_category
                ?? throw new \RuntimeException('Cannot evaluate: sanction category not set on case '.$case->case_number.'.');
            $pathResult = $sanctionService->recommendPath($admissionType, $category);

            $case->update([
                'status' => 'under_review',
                'path_recommendation' => $pathResult['path'],
                'path_recommendation_reason' => $pathResult['reason'],
            ]);

            AuditLog::create([
                'user_id' => $evaluatedBy,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'new_values' => [
                    'status' => 'under_review',
                    'admission_type' => $admissionType,
                    'path_recommendation' => $pathResult['path'],
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Correct an evaluation that was recorded wrong — the counterpart to
     * saveEvaluation()'s deliberate immutability. Only available while the case is
     * still under_review: once resolution has been initiated or the case forwarded
     * to the coordinator, the evaluation is genuinely final and this is no longer
     * offered — Dismiss/Void is the only way back from there.
     */
    public function correctEvaluation(
        DisciplinaryCase $case,
        CaseExplanation $explanation,
        string $admissionType,
        ?string $evaluationNotes,
        string $reason,
        int $correctedBy,
        SanctionRecommendationService $sanctionService
    ): void {
        if ($case->status !== 'under_review') {
            throw new \RuntimeException('The evaluation can only be corrected while the case is under review.');
        }

        if ((int) $explanation->case_id !== (int) $case->id || is_null($explanation->evaluated_at)) {
            throw new \RuntimeException('This explanation has not been evaluated yet.');
        }

        DB::transaction(function () use ($case, $explanation, $admissionType, $evaluationNotes, $reason, $correctedBy, $sanctionService) {
            $oldValues = [
                'admission_type' => $explanation->admission_type,
                'path_recommendation' => $case->path_recommendation,
            ];

            $explanation->update([
                'admission_type' => $admissionType,
                'evaluation_notes' => $evaluationNotes,
                'evaluated_by' => $correctedBy,
                'evaluated_at' => now(),
            ]);

            $category = $case->assigned_sanction_category
                ?? throw new \RuntimeException('Cannot evaluate: sanction category not set on case '.$case->case_number.'.');
            $pathResult = $sanctionService->recommendPath($admissionType, $category);

            $case->update([
                'path_recommendation' => $pathResult['path'],
                'path_recommendation_reason' => $pathResult['reason'],
            ]);

            AuditLog::create([
                'user_id' => $correctedBy,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'admission_type' => $admissionType,
                    'path_recommendation' => $pathResult['path'],
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    public function forwardToCoordinator(
        DisciplinaryCase $case,
        string $reason,
        int $officerId
    ): void {
        if ($case->isClosed()) {
            throw new \RuntimeException('This case is already closed and can no longer be forwarded.');
        }

        $oldStatus = $case->status;

        $case->update([
            'status' => 'pending_upcc',
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now(),
            'forwarded_reason' => $reason,
        ]);

        $this->notifyCoordinatorsOfCaseOpening(
            $case->fresh(['student']),
            'UPCC case forwarded to coordinator',
            'Case '.$case->case_number.' has been forwarded to the coordinator for UPCC review.',
            'upcc_case_forwarded',
        );

        AuditLog::create([
            'user_id' => $officerId,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => $case->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'pending_upcc', 'forwarded_reason' => $reason],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Requests that a case be voided.
     *
     * Closed cases cannot be voided, and a second request is refused
     * while one is already pending Coordinator approval, so the approval
     * queue cannot accumulate duplicates for the same case.
     */
    public function requestVoid(
        DisciplinaryCase $case,
        string $reason,
        int $requestedBy
    ): bool {
        if ($case->isClosed()) {
            throw new \RuntimeException('This case is already closed.');
        }

        if ($case->void_requires_coordinator_approval) {
            throw new \RuntimeException('A void request for this case is already pending Coordinator approval.');
        }

        $requiresApproval = ! in_array($case->status, ['open', 'pending_nte'], true);

        if (! $requiresApproval) {
            $case->update([
                'status' => 'voided',
                'voided_by' => $requestedBy,
                'void_reason' => $reason,
                'voided_at' => now(),
                'void_requires_coordinator_approval' => false,
            ]);

            $this->cancelSanctionsForCase($case);

            AuditLog::create([
                'user_id' => $requestedBy,
                'action' => 'void',
                'module' => 'cases',
                'record_id' => $case->id,
                'new_values' => ['status' => 'voided'],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            app(RiskScoringService::class)
                ->computeForStudent($case->student_id, 'case_voided', $requestedBy);

            return true;
        }

        $case->update([
            'void_requires_coordinator_approval' => true,
            'voided_by' => $requestedBy,
            'void_reason' => $reason,
        ]);

        AuditLog::create([
            'user_id' => $requestedBy,
            'action' => 'void',
            'module' => 'cases',
            'record_id' => $case->id,
            'new_values' => ['void_pending_approval' => true],
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        return false;
    }

    public function dismiss(DisciplinaryCase $case, string $reason, int $userId): void
    {
        if ($case->isClosed()) {
            throw new \RuntimeException('This case is already closed.');
        }

        $case->update([
            'status' => 'dismissed',
            'dismissal_reason' => $reason,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $userId,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => $case->id,
            'new_values' => ['status' => 'dismissed', 'dismissal_reason' => $reason],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        app(RiskScoringService::class)
            ->computeForStudent($case->student_id, 'case_dismissed', $userId);
    }

    private function notifyCoordinatorsOfCaseOpening(
        DisciplinaryCase $case,
        string $title,
        string $message,
        string $kind
    ): void {
        User::query()
            ->where('role', 'discipline_coordinator')
            ->where('status', 'active')
            ->get()
            ->each(fn (User $coordinator) => $coordinator->notify(
                new CoordinatorCaseNotification($case, $kind, $title, $message)
            ));
    }

    public function resolveMinorCase(
        DisciplinaryCase $case,
        string $resolutionNotes,
        int $resolvedBy
    ): void {
        if ($case->case_type !== 'minor') {
            throw new \RuntimeException('resolveMinorCase() called on a non-minor case.');
        }

        if ($case->isClosed()) {
            throw new \RuntimeException('This case is already closed.');
        }

        DB::transaction(function () use ($case, $resolutionNotes, $resolvedBy) {
            $oldStatus = $case->status;

            $case->update([
                'status' => 'resolved',
                'resolution_notes' => $resolutionNotes,
                'resolved_by' => $resolvedBy,
                'resolved_at' => now(),
            ]);

            AuditLog::create([
                'user_id' => $resolvedBy,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'resolved'],
                'reason' => $resolutionNotes,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            app(RiskScoringService::class)
                ->computeForStudent($case->student_id, 'case_resolved', $resolvedBy);

            $case->student?->notify(
                new CaseResolvedNotification($case->fresh())
            );
        });
    }

    /**
     * Writes the case resolution and the sanctions it implies.
     *
     * For Category 1 probation the end date is derived once, here, from
     * the academic-term count, so the resolution record and the
     * probation sanction it creates can never disagree about when the
     * period ends. Also stamps the appeal deadline from
     * MajorOffenseWorkflow and notifies the student.
     */
    public function recordResolution(
        DisciplinaryCase $case,
        array $data,
        int $resolvedBy
    ): CaseResolution {
        DB::transaction(function () use ($case, $data, $resolvedBy, &$resolution) {
            $resolvedAt = now();
            $appealDeadline = MajorOffenseWorkflow::appealDeadline($resolvedAt);

            // Derived once here so the resolution record and the probation sanction it
            // creates can never disagree about when the period ends.
            if ((int) $data['intervention_category'] === 1 && ! empty($data['probation_terms'])) {
                $data['probation_end_date'] = AcademicPeriod::endOfTermsFrom(
                    $resolvedAt,
                    (int) $data['probation_terms'],
                )?->toDateString() ?? ($data['probation_end_date'] ?? null);
            }

            $resolution = CaseResolution::create([
                'case_id' => $case->id,
                'upcc_referral_id' => $data['upcc_referral_id'] ?? null,
                'resolution_level' => $data['resolution_level'],
                'intervention_category' => $data['intervention_category'],
                'us_hours_assigned' => $data['us_hours_assigned'] ?? null,
                'counseling_referred' => $data['counseling_referred'] ?? false,
                'discipline_education_required' => $data['discipline_education_required'] ?? false,
                'evaluation_monitoring_required' => $data['evaluation_monitoring_required'] ?? false,
                'probation_terms' => $data['probation_terms'] ?? null,
                'probation_end_date' => $data['probation_end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'resolved_by' => $resolvedBy,
                'resolved_at' => $resolvedAt,
                'appeal_deadline' => $appealDeadline,
                'appeal_filed' => false,
                'created_at' => $resolvedAt,
            ]);

            $this->createSanctionRecords($resolution, $case->student_id, $data);

            $case->update([
                'status' => 'resolved',
                'resolved_by' => $resolvedBy,
                'resolved_at' => $resolvedAt,
            ]);

            AuditLog::create([
                'user_id' => $resolvedBy,
                'action' => 'create',
                'module' => 'case_resolutions',
                'record_id' => $resolution->id,
                'new_values' => [
                    'case_number' => $case->case_number,
                    'intervention_category' => $data['intervention_category'],
                    'resolution_level' => $data['resolution_level'],
                    'appeal_deadline' => $appealDeadline->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $resolution->load('sanctions');
            $student = $case->student;
            if ($student) {
                $student->notify(
                    new CaseResolvedNotification($case->fresh(), $resolution)
                );

                if ($resolution->sanctions->isNotEmpty()) {
                    $student->notify(
                        new SanctionsAssignedNotification($case->fresh(), $resolution)
                    );
                }
            }
        });

        app(RiskScoringService::class)
            ->computeForStudent($case->student_id, 'case_resolved', $resolvedBy);

        return $resolution;
    }

    private function createSanctionRecords(
        CaseResolution $resolution,
        int $studentId,
        array $data
    ): void {
        $category = (int) $data['intervention_category'];
        $now = now();

        // Auto-matched by the student's department (see
        // GuidanceReferralService::matchOfficerForStudent()) rather than picked on the
        // resolution form -- the department relationship already determines who should
        // handle it. Null only when no active Guidance Officer exists at all.
        $guidanceOfficerId = app(GuidanceReferralService::class)
            ->matchOfficerForStudent(User::find($studentId))
            ?->id;

        // The Discipline Officer's own account of why this student needs counseling — the
        // resolution form requires it whenever counseling is referred, so this fallback is
        // only ever reached for a referral raised some other way (e.g. directly on a sanction).
        $referralReason = trim((string) ($data['referral_reason'] ?? '')) !== ''
            ? trim((string) $data['referral_reason'])
            : "Assigned as part of Category {$category} disciplinary intervention.";
        $referralUrgency = in_array($data['urgency'] ?? null, array_keys(CounselingReferral::URGENCY_LEVELS), true)
            ? $data['urgency']
            : 'routine';

        if ($category === 1) {
            // Probation runs a whole number of academic terms, so its end date comes from
            // the academic calendar rather than a typed-in date that could contradict it.
            // The submitted date is the fallback for a calendar that does not yet reach
            // that far ahead.
            $probationEnd = AcademicPeriod::endOfTermsFrom($now, (int) $data['probation_terms'])
                ?? ($data['probation_end_date'] ?? null);

            Sanction::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $studentId,
                'sanction_type' => 'probation',
                'status' => 'in_progress',
                'due_date' => $probationEnd,
                'notes' => "Probation for {$data['probation_terms']} academic term(s).",
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (! empty($data['counseling_referred'])) {
                $sanction = Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $studentId,
                    'sanction_type' => 'counseling_referral',
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                CounselingReferral::create([
                    'sanction_id' => $sanction->id,
                    'student_id' => $studentId,
                    'guidance_officer_id' => $guidanceOfficerId,
                    'referred_by' => $resolution->resolved_by,
                    'referred_at' => $now,
                    'referral_reason' => $referralReason,
                    'urgency' => $referralUrgency,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($category === 2) {
            if (! empty($data['us_hours_assigned'])) {
                Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $studentId,
                    'sanction_type' => 'university_service',
                    'status' => 'pending',
                    'notes' => UsAssignment::formatDuration($data['us_hours_assigned']).' required.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! empty($data['counseling_referred'])) {
                $sanction = Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $studentId,
                    'sanction_type' => 'counseling_referral',
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                CounselingReferral::create([
                    'sanction_id' => $sanction->id,
                    'student_id' => $studentId,
                    'guidance_officer_id' => $guidanceOfficerId,
                    'referred_by' => $resolution->resolved_by,
                    'referred_at' => $now,
                    'referral_reason' => $referralReason,
                    'urgency' => $referralUrgency,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if (! empty($data['discipline_education_required'])) {
                $sanction = Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $studentId,
                    'sanction_type' => 'discipline_education',
                    'status' => 'pending',
                    'due_date' => ! empty($data['discipline_education_session_at'])
                        ? Carbon::parse($data['discipline_education_session_at'])->toDateString()
                        : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if (! empty($data['discipline_education_session_at'])) {
                    $attendance = DisciplineEducationAttendance::create([
                        'sanction_id' => $sanction->id,
                        'session_at' => $data['discipline_education_session_at'],
                        'status' => 'scheduled',
                        'recorded_by' => $resolution->resolved_by,
                    ]);

                    if ($sanction->student) {
                        $sanction->loadMissing('resolution.case');
                        $sanction->student->notify(
                            new DisciplineEducationScheduleNotification($sanction, $attendance)
                        );
                        $attendance->update(['notification_sent_at' => now()]);
                    }
                }
            }

            if (! empty($data['evaluation_monitoring_required'])) {
                // A monitoring sanction without an end date can neither remind nor close
                // itself, so the contract is explicit rather than silently nullable.
                if (empty($data['evaluation_monitoring_end_date'])) {
                    throw new \InvalidArgumentException(
                        'Evaluation monitoring requires evaluation_monitoring_end_date.'
                    );
                }

                // The period is known at resolution time, so the sanction starts running
                // immediately instead of waiting for an officer to open it and type a date.
                // Dated here, activated by the NFI: the student learns of the period from
                // the formal notice, the same way the other category 2 obligations start
                // (see issueNFI(), which flips this to in_progress and notifies the student).
                Sanction::create([
                    'case_resolution_id' => $resolution->id,
                    'student_id' => $studentId,
                    'sanction_type' => 'evaluation_monitoring',
                    'status' => 'pending',
                    'due_date' => Carbon::parse($data['evaluation_monitoring_end_date'])->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($category === 3) {
            // Takes effect immediately on issuance and has no follow-up workflow to
            // track (unlike US hours, counseling, discipline education or monitoring),
            // so it is created already completed rather than sitting on "pending" forever.
            Sanction::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $studentId,
                'sanction_type' => 'non_readmission',
                'status' => 'completed',
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            app(RegistrationHoldService::class)->raise(
                $studentId,
                "Non-Readmission sanction on {$resolution->case?->case_number}: barred from enrolling for the following term.",
                $resolution->resolved_by
            );
        }

        if ($category === 4) {
            // Same reasoning as non_readmission above: immediate effect, nothing to track.
            Sanction::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $studentId,
                'sanction_type' => 'exclusion',
                'status' => 'completed',
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            app(RegistrationHoldService::class)->raise(
                $studentId,
                "Exclusion sanction on {$resolution->case?->case_number}: name dropped from the university rolls.",
                $resolution->resolved_by
            );
        }

        if ($category === 5) {
            // Same reasoning as non_readmission/exclusion above: immediate effect, nothing to track.
            Sanction::create([
                'case_resolution_id' => $resolution->id,
                'student_id' => $studentId,
                'sanction_type' => 'expulsion',
                'status' => 'completed',
                'completed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            app(RegistrationHoldService::class)->raise(
                $studentId,
                "Expulsion sanction on {$resolution->case?->case_number}: permanently removed from the university.",
                $resolution->resolved_by
            );
        }
    }

    public function proceedWithNoResponse(DisciplinaryCase $case, string $reason, int $officerId): void
    {
        if ($case->status !== 'pending_nte') {
            throw new \RuntimeException('proceedWithNoResponse() called on a non-pending_nte case.');
        }

        DB::transaction(function () use ($case, $reason, $officerId) {
            CaseExplanation::create([
                'case_id' => $case->id,
                'submitted_at' => now(),
                'submission_channel' => 'no_response',
                'admission_type' => 'no_response',
                'explanation_text' => $reason,
                'word_count' => 0,
                'created_at' => now(),
            ]);

            $case->update(['status' => 'awaiting_explanation']);

            AuditLog::create([
                'user_id' => $officerId,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => ['status' => 'pending_nte'],
                'new_values' => ['status' => 'awaiting_explanation'],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        });
    }

    private function cancelSanctionsForCase(DisciplinaryCase $case): void
    {
        Sanction::whereHas(
            'resolution',
            fn ($q) => $q->where('case_id', $case->id)
        )
            ->whereIn('status', ['pending', 'in_progress'])
            ->update([
                'status' => 'waived',
                'updated_at' => now(),
            ]);
    }

    /**
     * The NFI exactly as it would be recorded, unsaved. The officer's preview renders
     * this, so what they review before issuing cannot drift from what gets issued.
     */
    public function draftNFI(
        DisciplinaryCase $case,
        CaseResolution $resolution,
        int $issuedBy,
        ?Carbon $issuedAt = null
    ): CaseNotice {
        $incidentDate = $case->incidentReport?->date_of_incident
            ?? $case->created_at->toDateString();
        $issuedAt = $issuedAt ?? now();

        return new CaseNotice([
            'case_id' => $case->id,
            'notice_type' => 'notice_of_formative_intervention',
            'issued_by' => $issuedBy,
            'issued_at' => $issuedAt,
            'student_handbook_id' => StudentHandbook::applicableOn($incidentDate)?->id,
            'handbook_section' => $case->confirmedOffense?->handbook_section
                ?? $case->confirmedOffense?->defaultHandbookSection()
                ?? 'Section VI.E',
            'handbook_page' => $case->confirmedOffense?->handbook_page ?? '',
            'incident_date' => $incidentDate,
            'response_deadline' => null,
            'additional_content' => [
                'intervention_category' => $resolution->intervention_category,
                'us_hours_assigned' => $resolution->us_hours_assigned,
                'counseling_referred' => $resolution->counseling_referred,
                'discipline_education_required' => $resolution->discipline_education_required,
                'evaluation_monitoring_required' => $resolution->evaluation_monitoring_required,
                'probation_terms' => $resolution->probation_terms,
                'probation_end_date' => $resolution->probation_end_date?->toDateString(),
                'notes' => $resolution->notes,
            ],
            'is_voided' => false,
            'created_at' => $issuedAt,
        ]);
    }

    /**
     * Issues a Notice of Formative Intervention.
     *
     * Drafting and persisting are split deliberately - draftNFI builds
     * the notice so a caller can preview its content before issuing,
     * and this method is the only path that actually saves it and
     * writes the audit log entry.
     */
    public function issueNFI(
        DisciplinaryCase $case,
        CaseResolution $resolution,
        int $issuedBy
    ): CaseNotice {
        DB::transaction(function () use ($case, $resolution, $issuedBy, &$notice) {
            $notice = $this->draftNFI($case, $resolution, $issuedBy);
            $notice->save();

            $issuedAt = $notice->issued_at;

            AuditLog::create([
                'user_id' => $issuedBy,
                'action' => 'create',
                'module' => 'case_notices',
                'record_id' => $notice->id,
                'new_values' => [
                    'notice_type' => 'notice_of_formative_intervention',
                    'case_number' => $case->case_number,
                    'intervention_category' => $resolution->intervention_category,
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $pendingSanctions = Sanction::where('case_resolution_id', $resolution->id)
                ->where('status', 'pending')
                ->whereIn('sanction_type', [
                    'university_service',
                    'counseling_referral',
                    'discipline_education',
                    'evaluation_monitoring',
                ])
                ->get();

            foreach ($pendingSanctions as $sanction) {
                $sanction->update([
                    'status' => in_array($sanction->sanction_type, ['university_service', 'discipline_education'], true)
                        ? 'pending'
                        : 'in_progress',
                    'updated_at' => $issuedAt,
                ]);

                AuditLog::create([
                    'user_id' => $issuedBy,
                    'action' => 'update',
                    'module' => 'sanctions',
                    'record_id' => $sanction->id,
                    'old_values' => ['status' => 'pending'],
                    'new_values' => [
                        'status' => in_array($sanction->sanction_type, ['university_service', 'discipline_education'], true)
                            ? 'pending'
                            : 'in_progress',
                        'activated_by_nfi' => $notice->id,
                    ],
                    'ip_address' => request()->ip(),
                    'created_at' => now(),
                ]);

                // The monitoring clock is now running, so the student is told when it ends.
                if ($sanction->sanction_type === 'evaluation_monitoring' && $sanction->due_date) {
                    $sanction->loadMissing('resolution.case');
                    $case->student?->notify(new EvaluationMonitoringNotification($sanction));
                    $sanction->update(['intervention_notification_sent_at' => now()]);
                }
            }

            $case->student?->notify(
                new NfiIssuedNotification($case, $notice)
            );
        });

        return $notice;
    }
}
