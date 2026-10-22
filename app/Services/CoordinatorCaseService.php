<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\Sanction;
use App\Models\StudentHandbook;
use App\Models\UpccAppealAttachment;
use App\Models\UpccReferral;
use App\Models\User;
use App\Notifications\UpccAppealSubmittedNotification;
use App\Notifications\UpccConferenceScheduledNotification;
use App\Notifications\UpccDecisionAvailableToComplainantNotification;
use App\Notifications\UpccDecisionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The UPCC workflow, from the Coordinator side: scheduling and
 * rescheduling conferences, recording attendance and decisions,
 * distributing outcomes, and handling appeals, reconsideration and void
 * approval.
 */
class CoordinatorCaseService
{
    /**
     * Schedules a UPCC conference for a case.
     *
     * Pins the handbook edition applicable on the incident date - not
     * today's edition - so a case is always judged under the rules in
     * force when the incident happened, even if the handbook is revised
     * before the conference sits.
     */
    public function scheduleUpcc(
        DisciplinaryCase $case,
        array $data,
        int $coordinatorId
    ): UpccReferral {
        $incidentDate = $case->incidentReport?->date_of_incident
            ?? $case->created_at->toDateString();
        $studentHandbookId = StudentHandbook::applicableOn($incidentDate)?->id;

        DB::transaction(function () use ($case, $data, $coordinatorId, $incidentDate, $studentHandbookId, &$referral) {
            $now = now();

            $referral = UpccReferral::create([
                'case_id' => $case->id,
                'referred_by' => $coordinatorId,
                'referred_at' => $now,
                'scheduled_conference_at' => $data['scheduled_conference_at'],
                'conference_location' => $data['conference_location'],
                'proceedings_type' => $data['proceedings_type'] ?? 'full_hearing',
                'sdao_head_name' => $data['sdao_head_name'],
                'dean_or_program_chair_name' => $data['dean_or_program_chair_name'],
                'cross_program_faculty_name' => $data['cross_program_faculty_name'],
                'student_representative_name' => $data['student_representative_name'],
                'presiding_officer_name' => $data['presiding_officer_name'],
                'status' => 'scheduled',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            CaseNotice::create([
                'case_id' => $case->id,
                'notice_type' => 'notice_of_hearing',
                'issued_by' => $coordinatorId,
                'issued_at' => $now,
                'student_handbook_id' => $studentHandbookId,
                'handbook_section' => $case->confirmedOffense?->handbook_section
                    ?? $case->confirmedOffense?->defaultHandbookSection()
                    ?? 'Section VI.E',
                'handbook_page' => $case->confirmedOffense?->handbook_page ?? '',
                'incident_date' => $incidentDate,
                'scheduled_at' => $data['scheduled_conference_at'],
                'scheduled_location' => $data['conference_location'],
                'additional_content' => [
                    'thru' => $data['thru'] ?? null,
                    'upcc_referral_id' => $referral->id,
                    'proceedings_type' => $data['proceedings_type'] ?? 'full_hearing',
                ],
                'is_voided' => false,
                'created_at' => $now,
            ]);

            AuditLog::create([
                'user_id' => $coordinatorId,
                'action' => 'create',
                'module' => 'upcc_referrals',
                'record_id' => $referral->id,
                'new_values' => [
                    'case_number' => $case->case_number,
                    'scheduled_conference_at' => $data['scheduled_conference_at'],
                    'conference_location' => $data['conference_location'],
                    'proceedings_type' => $data['proceedings_type'] ?? 'full_hearing',
                ],
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            $case->student?->notify(
                new UpccConferenceScheduledNotification($referral->fresh(['case']))
            );
        });

        return $referral;
    }

    public function rescheduleUpcc(
        DisciplinaryCase $case,
        UpccReferral $referral,
        array $data,
        string $reason,
        int $coordinatorId
    ): UpccReferral {
        DB::transaction(function () use ($case, $referral, $data, $reason, $coordinatorId) {
            $oldValues = [
                'scheduled_conference_at' => $referral->scheduled_conference_at?->toDateTimeString(),
                'conference_location' => $referral->conference_location,
            ];

            $referral->update([
                'scheduled_conference_at' => $data['scheduled_conference_at'],
                'conference_location' => $data['conference_location'],
            ]);

            $priorNotice = $case->notices()
                ->where('notice_type', 'notice_of_hearing')
                ->where('is_voided', false)
                ->latest('issued_at')
                ->first();

            if ($priorNotice) {
                $now = now();

                $priorNotice->update([
                    'is_voided' => true,
                    'voided_by' => $coordinatorId,
                    'voided_at' => $now,
                    'void_reason' => $reason,
                ]);

                CaseNotice::create([
                    'case_id' => $case->id,
                    'notice_type' => 'notice_of_hearing',
                    'issued_by' => $coordinatorId,
                    'issued_at' => $now,
                    'student_handbook_id' => $priorNotice->student_handbook_id,
                    'handbook_section' => $priorNotice->handbook_section,
                    'handbook_page' => $priorNotice->handbook_page,
                    'incident_date' => $priorNotice->incident_date,
                    'scheduled_at' => $data['scheduled_conference_at'],
                    'scheduled_location' => $data['conference_location'],
                    'additional_content' => $priorNotice->additional_content,
                    'is_voided' => false,
                    'created_at' => $now,
                ]);
            }

            AuditLog::create([
                'user_id' => $coordinatorId,
                'action' => 'update',
                'module' => 'upcc_referrals',
                'record_id' => $referral->id,
                'old_values' => $oldValues,
                'new_values' => [
                    'scheduled_conference_at' => $data['scheduled_conference_at'],
                    'conference_location' => $data['conference_location'],
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $case->student?->notify(
                new UpccConferenceScheduledNotification($referral->fresh(['case']), true)
            );
        });

        return $referral->fresh();
    }

    public function recordAttendance(
        UpccReferral $referral,
        bool $attended,
        int $coordinatorId
    ): void {
        $recordedAt = now();

        $referral->update([
            'student_attended' => $attended,
            'student_no_show_recorded_by' => $attended ? null : $coordinatorId,
            'proceedings_started_at' => $referral->proceedings_started_at ?? $recordedAt,
            'status' => 'in_progress',
            'updated_at' => $recordedAt,
        ]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'update',
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'new_values' => [
                'student_attended' => $attended,
                'proceedings_started_at' => $recordedAt->toDateTimeString(),
                'status' => 'in_progress',
            ],
            'ip_address' => request()->ip(),
            'created_at' => $recordedAt,
        ]);
    }

    public function recordDecision(
        UpccReferral $referral,
        string $decisionSummary,
        string $outcome,
        int $coordinatorId
    ): void {
        $now = now();

        $referral->update([
            'decision_summary' => $decisionSummary,
            'outcome' => $outcome,
            'proceedings_ended_at' => $now,
            'decision_issued_at' => $now,
            'final_report_submitted_at' => $now,
            'submitted_to_presidents_council_at' => $now,
            'status' => 'concluded',
            'updated_at' => $now,
        ]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'update',
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'new_values' => [
                'decision_summary' => $decisionSummary,
                'outcome' => $outcome,
                'proceedings_ended_at' => $now->toDateTimeString(),
                'decision_issued_at' => $now->toDateTimeString(),
                'final_report_submitted_at' => $now->toDateTimeString(),
                'submitted_to_presidents_council_at' => $now->toDateTimeString(),
                'status' => 'concluded',
            ],
            'ip_address' => request()->ip(),
            'created_at' => $now,
        ]);
    }

    /**
     * The UPCC panel found no violation. Skips Council action, distribution/appeal,
     * and mandatory sanction resolution — the case closes immediately with the
     * decision itself serving as the (student-visible) closure notice.
     */
    public function closeAsNoViolation(UpccReferral $referral, int $coordinatorId): void
    {
        $now = now();

        DB::transaction(function () use ($referral, $coordinatorId, $now): void {
            $case = $referral->case;

            $referral->update([
                'decision_distributed_at' => $now,
                'decision_final_at' => $now,
                'updated_at' => $now,
            ]);

            $case->update([
                'status' => 'dismissed',
                'dismissal_reason' => $referral->decision_summary,
                'resolved_by' => $coordinatorId,
                'resolved_at' => $now,
            ]);

            AuditLog::create([
                'user_id' => $coordinatorId,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'new_values' => [
                    'status' => 'dismissed',
                    'source' => 'upcc_no_violation',
                ],
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            $freshReferral = $referral->fresh(['case.student', 'case.incidentReport.reportedByUser']);

            $freshReferral?->case?->student?->notify(
                new UpccDecisionNotification($freshReferral, 'cleared')
            );

            $complainant = $freshReferral?->case?->incidentReport?->reportedByUser;
            if ($complainant) {
                $complainant->notify(
                    new UpccDecisionAvailableToComplainantNotification($freshReferral)
                );
            }
        });

        app(RiskScoringService::class)
            ->computeForStudent($referral->case->student_id, 'case_dismissed', $coordinatorId);
    }

    public function recordCouncilAction(
        UpccReferral $referral,
        string $action,
        ?string $notes,
        int $coordinatorId
    ): void {
        $now = now();

        $referral->update([
            'presidents_council_action' => $action,
            'presidents_council_notes' => $notes,
            'presidents_council_acted_at' => $now,
            'updated_at' => $now,
        ]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'update',
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'new_values' => [
                'presidents_council_action' => $action,
                'presidents_council_notes' => $notes,
            ],
            'ip_address' => request()->ip(),
            'created_at' => $now,
        ]);
    }

    public function distributeDecision(
        UpccReferral $referral,
        int $coordinatorId
    ): void {
        $distributedAt = now();
        $appealWindowEnd = MajorOffenseWorkflow::appealDeadline($distributedAt);

        DB::transaction(function () use ($referral, $coordinatorId, $distributedAt, $appealWindowEnd): void {
            $referral->update([
                'decision_distributed_at' => $distributedAt,
                'appeal_window_start' => $distributedAt->toDateString(),
                'appeal_window_end' => $appealWindowEnd->toDateString(),
                'updated_at' => $distributedAt,
            ]);

            AuditLog::create([
                'user_id' => $coordinatorId,
                'action' => 'update',
                'module' => 'upcc_referrals',
                'record_id' => $referral->id,
                'new_values' => [
                    'decision_distributed_at' => $distributedAt->toDateTimeString(),
                    'appeal_window_start' => $distributedAt->toDateString(),
                    'appeal_window_end' => $appealWindowEnd->toDateString(),
                ],
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            $freshReferral = $referral->fresh(['case.student', 'case.incidentReport.reportedByUser']);

            $freshReferral?->case?->student?->notify(
                new UpccDecisionNotification($freshReferral, 'distributed')
            );

            $complainant = $freshReferral?->case?->incidentReport?->reportedByUser;
            if ($complainant) {
                $complainant->notify(
                    new UpccDecisionAvailableToComplainantNotification($freshReferral)
                );
            }
        });
    }

    public function fileAppeal(
        UpccReferral $referral,
        ?string $appealNotes,
        int $filedBy,
        string $source = 'coordinator_entry'
    ): void {
        $filedAt = now();

        $referral->update([
            'appeal_filed' => true,
            'appeal_notes' => $appealNotes,
            'appeal_filed_by' => $filedBy,
            'appeal_filed_at' => $filedAt,
            'appeal_source' => $source,
            'status' => 'reconsideration_filed',
            'updated_at' => $filedAt,
        ]);

        AuditLog::create([
            'user_id' => $filedBy,
            'action' => 'update',
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'new_values' => [
                'appeal_filed' => true,
                'appeal_filed_at' => $filedAt->toDateTimeString(),
                'appeal_source' => $source,
                'status' => 'reconsideration_filed',
            ],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function storeAppealAttachment(
        UploadedFile $file,
        UpccReferral $referral,
        int $uploadedBy
    ): UpccAppealAttachment {
        $extension = strtolower($file->getClientOriginalExtension());
        $baseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'appeal-attachment';
        $displayName = "{$baseName}.{$extension}";
        $storedName = "{$baseName}-".Str::uuid().".{$extension}";
        $directory = "upcc-appeal-attachments/{$referral->id}";
        $storagePath = "{$directory}/{$storedName}";
        $security = app(AttachmentSecurityService::class)->inspect($file);

        Storage::disk('local')->putFileAs($directory, $file, $storedName);

        return UpccAppealAttachment::create([
            'upcc_referral_id' => $referral->id,
            'uploaded_by' => $uploadedBy,
            'file_name' => $displayName,
            'file_path' => $storagePath,
            'file_type' => $extension,
            'file_size_bytes' => $file->getSize(),
            'file_hash' => $security['file_hash'],
            'scan_status' => $security['scan_status'],
            'scanned_at' => $security['scanned_at'],
        ]);
    }

    public function notifyCoordinatorsOfAppeal(UpccReferral $referral): void
    {
        $notified = collect();

        if ($referral->referredBy) {
            $referral->referredBy->notify(new UpccAppealSubmittedNotification($referral));
            $notified->push((int) $referral->referredBy->id);
        }

        User::query()
            ->where('role', 'discipline_coordinator')
            ->where('status', 'active')
            ->whereNotIn('id', $notified)
            ->get()
            ->each(fn (User $coordinator) => $coordinator->notify(
                new UpccAppealSubmittedNotification($referral)
            ));
    }

    public function waiveAppealAndFinalize(
        DisciplinaryCase $case,
        UpccReferral $referral,
        int $studentId
    ): void {
        DB::transaction(function () use ($case, $referral, $studentId) {
            $waivedAt = now();

            $referral->update([
                'appeal_waived_by' => $studentId,
                'appeal_waived_at' => $waivedAt,
                'updated_at' => $waivedAt,
            ]);

            AuditLog::create([
                'user_id' => $studentId,
                'action' => 'update',
                'module' => 'upcc_referrals',
                'record_id' => $referral->id,
                'new_values' => [
                    'appeal_waived_by' => $studentId,
                    'appeal_waived_at' => $waivedAt->toDateTimeString(),
                ],
                'reason' => 'Student expressly selected No Appeal during the open appeal window.',
                'ip_address' => request()->ip(),
                'created_at' => $waivedAt,
            ]);

            $this->finalizeDecision(
                $case,
                $referral,
                null,
                'Decision finalized after the student expressly waived the right to appeal.'
            );
        });
    }

    public function finalizeDecision(
        DisciplinaryCase $case,
        UpccReferral $referral,
        ?int $coordinatorId,
        string $reason = 'Decision finalized after appeal-window review.'
    ): void {
        DB::transaction(function () use ($case, $referral, $coordinatorId, $reason) {
            $now = now();

            $referral->update([
                'decision_final_at' => $now,
                'records_filed_at' => $now,
                'updated_at' => $now,
            ]);

            $case->update(['status' => 'pending_resolution']);

            AuditLog::create([
                'user_id' => $coordinatorId,
                'action' => 'update',
                'module' => 'cases',
                'record_id' => $case->id,
                'old_values' => ['status' => 'pending_upcc'],
                'new_values' => [
                    'status' => 'pending_resolution',
                    'records_filed_at' => $now->toDateTimeString(),
                ],
                'reason' => $reason,
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            $referral->case?->student?->notify(
                new UpccDecisionNotification($referral->fresh(['case']), 'finalized')
            );
        });
    }

    public function recordReconsiderationOutcome(
        UpccReferral $referral,
        string $outcome,
        string $outcomeSummary,
        int $coordinatorId
    ): void {
        $referral->update([
            'appeal_notes' => $referral->appeal_notes
                ."\n\n[Reconsideration Outcome — "
                .now()->format('M d, Y')
                .']: '.$outcomeSummary,
            'status' => 'concluded',
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'update',
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'new_values' => [
                'reconsideration_outcome' => $outcome,
                'outcome_summary' => $outcomeSummary,
            ],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);

        $referral->case?->student?->notify(
            new UpccDecisionNotification($referral->fresh(['case']), 'reconsideration_resolved')
        );
    }

    public function approveVoid(DisciplinaryCase $case, int $coordinatorId): void
    {
        $now = now();

        $case->update([
            'status' => 'voided',
            'voided_at' => $now,
            'void_coordinator_approved_by' => $coordinatorId,
            'void_coordinator_approved_at' => $now,
        ]);

        Sanction::whereHas('resolution', fn ($q) => $q->where('case_id', $case->id))
            ->whereIn('status', ['pending', 'in_progress'])
            ->update(['status' => 'waived', 'updated_at' => $now]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'void',
            'module' => 'cases',
            'record_id' => $case->id,
            'new_values' => [
                'status' => 'voided',
                'coordinator_approved' => true,
            ],
            'ip_address' => request()->ip(),
            'created_at' => $now,
        ]);

        app(RiskScoringService::class)
            ->computeForStudent($case->student_id, 'case_voided', $coordinatorId);
    }

    public function rejectVoid(
        DisciplinaryCase $case,
        string $reason,
        int $coordinatorId
    ): void {
        // Preserve the original void requester and reason for audit history.
        $case->update([
            'void_requires_coordinator_approval' => false,
        ]);

        AuditLog::create([
            'user_id' => $coordinatorId,
            'action' => 'override',
            'module' => 'cases',
            'record_id' => $case->id,
            'new_values' => ['void_rejected' => true],
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
