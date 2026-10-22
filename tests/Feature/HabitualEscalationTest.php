<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\DisciplinePolicySetting;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Notifications\CaseEscalatedNotification;
use App\Services\CaseService;
use App\Services\EscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HabitualEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_updated_same_type_threshold_changes_the_escalation_decision(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();
        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $current = $this->openMinorCase($student, $officer, $minor, 2);

        $this->assertFalse((new EscalationService)->check($current)['escalation_flagged']);
        DisciplinePolicySetting::current()->update(['same_type_minor_threshold' => 2]);
        $result = (new EscalationService)->check($current);

        $this->assertTrue($result['escalation_flagged']);
        $this->assertSame('Same-type minor offense threshold of 2 reached in the current escalation cycle.', $result['escalation_flag_reason']);
    }

    public function test_third_offense_of_same_type_can_be_escalated_to_major_19(): void
    {
        [$officer, $student, $minor, $major19] = $this->actorsAndOffenses();

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);
        $current = $this->openMinorCase($student, $officer, $minor, 3);

        $service = new EscalationService;
        $progress = $service->progress($current);
        $result = $service->check($current);

        $this->assertSame(3, $progress['same_type_count']);
        $this->assertSame(1, $progress['different_type_count']);
        $this->assertSame(3, $progress['same_type_threshold']);
        $this->assertTrue($result['escalation_flagged']);
        $current->update($result);

        (new CaseService)->escalateHabitualDisregard($current->fresh(), $officer->id);

        $this->assertSame('major', $current->fresh()->case_type);
        $this->assertSame($major19->id, $current->fresh()->confirmed_offense_id);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $current->id,
            'offense_catalog_id' => $minor->id,
        ]);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $current->id,
            'offense_catalog_id' => $major19->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'type' => CaseEscalatedNotification::class,
            'notifiable_id' => $student->id,
        ]);
        $resetProgress = $service->progress($current->fresh());
        $this->assertSame(0, $resetProgress['same_type_count']);
        $this->assertSame(0, $resetProgress['different_type_count']);
        $this->assertNotNull($resetProgress['last_escalation_at']);

        $nextCycleCase = $this->openMinorCase($student, $officer, $minor, 4);
        $nextCycleResult = (new EscalationService)->check($nextCycleCase);
        $nextCycleProgress = $service->progress($nextCycleCase);

        $this->assertFalse($nextCycleResult['escalation_flagged']);
        $this->assertSame(1, $nextCycleProgress['same_type_count']);
    }

    public function test_fourth_minor_case_with_different_types_triggers_escalation(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();
        $minorB = $this->offense('MINOR-B', 'minor');
        $minorC = $this->offense('MINOR-C', 'minor');
        $minorD = $this->offense('MINOR-D', 'minor');

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $firstCase = DisciplinaryCase::where('case_number', 'DC-TEST-1')->firstOrFail();
        $firstCase->caseOffenses()->create([
            'offense_catalog_id' => $minorB->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
        $this->resolvedMinorCase($student, $officer, $minorC, 2);
        $current = $this->openMinorCase($student, $officer, $minorD, 3);

        $service = new EscalationService;
        $progress = $service->progress($current);
        $result = $service->check($current);

        $this->assertTrue($result['escalation_flagged']);
        $this->assertStringContainsString('Fourth minor offense finding', $result['escalation_flag_reason']);
        $this->assertSame(4, $progress['different_type_count']);
        $this->assertSame(4, $progress['different_type_threshold']);
    }

    public function test_four_findings_with_only_three_kinds_does_not_trigger_escalation(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();
        $minorB = $this->offense('MINOR-B', 'minor');
        $minorC = $this->offense('MINOR-C', 'minor');

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);
        $this->resolvedMinorCase($student, $officer, $minorB, 3);
        $current = $this->openMinorCase($student, $officer, $minorC, 4);

        $result = (new EscalationService)->check($current);

        $this->assertFalse($result['escalation_flagged']);
    }

    public function test_third_same_type_minor_offense_counts_active_confirmed_cases(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();

        $this->openMinorCase($student, $officer, $minor, 1);
        $this->openMinorCase($student, $officer, $minor, 2);
        $current = $this->openMinorCase($student, $officer, $minor, 3);

        $service = new EscalationService;
        $progress = $service->progress($current);
        $result = $service->check($current);

        $this->assertSame(3, $progress['same_type_count']);
        $this->assertSame(3, $progress['current_same_type_count']);
        $this->assertTrue($result['escalation_flagged']);
        $this->assertStringContainsString('Third offense of the same type', $result['escalation_flag_reason']);

        $response = $this->actingAs($officer)
            ->get(route('officer.cases.show', $current))
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/Prior confirmed occurrences:\s*2/',
            strip_tags($response->getContent()),
        );
    }

    public function test_dismissed_and_voided_cases_do_not_count_toward_habitual_escalation(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();

        $this->case($student, $officer, $minor, 1, 'dismissed')->caseOffenses()->create([
            'offense_catalog_id' => $minor->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
        $this->case($student, $officer, $minor, 2, 'voided')->caseOffenses()->create([
            'offense_catalog_id' => $minor->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => now(),
        ]);
        $current = $this->openMinorCase($student, $officer, $minor, 3);

        $service = new EscalationService;
        $progress = $service->progress($current);
        $result = $service->check($current);

        $this->assertSame(1, $progress['same_type_count']);
        $this->assertFalse($result['escalation_flagged']);
    }

    public function test_later_active_cases_do_not_retroactively_trigger_an_older_case(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();

        $first = $this->openMinorCase($student, $officer, $minor, 1);
        $second = $this->openMinorCase($student, $officer, $minor, 2);
        $third = $this->openMinorCase($student, $officer, $minor, 3);

        $service = new EscalationService;

        $this->assertFalse($service->check($first)['escalation_flagged']);
        $this->assertSame(1, $service->progress($first)['current_same_type_count']);
        $this->assertFalse($service->check($second)['escalation_flagged']);
        $this->assertSame(2, $service->progress($second)['current_same_type_count']);
        $this->assertTrue($service->check($third)['escalation_flagged']);
        $this->assertSame(3, $service->progress($third)['current_same_type_count']);
    }

    public function test_major_case_can_add_habitual_disregard_without_replacing_existing_primary_charge(): void
    {
        [$officer, $student, $minor, $major19] = $this->actorsAndOffenses();
        $major25 = $this->offense('MAJOR-25', 'major');
        SanctionMatrix::create([
            'offense_catalog_id' => $major25->id,
            'offense_count' => 1,
            'sanction_category' => 5,
        ]);

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);
        $current = DisciplinaryCase::create([
            'case_number' => 'DC-MULTI-MAJOR-003',
            'student_id' => $student->id,
            'case_type' => 'major',
            'case_type_locked' => false,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'confirmed_offense_id' => $major25->id,
            'prior_offense_count' => 0,
        ]);
        foreach ([$minor, $major25] as $offense) {
            $current->caseOffenses()->create([
                'offense_catalog_id' => $offense->id,
                'status' => 'confirmed',
                'confirmed_by' => $officer->id,
                'confirmed_at' => now(),
            ]);
        }

        $this->actingAs($officer)
            ->get(route('officer.cases.show', $current))
            ->assertOk()
            ->assertSee('Add MAJOR-19')
            ->assertSee('Third offense of the same type');

        $this->post(route('officer.cases.apply-escalation', $current))
            ->assertRedirect()
            ->assertSessionHas('success');

        $current->refresh();
        $this->assertSame('major', $current->case_type);
        $this->assertSame($major25->id, $current->confirmed_offense_id);
        $this->assertNotNull($current->escalation_acknowledged_at);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $current->id,
            'offense_catalog_id' => $major19->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_student_sees_their_habitual_disregard_counters_on_my_cases(): void
    {
        [$officer, $student, $minor] = $this->actorsAndOffenses();

        $this->resolvedMinorCase($student, $officer, $minor, 1);
        $this->resolvedMinorCase($student, $officer, $minor, 2);

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('Minor offense standing')
            ->assertSee('One more minor finding would make this a major offense')
            ->assertSee('Same offense, repeated')
            ->assertSee('Different minor offenses')
            ->assertSee($minor->title);
    }

    public function test_a_student_with_no_findings_still_sees_how_the_rule_works(): void
    {
        $student = $this->user('student', 'clean.student@example.test');

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('No confirmed minor offenses on your record')
            ->assertSee('Habitual Disregard', false);
    }

    private function actorsAndOffenses(): array
    {
        $officer = $this->user('discipline_officer', 'officer@example.test');
        $student = $this->user('student', 'student@example.test');
        $minor = $this->offense('MINOR-A', 'minor');
        $major19 = $this->offense('MAJOR-19', 'major');

        SanctionMatrix::create([
            'offense_catalog_id' => $major19->id,
            'offense_count' => 1,
            'sanction_category' => 2,
        ]);

        return [$officer, $student, $minor, $major19];
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

    private function case(User $student, User $officer, OffenseCatalog $offense, int $sequence, string $status): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => "DC-TEST-{$sequence}",
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

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => 'Test',
            'first_name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
