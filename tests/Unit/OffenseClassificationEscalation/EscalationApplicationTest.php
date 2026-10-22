<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\CaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Habitual Disregard Escalation Application
 *            (App\Services\CaseService::escalateHabitualDisregard)
 * Test Cycle No. 1 - 5 test cases.
 *
 * EscalationService (HabitualDisregardEscalationTest) only detects and flags
 * the threshold; this is the separate step where an officer applies that
 * flag, adding the MAJOR-19 charge and locking the case in as major.
 */
class EscalationApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Scenario 1 - Normal: applying an active escalation flag adds MAJOR-19 and retypes the case as major. */
    public function test_applying_an_active_flag_adds_major_19_and_retypes_the_case_as_major(): void
    {
        [$case, $officer] = $this->flaggedCase();

        (new CaseService)->escalateHabitualDisregard($case, $officer->id);

        $fresh = $case->fresh();
        $this->assertSame('major', $fresh->case_type);
        $this->assertNotNull($fresh->escalation_acknowledged_at);
        $this->assertSame($officer->id, $fresh->escalation_acknowledged_by);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $case->id,
            'offense_catalog_id' => $this->major19()->id,
            'status' => 'confirmed',
        ]);
    }

    /** Scenario 2 - Failure: a case with no active escalation flag is refused. */
    public function test_a_case_without_an_active_escalation_flag_is_refused(): void
    {
        [$case, $officer] = $this->openCase();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This case does not have an active escalation alert.');

        (new CaseService)->escalateHabitualDisregard($case, $officer->id);
    }

    /** Scenario 3 - Failure: an already-acknowledged escalation flag cannot be applied again. */
    public function test_an_already_acknowledged_flag_cannot_be_applied_again(): void
    {
        [$case, $officer] = $this->flaggedCase();
        $case->update(['escalation_acknowledged_at' => now(), 'escalation_acknowledged_by' => $officer->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('This case does not have an active escalation alert.');

        (new CaseService)->escalateHabitualDisregard($case->fresh(), $officer->id);
    }

    /** Scenario 4 - Failure: a locked or non-open case refuses escalation even if flagged. */
    public function test_a_locked_or_non_open_case_refuses_escalation(): void
    {
        [$case, $officer] = $this->flaggedCase();
        $case->update(['case_type_locked' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Escalation can only be applied while the case is open and unlocked.');

        (new CaseService)->escalateHabitualDisregard($case->fresh(), $officer->id);
    }

    /** Scenario 5 - Boundary: if MAJOR-19 is already charged on the case, applying escalation does not duplicate the charge. */
    public function test_escalation_does_not_duplicate_an_existing_major_19_charge(): void
    {
        [$case, $officer] = $this->flaggedCase();
        $case->caseOffenses()->create([
            'offense_catalog_id' => $this->major19()->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        (new CaseService)->escalateHabitualDisregard($case->fresh(), $officer->id);

        $this->assertSame(
            1,
            $case->fresh()->caseOffenses()->where('offense_catalog_id', $this->major19()->id)->count()
        );
    }

    private function major19(): OffenseCatalog
    {
        return OffenseCatalog::firstOrCreate(
            ['code' => 'MAJOR-19'],
            [
                'offense_type' => 'major',
                'title' => 'Habitual disregard of university rules',
                'description' => 'Habitual disregard of university rules.',
                'keywords' => [],
                'created_at' => now(),
            ]
        );
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
        $this->major19();

        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Escalated',
            'email' => 'escalation-apply.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Applying',
            'email' => 'escalation-apply.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-7501',
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
