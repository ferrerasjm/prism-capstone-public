<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Services\SanctionRecommendationService;
use Database\Seeders\OffenseCatalogSeeder;
use Database\Seeders\SanctionMatrixSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SanctionRecommendationGuidelinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            OffenseCatalogSeeder::class,
            SanctionMatrixSeeder::class,
        ]);
    }

    public function test_exact_official_hours_replace_the_provisional_defaults(): void
    {
        $first = $this->recommend('MAJOR-04', 0);
        $second = $this->recommend('MAJOR-04', 1);
        $third = $this->recommend('MAJOR-04', 2);

        $this->assertSame(16.0, $first['us_hours_recommended']);
        $this->assertSame(60.0, $second['us_hours_recommended']);
        $this->assertSame(
            60.0,
            $third['us_hours_recommended'],
            'Rows beyond the second offense should retain the official second-offense guideline.'
        );
    }

    public function test_variable_guidelines_return_a_range_without_inventing_an_exact_value(): void
    {
        $first = $this->recommend('MAJOR-01', 0);
        $second = $this->recommend('MAJOR-01', 1);

        $this->assertNull($first['us_hours_recommended']);
        $this->assertSame(8.0, $first['us_hours_min']);
        $this->assertSame(40.0, $first['us_hours_max']);
        $this->assertStringContainsString('Quizzes', $first['us_hours_guidance']);

        $this->assertNull($second['us_hours_recommended']);
        $this->assertSame(40.0, $second['us_hours_min']);
        $this->assertSame(200.0, $second['us_hours_max']);
        $this->assertStringContainsString('Written reports', $second['us_hours_guidance']);
    }

    public function test_conditional_guidelines_retain_the_official_eligibility_note(): void
    {
        $recommendation = $this->recommend('MAJOR-02', 0);

        $this->assertSame(40.0, $recommendation['us_hours_recommended']);
        $this->assertStringContainsString(
            'appeal is approved',
            $recommendation['us_hours_guidance']
        );
    }

    public function test_offenses_without_university_service_hours_remain_empty(): void
    {
        $recommendation = $this->recommend('MAJOR-10', 0);

        $this->assertNull($recommendation['us_hours_recommended']);
        $this->assertNull($recommendation['us_hours_min']);
        $this->assertNull($recommendation['us_hours_max']);
        $this->assertNull($recommendation['us_hours_guidance']);
    }

    public function test_resolution_form_shows_variable_guidance_without_adjustments(): void
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => 'guideline-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        $offense = OffenseCatalog::where('code', 'MAJOR-01')->firstOrFail();
        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-GUIDELINE-001',
            'case_type' => 'major',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => 2,
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'prior_offense_count' => 0,
        ]);

        $this->actingAs($officer)
            ->get(route('officer.cases.resolution', $case))
            ->assertOk()
            ->assertSee('Must stay within the 8')
            ->assertSee('matrix guideline for Category 2', false)
            // The min/max range is already stated in that sentence, so the
            // "Official base guideline" panel below it would be pure repetition -
            // it's reserved for when there's no guideline text to fall back on.
            ->assertDontSee('Official base guideline')
            ->assertDontSee('Select the applicable amount using the details below.')
            ->assertDontSee('Quizzes')
            ->assertDontSee('Official adjustments to the base hours');
    }

    public function test_resolution_form_shows_a_fallback_note_when_no_guideline_exists(): void
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => 'no-guideline-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        $offense = OffenseCatalog::where('code', 'MAJOR-10')->firstOrFail();
        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-GUIDELINE-002',
            'case_type' => 'major',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => 2,
            'assigned_to' => $officer->id,
            'status' => 'pending_resolution',
            'prior_offense_count' => 0,
        ]);

        $this->actingAs($officer)
            ->get(route('officer.cases.resolution', $case))
            ->assertOk()
            ->assertSee('Official base guideline')
            ->assertSee('No official hours guideline is recorded for this offense');
    }

    /**
     * @return array{
     *     sanction_category: int|null,
     *     us_hours_recommended: float|null,
     *     us_hours_min: float|null,
     *     us_hours_max: float|null,
     *     us_hours_guidance: string|null,
     *     notes: string|null,
     *     offense_count_used: int
     * }
     */
    private function recommend(string $code, int $priorOffenseCount): array
    {
        $offense = OffenseCatalog::where('code', $code)->firstOrFail();

        return (new SanctionRecommendationService)
            ->recommend($offense->id, $priorOffenseCount);
    }
}
