<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\EscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Habitual Disregard Escalation Thresholds (App\Services\EscalationService)
 * Test Cycle No. 1 - 5 test cases.
 */
class HabitualDisregardEscalationTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: a case with no confirmed minor finding is never flagged. */
    public function test_a_case_without_a_confirmed_minor_finding_is_never_flagged(): void
    {
        [$student, $officer] = $this->actors();
        $this->offense('MAJOR-19', 'major');
        $case = $this->openCase($student, $officer, 'DC-2026-9001');

        $result = (new EscalationService)->check($case);
        $progress = (new EscalationService)->progress($case);

        $this->assertFalse($result['escalation_flagged']);
        $this->assertNull($result['escalation_flag_reason']);
        $this->assertNull($result['suggested_offense_override_id']);
        $this->assertSame(0, $progress['current_minor_charge_count']);
    }

    /** Scenario 2 - Failure: dismissed offense findings do not count toward the thresholds. */
    public function test_dismissed_offense_findings_do_not_count_toward_the_thresholds(): void
    {
        [$student, $officer] = $this->actors();
        $this->offense('MAJOR-19', 'major');
        $minor = $this->offense('MINOR-01', 'minor');
        $case = $this->openCase($student, $officer, 'DC-2026-9002');
        $case->caseOffenses()->create([
            'offense_catalog_id' => $minor->id,
            'status' => 'dismissed',
        ]);

        $progress = (new EscalationService)->progress($case);

        $this->assertSame(0, $progress['current_minor_charge_count']);
        $this->assertFalse((new EscalationService)->check($case)['escalation_flagged']);
    }

    /** Scenario 3 - Boundary: two same-type findings stay below the third-offense threshold. */
    public function test_two_findings_of_the_same_type_stay_below_the_threshold(): void
    {
        [$student, $officer] = $this->actors();
        $this->offense('MAJOR-19', 'major');
        $minor = $this->offense('MINOR-01', 'minor');

        $this->resolvedCase($student, $officer, 'DC-2026-9003', $minor);
        $current = $this->openCase($student, $officer, 'DC-2026-9004');
        $this->confirmFinding($current, $minor);

        $progress = (new EscalationService)->progress($current);
        $result = (new EscalationService)->check($current);

        $this->assertSame(2, $progress['current_same_type_count']);
        $this->assertSame(3, $progress['same_type_threshold']);
        $this->assertFalse($result['escalation_flagged']);
    }

    /** Scenario 4 - Normal: the third finding of the same type triggers escalation to MAJOR-19. */
    public function test_the_third_finding_of_the_same_type_triggers_escalation(): void
    {
        [$student, $officer] = $this->actors();
        $major19 = $this->offense('MAJOR-19', 'major');
        $minor = $this->offense('MINOR-01', 'minor');

        $this->resolvedCase($student, $officer, 'DC-2026-9005', $minor);
        $this->resolvedCase($student, $officer, 'DC-2026-9006', $minor);
        $current = $this->openCase($student, $officer, 'DC-2026-9007');
        $this->confirmFinding($current, $minor);

        $result = (new EscalationService)->check($current);

        $this->assertTrue($result['escalation_flagged']);
        $this->assertSame(
            'Third offense of the same type in the current escalation cycle.',
            $result['escalation_flag_reason']
        );
        $this->assertSame($major19->id, $result['suggested_offense_override_id']);
    }

    /** Scenario 5 - Normal: a fourth finding of differing types also triggers escalation. */
    public function test_a_fourth_finding_of_differing_types_triggers_escalation(): void
    {
        [$student, $officer] = $this->actors();
        $major19 = $this->offense('MAJOR-19', 'major');
        $offenses = [
            $this->offense('MINOR-01', 'minor'),
            $this->offense('MINOR-02', 'minor'),
            $this->offense('MINOR-03', 'minor'),
            $this->offense('MINOR-04', 'minor'),
        ];

        foreach (array_slice($offenses, 0, 3) as $index => $offense) {
            $this->resolvedCase($student, $officer, 'DC-2026-91'.$index.'0', $offense);
        }

        $current = $this->openCase($student, $officer, 'DC-2026-9199');
        $this->confirmFinding($current, $offenses[3]);

        $progress = (new EscalationService)->progress($current);
        $result = (new EscalationService)->check($current);

        $this->assertSame(4, $progress['different_type_count']);
        $this->assertSame(4, $progress['different_type_threshold']);
        $this->assertTrue($result['escalation_flagged']);
        $this->assertSame(
            'Fourth minor offense finding of a different kind in the current escalation cycle.',
            $result['escalation_flag_reason']
        );
        $this->assertSame($major19->id, $result['suggested_offense_override_id']);
    }

    /** @return array{0: User, 1: User} */
    private function actors(): array
    {
        $student = User::create([
            'last_name' => 'Cruz',
            'first_name' => 'Student',
            'email' => 'escalation.student@students.nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Officer',
            'email' => 'escalation.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        return [$student, $officer];
    }

    private function offense(string $code, string $type): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => "Catalog entry {$code}",
            'description' => "Catalog entry {$code}.",
            'keywords' => [],
            'created_at' => now(),
        ]);
    }

    private function openCase(User $student, User $officer, string $number): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'case_type' => 'minor',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);
    }

    private function resolvedCase(
        User $student,
        User $officer,
        string $number,
        OffenseCatalog $offense
    ): DisciplinaryCase {
        $case = $this->openCase($student, $officer, $number);
        $this->confirmFinding($case, $offense);
        $case->update(['status' => 'resolved', 'resolved_at' => now()]);

        return $case;
    }

    private function confirmFinding(DisciplinaryCase $case, OffenseCatalog $offense): void
    {
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $case->assigned_to,
            'confirmed_at' => now(),
        ]);
    }
}
