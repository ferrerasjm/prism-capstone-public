<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\EscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unit tests for the escalation threshold flagging component
 * (Offense Classification and Escalation Support Module).
 *
 * These focus on boundary conditions not already covered by the
 * broader end-to-end escalation flow.
 */
class EscalationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_case_with_no_confirmed_minor_offenses_is_never_flagged(): void
    {
        [$officer, $student] = $this->actors();
        $case = $this->openCase($student, $officer, 'DC-NONE-1');

        $result = (new EscalationService)->check($case);
        $progress = (new EscalationService)->progress($case);

        $this->assertFalse($result['escalation_flagged']);
        $this->assertNull($result['escalation_flag_reason']);
        $this->assertNull($result['suggested_offense_override_id']);
        $this->assertSame(0, $progress['current_minor_charge_count']);
    }

    public function test_dismissed_offense_findings_do_not_count_toward_thresholds(): void
    {
        [$officer, $student] = $this->actors();
        $minor = $this->offense('MINOR-DISMISSED', 'minor');
        $case = $this->openCase($student, $officer, 'DC-DISMISSED-1');
        $case->caseOffenses()->create([
            'offense_catalog_id' => $minor->id,
            'status' => 'dismissed',
        ]);

        $progress = (new EscalationService)->progress($case);

        $this->assertSame(0, $progress['current_minor_charge_count']);
        $this->assertSame(0, $progress['current_same_type_count']);
    }

    public function test_major_offense_findings_are_excluded_from_minor_offense_counters(): void
    {
        [$officer, $student] = $this->actors();
        $major = $this->offense('MAJOR-COUNTS-NOT', 'major');
        $case = $this->openCase($student, $officer, 'DC-MAJORONLY-1');
        $case->caseOffenses()->create([
            'offense_catalog_id' => $major->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        $progress = (new EscalationService)->progress($case);
        $result = (new EscalationService)->check($case);

        $this->assertSame(0, $progress['current_minor_charge_count']);
        $this->assertFalse($result['escalation_flagged']);
    }

    public function test_second_offense_of_the_same_type_does_not_yet_reach_the_threshold(): void
    {
        [$officer, $student] = $this->actors();
        $minor = $this->offense('MINOR-TWO', 'minor');

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $current = $this->openMinorCase($student, $officer, $minor, 2);

        $result = (new EscalationService)->check($current);
        $progress = (new EscalationService)->progress($current);

        $this->assertSame(2, $progress['current_same_type_count']);
        $this->assertFalse($result['escalation_flagged']);
    }

    public function test_suggested_offense_override_id_is_null_when_major19_is_not_in_the_catalog(): void
    {
        // No MAJOR-19 row exists in the catalog for this test.
        [$officer, $student] = $this->actors();
        $minor = $this->offense('MINOR-NOMAJOR19', 'minor');

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);
        $current = $this->openMinorCase($student, $officer, $minor, 3);

        $result = (new EscalationService)->check($current);

        $this->assertTrue($result['escalation_flagged']);
        $this->assertNull($result['suggested_offense_override_id']);
    }

    private function actors(): array
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Unit',
            'email' => 'unit-officer-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Unit',
            'email' => 'unit-student-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        return [$officer, $student];
    }

    private function resolvedMinorCase(User $student, User $officer, OffenseCatalog $offense, int $sequence): void
    {
        $case = $this->case($student, $officer, $offense, $sequence, 'resolved');
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
    }

    private function openMinorCase(User $student, User $officer, OffenseCatalog $offense, int $sequence): DisciplinaryCase
    {
        $case = $this->case($student, $officer, $offense, $sequence, 'open');
        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);

        return $case;
    }

    private function openCase(User $student, User $officer, string $caseNumber): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'minor',
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
    }

    private function case(User $student, User $officer, OffenseCatalog $offense, int $sequence, string $status): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => "DC-UNIT-{$offense->code}-{$sequence}",
            'student_id' => $student->id,
            'case_type' => 'minor',
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => $status,
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => max(0, $sequence - 1),
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);
    }

    private function offense(string $code, string $type): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => "{$code} Test Offense",
            'description' => 'Test offense.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);
    }
}
