<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Offense Confirmation with Override Justification
 *            (App\Services\CaseService::confirmOffenses, $overrideReason)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Confirming an offense outside what was advised or classified requires the
 * officer's written justification to be preserved on the charge, the case,
 * and the audit trail, distinct from the plain confirmation path already
 * covered by OffenseConfirmationTest.
 */
class OffenseConfirmationOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Scenario 1 - Normal: an override reason is stored on the confirmed charge and the case. */
    public function test_an_override_reason_is_stored_on_the_charge_and_the_case(): void
    {
        [$case, $officer] = $this->openCase();
        $chosen = $this->offense('MAJOR-26', 'major', 3);
        $reason = 'Body-worn footage confirms possession of vape cartridges, not clothing graphics.';

        (new CaseService)->confirmOffenses($case, [$chosen->id], $officer->id, $reason);

        $fresh = $case->fresh();
        $this->assertSame($reason, $fresh->classification_override_reason);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $chosen->id,
            'classification_reason' => $reason,
        ]);
    }

    /** Scenario 2 - Normal: the override reason is written to the audit log for the confirmation. */
    public function test_the_override_reason_is_written_to_the_audit_log(): void
    {
        [$case, $officer] = $this->openCase();
        $chosen = $this->offense('MAJOR-15', 'major', 4);
        $reason = 'Facilities report ties the reported student to the broken window, not the classified minor case.';

        (new CaseService)->confirmOffenses($case, [$chosen->id], $officer->id, $reason);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'cases',
            'record_id' => $case->id,
            'user_id' => $officer->id,
            'action' => 'update',
            'reason' => $reason,
        ]);
    }

    /** Scenario 3 - Normal: confirming without an override reason leaves the override fields null. */
    public function test_confirming_without_an_override_reason_leaves_it_null(): void
    {
        [$case, $officer] = $this->openCase();
        $recommended = $this->offense('MINOR-01', 'minor', 1);

        (new CaseService)->confirmOffenses($case, [$recommended->id], $officer->id);

        $fresh = $case->fresh();
        $this->assertNull($fresh->classification_override_reason);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $recommended->id,
            'classification_reason' => null,
        ]);
    }

    /** Scenario 4 - Boundary: when multiple offenses are confirmed together, the override reason applies to every new charge in that call. */
    public function test_the_override_reason_applies_to_every_charge_confirmed_in_the_same_call(): void
    {
        [$case, $officer] = $this->openCase();
        $minor = $this->offense('MINOR-01', 'minor', 1);
        $major = $this->offense('MAJOR-15', 'major', 4);
        $reason = 'Both findings are supported by the same incident report and CCTV timestamp.';

        (new CaseService)->confirmOffenses($case, [$minor->id, $major->id], $officer->id, $reason);

        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $minor->id,
            'classification_reason' => $reason,
        ]);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $major->id,
            'classification_reason' => $reason,
        ]);
    }

    /** Scenario 5 - Failure: confirmOffense() forwards a single offense's override reason through the same path as confirmOffenses(). */
    public function test_confirm_offense_forwards_the_override_reason_for_a_single_charge(): void
    {
        [$case, $officer] = $this->openCase();
        $chosen = $this->offense('MAJOR-17', 'major', 2);
        $reason = 'Recorded remarks match the transcript attached to the incident report.';

        (new CaseService)->confirmOffense($case, $chosen->id, $officer->id, $reason);

        $fresh = $case->fresh();
        $this->assertSame($reason, $fresh->classification_override_reason);
        $this->assertSame($chosen->id, $fresh->confirmed_offense_id);
    }

    private function offense(string $code, string $type, int $category): OffenseCatalog
    {
        $offense = OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => "Catalog entry {$code}",
            'description' => "Catalog entry {$code}.",
            'keywords' => [],
            'created_at' => now(),
        ]);

        SanctionMatrix::create([
            'offense_catalog_id' => $offense->id,
            'offense_count' => 1,
            'sanction_category' => $category,
            'us_hours_recommended' => 6.0,
            'us_hours_min' => 6.0,
            'us_hours_max' => 6.0,
            'us_hours_guidance' => '6 hours of University Service.',
            'notes' => null,
        ]);

        return $offense;
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function openCase(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Charged',
            'email' => 'override.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Overriding',
            'email' => 'override.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7301',
            'student_id' => $student->id,
            'case_type' => null,
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
