<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\CounselingReferral;
use App\Models\Sanction;
use App\Models\User;
use App\Notifications\GuidanceReferralIncompleteNotification;
use App\Notifications\GuidanceReferralStatusNotification;
use Illuminate\Support\Facades\DB;

/**
 * Routes counseling referrals to an eligible Guidance Officer and tracks
 * their lifecycle (acknowledged, completed, incomplete). Officer matching
 * is scoped so a student is referred to someone permitted to see their
 * case.
 */
class GuidanceReferralService
{
    /**
     * Guidance Officers whose assigned departments cover the given student. Falls back to
     * every active Guidance Officer when the student has no matching department (unset
     * program/year level) or no officer has been assigned to that department yet, so a
     * referral is never left impossible to assign because of incomplete setup data.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function eligibleOfficersForStudent(?User $student): \Illuminate\Support\Collection
    {
        $matchingUnitIds = $student?->studentProfile?->matchingAcademicUnitIds() ?? collect();

        $base = User::where('role', 'guidance_officer')->where('status', 'active');

        if ($matchingUnitIds->isNotEmpty()) {
            $scoped = (clone $base)->where(function ($query) use ($matchingUnitIds): void {
                $query->whereHas('guidanceAcademicUnits', fn ($q) => $q->whereIn('academic_units.id', $matchingUnitIds))
                    ->orWhereIn('academic_unit_id', $matchingUnitIds);
            })->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);

            if ($scoped->isNotEmpty()) {
                return $scoped;
            }
        }

        return $base->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
    }

    /**
     * The single Guidance Officer a new referral should go to, chosen automatically by
     * department coverage rather than asked of the Discipline Officer -- the department
     * relationship already determines who should handle it, so making a person pick from
     * a dropdown that usually has one obvious answer was workflow for its own sake.
     *
     * When more than one officer covers the same department, the tie breaks toward
     * whoever currently has the fewest pending referrals, so coverage load spreads out
     * rather than always landing on the same person; a further tie falls back to the
     * name-ordering eligibleOfficersForStudent() already returns, so the pick is
     * deterministic. Returns null only when no active Guidance Officer exists at all.
     */
    public function matchOfficerForStudent(?User $student): ?User
    {
        $eligible = $this->eligibleOfficersForStudent($student);

        if ($eligible->count() <= 1) {
            return $eligible->first();
        }

        $pendingCounts = CounselingReferral::query()
            ->where('status', 'pending')
            ->whereIn('guidance_officer_id', $eligible->pluck('id'))
            ->selectRaw('guidance_officer_id, COUNT(*) as pending_count')
            ->groupBy('guidance_officer_id')
            ->pluck('pending_count', 'guidance_officer_id');

        return $eligible
            ->sortBy(fn (User $officer): int => (int) ($pendingCounts[$officer->id] ?? 0))
            ->first();
    }

