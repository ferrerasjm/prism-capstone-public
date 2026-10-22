<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\AuditLog;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Escalation Alert Dismissal (App\Services\CaseService::acknowledgeEscalation)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The alternative branch to EscalationApplicationTest: an officer who
 * reviews a habitual-disregard flag and decides not to escalate records
 * that judgment call, with a required reason, instead of adding MAJOR-19.
 */
class EscalationAcknowledgmentTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: acknowledging a flagged case records who dismissed it and when. */
    public function test_acknowledging_a_flagged_case_records_who_dismissed_it_and_when(): void
    {
        [$case, $officer] = $this->flaggedCase();

        (new CaseService)->acknowledgeEscalation($case, $officer->id, 'Reviewed the three findings; the third was already dismissed on appeal.');

        $fresh = $case->fresh();
        $this->assertSame($officer->id, $fresh->escalation_acknowledged_by);
        $this->assertNotNull($fresh->escalation_acknowledged_at);
    }

    /** Scenario 2 - Normal: acknowledgment does not add a MAJOR-19 charge or change the case type. */
    public function test_acknowledgment_does_not_charge_major_19_or_change_the_case_type(): void
    {
        [$case, $officer] = $this->flaggedCase();

        (new CaseService)->acknowledgeEscalation($case, $officer->id, 'The repeat findings involve a different student on review.');

        $fresh = $case->fresh();
        $this->assertSame('minor', $fresh->case_type);
        $this->assertSame(0, $fresh->caseOffenses()->count());
    }

    /** Scenario 3 - Normal: the dismissal reason and the flag it dismissed are written to the audit trail. */
    public function test_the_dismissal_reason_and_the_flag_it_dismissed_are_audited(): void
    {
        [$case, $officer] = $this->flaggedCase();
        $reason = 'Coordinator confirmed one of the three counted offenses was voided, dropping the count below threshold.';

        (new CaseService)->acknowledgeEscalation($case, $officer->id, $reason);

        $log = AuditLog::where('module', 'cases')
            ->where('record_id', $case->id)
            ->where('action', 'override')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame($reason, $log->reason);
        $this->assertTrue($log->old_values['escalation_flagged']);
        $this->assertSame(
            'Third offense of the same type in the current escalation cycle.',
            $log->old_values['escalation_flag_reason']
        );
        $this->assertTrue($log->new_values['escalation_dismissed']);
    }

    /** Scenario 4 - Boundary: acknowledgment leaves the original escalation_flagged value and reason untouched on the case. */
    public function test_acknowledgment_leaves_the_flagged_state_and_reason_on_the_case_untouched(): void
    {
        [$case, $officer] = $this->flaggedCase();

        (new CaseService)->acknowledgeEscalation($case, $officer->id, 'Kept for the record; no further action warranted.');

        $fresh = $case->fresh();
        $this->assertTrue((bool) $fresh->escalation_flagged);
        $this->assertSame('Third offense of the same type in the current escalation cycle.', $fresh->escalation_flag_reason);
    }

    /** Scenario 5 - Boundary: acknowledging a case that was never flagged still records the dismissal without error. */
    public function test_acknowledging_a_never_flagged_case_still_records_the_dismissal(): void
    {
        [$case, $officer] = $this->openCase();

        (new CaseService)->acknowledgeEscalation($case, $officer->id, 'No escalation was pending; entry made for completeness.');

        $fresh = $case->fresh();
        $this->assertSame($officer->id, $fresh->escalation_acknowledged_by);
        $this->assertNotNull($fresh->escalation_acknowledged_at);
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function flaggedCase(): array
    {
        [$case, $officer] = $this->openCase();
        $case->update([
            'escalation_flagged' => true,
            'escalation_flag_reason' => 'Third offense of the same type in the current escalation cycle.',
        ]);

        return [$case->fresh(), $officer];
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function openCase(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Reviewed',
            'email' => 'escalation-ack.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Acknowledging',
            'email' => 'escalation-ack.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7601',
            'student_id' => $student->id,
            'case_type' => 'minor',
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        return [$case, $officer];
    }
}
