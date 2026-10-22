<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use Tests\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Disciplinary Case Record State Rules (App\Models\DisciplinaryCase)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The UPCC referral relation is pre-loaded on the in-memory model so the
 * state rules are exercised without touching the database.
 */
class DisciplinaryCaseRecordStateTest extends TestCase
{
    /** Scenario 1 - Normal: a case still in progress can be voided. */
    public function test_an_in_progress_case_is_voidable(): void
    {
        foreach (['open', 'pending_nte', 'awaiting_explanation', 'under_review', 'pending_upcc', 'pending_resolution'] as $status) {
            $this->assertTrue(
                (new DisciplinaryCase(['status' => $status]))->isVoidable(),
                "A [{$status}] case should still be voidable."
            );
        }
    }

    /** Scenario 2 - Failure: closed cases can no longer be voided. */
    public function test_a_closed_case_is_not_voidable(): void
    {
        foreach (['resolved', 'dismissed', 'voided'] as $status) {
            $this->assertFalse(
                (new DisciplinaryCase(['status' => $status]))->isVoidable(),
                "A [{$status}] case must not be voidable."
            );
        }
    }

    /** Scenario 3 - Boundary: coordinator approval is required only past the early stages. */
    public function test_coordinator_void_approval_is_required_only_after_the_early_stages(): void
    {
        $this->assertFalse((new DisciplinaryCase(['status' => 'open']))->requiresCoordinatorVoidApproval());
        $this->assertFalse((new DisciplinaryCase(['status' => 'pending_nte']))->requiresCoordinatorVoidApproval());
        $this->assertTrue((new DisciplinaryCase(['status' => 'awaiting_explanation']))->requiresCoordinatorVoidApproval());
        $this->assertTrue((new DisciplinaryCase(['status' => 'pending_upcc']))->requiresCoordinatorVoidApproval());
    }

    /** Scenario 4 - Normal: the student-facing state mirrors the UPCC referral progress. */
    public function test_the_student_workflow_state_reflects_the_upcc_referral_progress(): void
    {
        $this->assertSame('under_review', $this->caseWithReferral('under_review', null)->studentWorkflowState());
        $this->assertSame('upcc_awaiting_schedule', $this->caseWithReferral('pending_upcc', null)->studentWorkflowState());
        $this->assertSame(
            'upcc_scheduled',
            $this->caseWithReferral('pending_upcc', new UpccReferral(['status' => 'scheduled']))->studentWorkflowState()
        );
        $this->assertSame(
            'upcc_in_progress',
            $this->caseWithReferral('pending_upcc', new UpccReferral(['status' => 'in_progress']))->studentWorkflowState()
        );
    }

    /** Scenario 5 - Boundary: an unmapped or missing sanction category falls back to the neutral badge. */
    public function test_sanction_category_badges_cover_every_category_and_fall_back_safely(): void
    {
        $this->assertSame('prism-badge-blue', DisciplinaryCase::sanctionCategoryBadgeClass(1));
        $this->assertSame('prism-badge-violet', DisciplinaryCase::sanctionCategoryBadgeClass(2));
        $this->assertSame('prism-badge-amber', DisciplinaryCase::sanctionCategoryBadgeClass(3));
        $this->assertSame('prism-badge-orange', DisciplinaryCase::sanctionCategoryBadgeClass(4));
        $this->assertSame('prism-badge-red', DisciplinaryCase::sanctionCategoryBadgeClass(5));
        $this->assertSame('prism-badge-gray', DisciplinaryCase::sanctionCategoryBadgeClass(null));
        $this->assertSame('prism-badge-gray', DisciplinaryCase::sanctionCategoryBadgeClass(9));
    }

    public function test_admission_badges_are_colour_coded_by_outcome(): void
    {
        $this->assertSame(['Full Admission', 'prism-badge-green'], \App\Models\CaseExplanation::admissionBadge('full_admission'));
        $this->assertSame(['Partial Admission', 'prism-badge-amber'], \App\Models\CaseExplanation::admissionBadge('partial_admission'));
        $this->assertSame(['Full Denial', 'prism-badge-red'], \App\Models\CaseExplanation::admissionBadge('full_denial'));
        $this->assertSame(['No Response', 'prism-badge-gray'], \App\Models\CaseExplanation::admissionBadge('no_response'));
        $this->assertSame(['—', 'prism-badge-gray'], \App\Models\CaseExplanation::admissionBadge(null));
    }

    private function caseWithReferral(string $status, ?UpccReferral $referral): DisciplinaryCase
    {
        $case = new DisciplinaryCase(['status' => $status]);
        $case->setRelation('upccReferral', $referral);

        return $case;
    }
}
