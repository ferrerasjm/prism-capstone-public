<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Sanction Category Confirmation (App\Services\CaseService::confirmSanction)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Distinct from SanctionRecommendationService (which only reads the matrix
 * and proposes a category), confirmSanction() is the officer's decision that
 * fixes the assigned_sanction_category on the case, with an optional written
 * justification when the assigned category departs from the recommendation.
 */
class SanctionCategoryConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: confirming the recommended category sets it on the case with no override reason. */
    public function test_confirming_the_recommended_category_sets_it_without_an_override_reason(): void
    {
        [$case, $officer] = $this->openCase();

        (new CaseService)->confirmSanction($case, 2, $officer->id);

        $fresh = $case->fresh();
        $this->assertSame(2, $fresh->assigned_sanction_category);
        $this->assertNull($fresh->sanction_override_reason);
    }

    /** Scenario 2 - Normal: assigning a category above the recommendation stores the required override reason. */
    public function test_assigning_a_higher_category_stores_the_override_reason(): void
    {
        [$case, $officer] = $this->openCase();
        $reason = 'Prior sanction history for the same offense type warrants the higher category.';

        (new CaseService)->confirmSanction($case, 3, $officer->id, $reason);

        $fresh = $case->fresh();
        $this->assertSame(3, $fresh->assigned_sanction_category);
        $this->assertSame($reason, $fresh->sanction_override_reason);
    }

    /** Scenario 3 - Boundary: reassigning an already-confirmed category overwrites the prior value and reason. */
    public function test_reassigning_the_category_overwrites_the_prior_value_and_reason(): void
    {
        [$case, $officer] = $this->openCase();
        (new CaseService)->confirmSanction($case, 3, $officer->id, 'Initial escalation basis.');

        (new CaseService)->confirmSanction($case->fresh(), 1, $officer->id, 'Corrected after coordinator review lowered the finding.');

        $fresh = $case->fresh();
        $this->assertSame(1, $fresh->assigned_sanction_category);
        $this->assertSame('Corrected after coordinator review lowered the finding.', $fresh->sanction_override_reason);
    }

    /** Scenario 4 - Normal: the confirmation and its reason are recorded on the audit trail with the officer's old and new values. */
    public function test_the_confirmation_is_recorded_on_the_audit_trail(): void
    {
        [$case, $officer] = $this->openCase();
        $case->update(['assigned_sanction_category' => 2]);
        $reason = 'Repeat offense within the same term justifies category 4.';

        (new CaseService)->confirmSanction($case->fresh(), 4, $officer->id, $reason);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'cases',
            'record_id' => $case->id,
            'user_id' => $officer->id,
            'action' => 'update',
        ]);

        $log = \App\Models\AuditLog::where('module', 'cases')
            ->where('record_id', $case->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(2, $log->old_values['assigned_sanction_category']);
        $this->assertSame(4, $log->new_values['assigned_sanction_category']);
        $this->assertSame($reason, $log->new_values['override_reason']);
    }

    /** Scenario 5 - Boundary: category 5 (expulsion) is accepted the same as any other category, without special-casing. */
    public function test_the_highest_sanction_category_is_accepted_like_any_other(): void
    {
        [$case, $officer] = $this->openCase();

        (new CaseService)->confirmSanction($case, 5, $officer->id, 'Handbook Section VI mandates expulsion for this confirmed offense.');

        $this->assertSame(5, $case->fresh()->assigned_sanction_category);
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function openCase(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Sanctioned',
            'email' => 'sanction-confirm.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Confirming',
            'email' => 'sanction-confirm.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-15',
            'offense_type' => 'major',
            'title' => 'Vandalism of university property',
            'description' => 'Vandalism of university property.',
            'keywords' => [],
            'created_at' => now(),
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7401',
            'student_id' => $student->id,
            'case_type' => 'major',
            'case_type_locked' => false,
            'confirmed_offense_id' => $offense->id,
            'suggested_sanction_category' => 2,
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
