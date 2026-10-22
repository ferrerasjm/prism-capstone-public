<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\SanctionMatrix;
use App\Models\User;
use App\Services\AdvisoryOffenseClassificationService;
use App\Services\CaseService;
use App\Services\EscalationService;
use App\Services\SanctionRecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integration test for the Offense Classification and Escalation Support
 * Module. It chains all four components in a single realistic flow:
 *
 *   1. AdvisoryOffenseClassificationService ranks each incoming report
 *      against the offense catalog, using the Tagalog RoBERTa/hybrid
 *      model service (mocked at the HTTP boundary with Http::fake, since
 *      the real model process is a separate Python service).
 *   2. A Discipline Officer confirms the top-ranked offense on three
 *      separate reports for the same student.
 *   3. EscalationService detects the third same-type minor finding and
 *      flags the case for habitual-disregard escalation to MAJOR-19.
 *   4. CaseService applies the escalation, adding the MAJOR-19 charge.
 *   5. SanctionRecommendationService produces the sanction guidance for
 *      the resulting MAJOR-19 charge from the sanction matrix.
 */
class OffenseClassificationEscalationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_minor_offense_reports_flow_through_classification_escalation_and_sanctioning(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');

        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Integration',
            'email' => 'integration-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Integration',
            'email' => 'integration-student@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $uniformOffense = OffenseCatalog::create([
            'code' => 'MINOR-01',
            'offense_type' => 'minor',
            'title' => 'Non-wearing of prescribed uniform',
            'description' => 'Non-wearing of prescribed uniform.',
            'keywords' => ['uniform'],
            'created_at' => now(),
        ]);
        $major19 = OffenseCatalog::create([
            'code' => 'MAJOR-19',
            'offense_type' => 'major',
            'title' => 'Habitual disregard of university rules',
            'description' => 'Habitual disregard of university rules.',
            'keywords' => ['habitual disregard'],
            'created_at' => now(),
        ]);
        SanctionMatrix::create([
            'offense_catalog_id' => $major19->id,
            'offense_count' => 1,
            'sanction_category' => 3,
            'us_hours_recommended' => 24.0,
        ]);

        Http::fake([
            'http://127.0.0.1:8765/predict' => Http::response([
                'model' => ['name' => 'roberta-tfidf-svm-policy-hybrid', 'version' => 'test'],
                'no_match' => false,
                'guardrail' => ['applied' => false, 'reason' => null],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MINOR-01', 'score' => 0.91],
                ],
            ]),
        ]);

        $classificationService = app(AdvisoryOffenseClassificationService::class);
        $escalationService = new EscalationService;
        $caseService = new CaseService;

        $latestCase = null;
        foreach (range(1, 3) as $occurrence) {
            $classification = $classificationService->classify(
                "The student was reported without a complete uniform (incident {$occurrence}).",
            );
            $this->assertSame('hybrid_model', $classification['source']);
            $topSuggestionId = $classification['suggestions']->first()['offense']->id;
            $this->assertSame($uniformOffense->id, $topSuggestionId);

            $latestCase = DisciplinaryCase::create([
                'case_number' => "DC-INTEGRATION-{$occurrence}",
                'student_id' => $student->id,
                'case_type' => 'minor',
                'case_type_locked' => false,
                'assigned_to' => $officer->id,
                'status' => 'open',
                'confirmed_offense_id' => $topSuggestionId,
                'prior_offense_count' => $occurrence - 1,
            ]);
            $latestCase->caseOffenses()->create([
                'offense_catalog_id' => $topSuggestionId,
                'status' => 'confirmed',
                'confirmed_by' => $officer->id,
                'confirmed_at' => now(),
            ]);
        }

        $progress = $escalationService->progress($latestCase);
        $escalationResult = $escalationService->check($latestCase);

        $this->assertSame(3, $progress['current_same_type_count']);
        $this->assertTrue($escalationResult['escalation_flagged']);
        $this->assertSame($major19->id, $escalationResult['suggested_offense_override_id']);

        $latestCase->update($escalationResult);
        $caseService->escalateHabitualDisregard($latestCase->fresh(), $officer->id);
        $latestCase->refresh();

        $this->assertSame('major', $latestCase->case_type);
        $this->assertDatabaseHas('case_offenses', [
            'case_id' => $latestCase->id,
            'offense_catalog_id' => $major19->id,
            'status' => 'confirmed',
        ]);

        $sanction = (new SanctionRecommendationService)->recommend($major19->id, 0);

        $this->assertSame(3, $sanction['sanction_category']);
        $this->assertSame(24.0, $sanction['us_hours_recommended']);
    }
}
