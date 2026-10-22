<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\RiskFactorWeight;
use App\Models\User;
use App\Services\CaseService;
use Database\Seeders\RiskFactorWeightSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The student's case page used to carry only one outcome card - the Notice of
 * Formative Intervention - which exists for Category 2 alone. Every other
 * category was told to "review the resolution below" and shown nothing, so the
 * decision itself never appeared where the student would look for it.
 */
class StudentResolutionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_non_readmission_resolution_is_shown_to_the_student_in_words(): void
    {
        [$case, $student] = $this->resolvedCase(3);

        $response = $this->actingAs($student)->get(route('student.cases.show', $case));

        $response->assertOk();
        $response->assertSee('Category 3 — Non-Readmission');
        $response->assertSee('You may complete the current term but will not be permitted to enroll for the following term.');
    }

    public function test_the_recorded_notes_reach_the_student(): void
    {
        [$case, $student] = $this->resolvedCase(3, 'Weapon surrendered at the gate and returned to the guardian.');

        $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee('Weapon surrendered at the gate and returned to the guardian.');
    }

    public function test_the_category_is_never_shown_as_a_bare_number(): void
    {
        [$case, $student] = $this->resolvedCase(5);

        $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee('Category 5 — Expulsion')
            ->assertDontSee('<td>Category 5</td>', false);
    }

    /** @return array{0: DisciplinaryCase, 1: User} */
    private function resolvedCase(int $category, ?string $notes = null): array
    {
        RiskFactorWeight::query()->delete();
        $this->seed(RiskFactorWeightSeeder::class);

        $student = $this->user('student');
        $officer = $this->user('discipline_officer');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-2026-'.random_int(1000, 9999),
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
        ]);

        (new CaseService)->recordResolution($case, [
            'resolution_level' => 'sdo_level',
            'intervention_category' => $category,
            'notes' => $notes,
        ], $officer->id);

        return [$case->fresh(['resolution']), $student];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'first_name' => 'Visibility',
            'email' => 'visibility-'.uniqid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
