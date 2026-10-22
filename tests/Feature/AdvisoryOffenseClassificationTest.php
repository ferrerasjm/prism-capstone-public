<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\OffenseRecommendationAudit;
use App\Models\User;
use App\Services\AdvisoryOffenseClassificationService;
use App\Services\OffenseRecommendationAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdvisoryOffenseClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hybrid_predictions_are_mapped_to_catalog_without_making_a_decision(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');

        $cigarette = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);
        $clothing = $this->offense('MINOR-04', 'Inappropriate clothing graphics', ['graphic']);
        $idViolation = $this->offense('MINOR-02', 'University ID violation', ['identification card']);
        $excluded = $this->offense('MINOR-01', 'Uniform violation', ['uniform']);

        Http::fake([
            'http://127.0.0.1:8765/predict' => Http::response([
                'advisory' => true,
                'model' => [
                    'name' => 'roberta-tfidf-svm-policy-hybrid',
                    'version' => 'test-version',
                ],
                'no_match' => false,
                'guardrail' => ['applied' => false, 'reason' => null],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-26', 'score' => 0.73],
                    ['rank' => 2, 'label' => 'NO_MATCH', 'score' => 0.02],
                    // Kept above the noise floor: this case asserts catalog
                    // mapping and exclusion handling, not display filtering.
                    ['rank' => 3, 'label' => 'MINOR-04', 'score' => 0.11],
                    ['rank' => 4, 'label' => 'MINOR-02', 'score' => 0.06],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)->classify(
            'Ang estudyante ay nahuli na may dalang sigarilyo.',
            [$excluded->id],
        );

        $this->assertSame('hybrid_model', $result['source']);
        $this->assertSame(
            [$cigarette->id, $clothing->id, $idViolation->id],
            $result['suggestions']->pluck('offense.id')->all(),
        );
        $this->assertSame([73.0, 11.0, 6.0], $result['suggestions']->pluck('display_percentage')->all());
        $this->assertNull($cigarette->fresh()->confirmedCases()->first());

        Http::assertSent(fn (Request $request): bool => $request['top_k'] === 4
            && $request['excluded_labels'] === ['MINOR-01']);
    }

    public function test_unavailable_model_uses_keyword_fallback(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $offense = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);
        Http::fake(['*' => Http::response([], 503)]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang student.');

        $this->assertSame('keyword_fallback', $result['source']);
        $this->assertSame($offense->id, $result['suggestions']->first()['offense']->id);
        $this->assertNotNull($result['notice']);
    }

    public function test_keyword_fallback_excludes_inapplicable_and_manual_only_codes(): void
    {
        config()->set('offense_classification.enabled', false);
        $this->offense('MAJOR-16', 'Student publication circulation', ['newsstand copies']);
        $this->offense('MAJOR-22', 'Dormitory rules', ['athlete dormitory']);
        $this->offense('MAJOR-19', 'Habitual disregard', ['habitual disregard']);

        $service = app(AdvisoryOffenseClassificationService::class);

        foreach ([
            'Removed newsstand copies.',
            'Violated athlete dormitory rules.',
            'Committed habitual disregard.',
        ] as $description) {
            $result = $service->classify($description);
            $this->assertTrue($result['suggestions']->isEmpty(), $description);
        }
    }

    public function test_keyword_fallback_can_recommend_major30(): void
    {
        config()->set('offense_classification.enabled', false);
        $major30 = $this->offense(
            'MAJOR-30',
            'Acts of subversion',
            ['violent overthrow of lawful authority'],
        );

        $result = app(AdvisoryOffenseClassificationService::class)->classify(
            'The student recruited a group for the violent overthrow of lawful authority.',
        );

        $this->assertSame(
            $major30->id,
            $result['suggestions']->first()['offense']->id,
        );
    }

    public function test_recommendation_and_officer_decision_are_audited_separately(): void
    {
        $officer = User::query()->create([
            'last_name' => 'Officer',
            'first_name' => 'Test',
            'email' => 'ml-audit@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
        $selected = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);
        $case = DisciplinaryCase::query()->create([
            'case_number' => 'CASE-ML-AUDIT',
            'student_id' => $officer->id,
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
        $classification = [
            'source' => 'hybrid_model',
            'status' => 'success',
            'predictions' => [
                ['rank' => 1, 'label' => 'MAJOR-26', 'score' => 0.73],
            ],
            'model' => ['name' => 'hybrid', 'version' => 'version-1'],
            'no_match' => false,
            'guardrail' => ['reason' => null],
        ];

        $service = app(OffenseRecommendationAuditService::class);
        $audit = $service->record(
            $case,
            'Sensitive incident narrative.',
            [],
            $classification,
            $officer->id,
        );

        $this->assertSame(hash('sha256', 'Sensitive incident narrative.'), $audit->description_hash);
        $this->assertStringNotContainsString(
            'Sensitive incident narrative.',
            json_encode($audit->getAttributes(), JSON_THROW_ON_ERROR),
        );
        $this->assertNull($audit->selected_offense_id);

        $service->recordDecision($audit, $selected->id, $officer->id);

        $this->assertDatabaseHas('offense_recommendation_audits', [
            'id' => $audit->id,
            'selected_offense_id' => $selected->id,
            'decision_was_recommended' => true,
            'decided_by' => $officer->id,
        ]);
        $this->assertSame(1, OffenseRecommendationAudit::query()->count());
    }

    private function offense(string $code, string $title, array $keywords): OffenseCatalog
    {
        return OffenseCatalog::query()->create([
            'code' => $code,
            'offense_type' => str_starts_with($code, 'MINOR') ? 'minor' : 'major',
            'title' => $title,
            'description' => $title,
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }
}