    /**
     * Raises a counseling referral off an officer's finding on a sanction other than
     * probation's own resolution-time referral -- currently, an Evaluation period the
     * officer closes as "Needs Further Intervention". Without this, that finding was
     * stored on the sanction and shown on one page, with no consequence for the student
     * at all.
     *
     * Assigned automatically to whichever Guidance Officer covers the student's
     * department (see matchOfficerForStudent()) rather than left for an officer to pick.
     */
    public function raiseFromFinding(Sanction $sourceSanction, string $reason, int $raisedBy): CounselingReferral
    {
        return DB::transaction(function () use ($sourceSanction, $reason, $raisedBy): CounselingReferral {
            $now = now();

            $referralSanction = Sanction::create([
                'case_resolution_id' => $sourceSanction->case_resolution_id,
                'student_id' => $sourceSanction->student_id,
                'sanction_type' => 'counseling_referral',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $referral = CounselingReferral::create([
                'sanction_id' => $referralSanction->id,
                'student_id' => $sourceSanction->student_id,
                'guidance_officer_id' => $this->matchOfficerForStudent($sourceSanction->student)?->id,
                'referred_by' => $raisedBy,
                'referred_at' => $now,
                'referral_reason' => $reason,
                // An adverse finding on an intervention already in progress reads as more
                // urgent than a fresh, routine referral off a first resolution.
                'urgency' => 'priority',
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            AuditLog::create([
                'user_id' => $raisedBy,
                'action' => 'create',
                'module' => 'counseling_referrals',
                'record_id' => $referral->id,
                'new_values' => [
                    'source_sanction_id' => $sourceSanction->id,
                    'source_sanction_type' => $sourceSanction->sanction_type,
                    'referral_reason' => $reason,
                ],
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            return $referral;
        });
    }

    /**
     * Whether the case resolution behind this sanction already has an unresolved
     * counseling referral -- from the resolution form's own checkbox, or from an earlier
     * finding. Guards raiseFromFinding() against opening a second, redundant referral.
     */
    public function hasPendingReferral(?int $caseResolutionId): bool
    {
        if (! $caseResolutionId) {
            return false;
        }

        return Sanction::where('case_resolution_id', $caseResolutionId)
            ->where('sanction_type', 'counseling_referral')
            ->where('status', 'pending')
            ->exists();
    }

    public function acknowledge(
        CounselingReferral $referral,
        int $officerId
    ): void {
        if ($referral->status !== 'pending') {
            throw new \RuntimeException('Only pending referrals can be acknowledged.');
        }

        if ((int) $referral->guidance_officer_id !== $officerId) {
            throw new \RuntimeException('Only the assigned Guidance Officer can acknowledge this referral.');
        }

        $referral->update(['acknowledged_at' => now()]);

        AuditLog::create([
            'user_id' => $officerId,
            'action' => 'update',
            'module' => 'counseling_referrals',
            'record_id' => $referral->id,
            'new_values' => ['acknowledged_at' => now()->toDateTimeString()],
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function markCompleted(
        CounselingReferral $referral,
        int $officerId
    ): void {
        $this->assertCanUpdateStatus($referral, $officerId);

        DB::transaction(function () use ($referral, $officerId): void {
            $now = now();
            $oldStatus = $referral->status;
            $sanction = $referral->sanction;

            $referral->update([
                'status' => 'completed',
                'completed_at' => $now,
                'confirmed_by' => $officerId,
                'incomplete_reason' => null,
            ]);

            Sanction::whereKey($referral->sanction_id)->update([
                'status' => 'completed',
                'completed_at' => $now,
                'verified_by' => $officerId,
                'updated_at' => $now,
            ]);

            // A re-referral creates a fresh Sanction row rather than reusing the
            // one that came back non-compliant (see raiseFromFinding()), so the
            // earlier failed attempt(s) on this same case are superseded, not
            // resolved, once the requirement is finally met here. Close them out
            // too, otherwise they sit non-compliant forever and a registration
            // hold raised off them (see markIncomplete()) could never clear.
            if ($sanction?->case_resolution_id) {
                Sanction::where('case_resolution_id', $sanction->case_resolution_id)
                    ->where('sanction_type', 'counseling_referral')
                    ->where('status', 'non_compliant')
                    ->update([
                        'status' => 'completed',
                        'completed_at' => $now,
                        'verified_by' => $officerId,
                        'updated_at' => $now,
                    ]);
            }

            AuditLog::create([
                'user_id' => $officerId,
                'action' => 'update',
                'module' => 'counseling_referrals',
                'record_id' => $referral->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'completed'],
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            app(RegistrationHoldService::class)->liftIfClear($referral->student_id);

            $referral->student?->notify(new GuidanceReferralStatusNotification($referral->fresh()));
        });
    }

    public function markIncomplete(
        CounselingReferral $referral,
        string $reason,
        int $officerId
    ): void {
        $this->assertCanUpdateStatus($referral, $officerId);

        DB::transaction(function () use ($referral, $reason, $officerId): void {
            $now = now();
            $oldStatus = $referral->status;
            $sanction = $referral->sanction;

            $referral->update([
                'status' => 'incomplete',
                'incomplete_reason' => $reason,
                'completed_at' => null,
                'confirmed_by' => $officerId,
            ]);

            Sanction::whereKey($referral->sanction_id)->update([
                'status' => 'non_compliant',
                'completed_at' => null,
                'verified_by' => $officerId,
                'updated_at' => $now,
            ]);

            AuditLog::create([
                'user_id' => $officerId,
                'action' => 'update',
                'module' => 'counseling_referrals',
                'record_id' => $referral->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'incomplete', 'administrative_reason' => $reason],
                'ip_address' => request()->ip(),
                'created_at' => $now,
            ]);

            app(RiskScoringService::class)->computeForStudent(
                $referral->student_id,
                'counseling_non_compliance',
                $officerId
            );

            if ($sanction?->case_resolution_id
                && $this->consecutiveNonCompliantReferralCount($sanction->case_resolution_id)
                    >= (int) config('discipline.counseling_hold_threshold', 2)
            ) {
                app(RegistrationHoldService::class)->raise(
                    $referral->student_id,
                    'Automatic Hold: '.config('discipline.counseling_hold_threshold', 2)
                        .' consecutive non-compliant counseling referrals on this case.',
                    $officerId
                );
            }

            $referral->student?->notify(new GuidanceReferralStatusNotification($referral->fresh()));
            $this->notifyOfficersOfIncompleteReferral($referral->fresh());
        });
    }

    /**
     * How many counseling-referral sanctions on this case have come back
     * non-compliant in a row, most recent first, stopping at the first one
     * that isn't -- e.g. a completed referral earlier in the same case. Used
     * to decide whether repeated counseling non-compliance should raise a
     * registration hold; see config('discipline.counseling_hold_threshold').
     */
    private function consecutiveNonCompliantReferralCount(int $caseResolutionId): int
    {
        $statuses = Sanction::where('case_resolution_id', $caseResolutionId)
            ->where('sanction_type', 'counseling_referral')
            ->orderByDesc('created_at')
            ->pluck('status');

        $count = 0;

        foreach ($statuses as $status) {
            if ($status !== 'non_compliant') {
                break;
            }

            $count++;
        }

        return $count;
    }

    /**
     * Alerts every Discipline Officer who can see this sanction (department-scoped,
     * same rule as the rest of the officer views) that the referral came back
     * incomplete -- otherwise the resulting Non-Compliant sanction sat unremarked
     * until an officer happened to reopen it.
     */
    private function notifyOfficersOfIncompleteReferral(CounselingReferral $referral): void
    {
        $sanction = $referral->sanction;

        if (! $sanction) {
            return;
        }

        User::query()
            ->where('role', 'discipline_officer')
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $officer): bool => $sanction->isVisibleToOfficer($officer))
            ->each(fn (User $officer) => $officer->notify(new GuidanceReferralIncompleteNotification($referral)));
    }

    private function assertCanUpdateStatus(CounselingReferral $referral, int $officerId): void
    {
        if ($referral->status !== 'pending') {
            throw new \RuntimeException('Only pending referrals can be updated.');
        }

        if ((int) $referral->guidance_officer_id !== $officerId) {
            throw new \RuntimeException('Only the assigned Guidance Officer can update this referral.');
        }

        if (! $referral->acknowledged_at) {
            throw new \RuntimeException('Acknowledge the referral before updating its completion status.');
        }
    }
}
