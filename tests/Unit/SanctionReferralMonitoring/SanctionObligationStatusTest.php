<?php

declare(strict_types=1);

namespace Tests\Unit\SanctionReferralMonitoring;

use App\Models\Sanction;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Module 5 - Sanction and Referral Monitoring
 * Component: Sanction Obligation Status (App\Models\Sanction)
 * Test Cycle No. 1 - 5 test cases.
 */
class SanctionObligationStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: outstanding obligations count as active. */
    public function test_outstanding_obligations_are_active(): void
    {
        foreach (['pending', 'in_progress', 'non_compliant'] as $status) {
            $this->assertTrue(
                (new Sanction(['status' => $status]))->isActive(),
                "A [{$status}] sanction should still be active."
            );
        }
    }

    /** Scenario 2 - Boundary: completed or waived obligations are no longer active. */
    public function test_settled_obligations_are_no_longer_active(): void
    {
        $this->assertFalse((new Sanction(['status' => 'completed']))->isActive());
        $this->assertFalse((new Sanction(['status' => 'waived']))->isActive());
    }

    /** Scenario 3 - Boundary: a sanction is not overdue on its own due date. */
    public function test_a_sanction_is_not_overdue_on_its_due_date_but_is_the_next_day(): void
    {
        $sanction = new Sanction(['status' => 'pending', 'due_date' => '2026-08-14']);

        Carbon::setTestNow('2026-08-14 23:59:00');
        $this->assertFalse($sanction->isOverdue());

        Carbon::setTestNow('2026-08-15 00:01:00');
        $this->assertTrue($sanction->isOverdue());
    }

    /** Scenario 4 - Failure: completed sanctions and sanctions without a due date never go overdue. */
    public function test_completed_sanctions_and_sanctions_without_a_due_date_never_go_overdue(): void
    {
        Carbon::setTestNow('2026-12-31 12:00:00');

        $completed = new Sanction(['status' => 'completed', 'due_date' => '2026-01-01']);
        $noDueDate = new Sanction(['status' => 'pending', 'due_date' => null]);

        $this->assertFalse($completed->isOverdue());
        $this->assertFalse($noDueDate->isOverdue());
    }

    /** Scenario 5 - Normal / invalid: every documented sanction type has a readable label. */
    public function test_every_sanction_type_has_a_readable_label(): void
    {
        $this->assertSame('University Service', (new Sanction(['sanction_type' => 'university_service']))->typeLabel());
        $this->assertSame('Counseling Referral', (new Sanction(['sanction_type' => 'counseling_referral']))->typeLabel());
        $this->assertSame('Discipline Education Program', (new Sanction(['sanction_type' => 'discipline_education']))->typeLabel());
        $this->assertSame('Non-Readmission', (new Sanction(['sanction_type' => 'non_readmission']))->typeLabel());
        $this->assertSame('Community Restitution', (new Sanction(['sanction_type' => 'community_restitution']))->typeLabel());
    }
}
