<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\OffenseCatalog;
use App\Services\AdvisoryOffenseClassificationService;
use App\Services\ClassificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Advisory Model Recommendation Client
 *            (App\Services\AdvisoryOffenseClassificationService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The Tagalog RoBERTa hybrid model is served over HTTP. The transport is
 * faked here so the client contract, guardrail passthrough and keyword
 * fallback can be verified deterministically without loading the model.
 */
class AdvisoryModelRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private const NARRATIVE = 'Sinira ng estudyante ang pintuan ng silid-aralan matapos ang klase.';

    /** Scenario 1 - Invalid input: a blank narrative is answered without calling the model. */
    public function test_a_blank_narrative_returns_an_empty_result_without_calling_the_model(): void
    {
        Http::fake();
        config()->set('offense_classification.enabled', true);

        $result = $this->service()->classify('   ');

        $this->assertSame('none', $result['source']);
        $this->assertSame('empty', $result['status']);
        $this->assertTrue($result['suggestions']->isEmpty());
        Http::assertNothingSent();
    }

    /** Scenario 2 - Normal: a valid model response is mapped onto the offense catalog. */
    public function test_a_valid_model_response_is_mapped_onto_the_offense_catalog(): void
    {
        config()->set('offense_classification.enabled', true);
        $damage = $this->offense('MAJOR-15', 'major', 'Vandalism of university property');
        $this->offense('MAJOR-17', 'major', 'Gross disrespect to a university member');

        Http::fake([
            '*/predict' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-15', 'score' => 0.91],
                    ['rank' => 2, 'label' => 'MAJOR-17', 'score' => 0.05],
                ],
                'model' => ['name' => 'hybrid-prism-offense-v3', 'version' => 'test-version'],
                'no_match' => false,
                'guardrail' => null,
            ]),
        ]);

        $result = $this->service()->classify(self::NARRATIVE);

        $this->assertSame('hybrid_model', $result['source']);
        $this->assertSame('success', $result['status']);
        $this->assertSame($damage->id, $result['suggestions']->first()['offense']->id);
        $this->assertSame(91.0, $result['suggestions']->first()['display_percentage']);
        $this->assertSame('hybrid-prism-offense-v3', $result['model']['name']);
    }

    /** Scenario 3 - Boundary: a NO_MATCH verdict is reported without becoming an offense card. */
    public function test_a_no_match_verdict_is_reported_without_producing_an_offense_card(): void
    {
        config()->set('offense_classification.enabled', true);
        $this->offense('MAJOR-15', 'major', 'Vandalism of university property');

        Http::fake([
            '*/predict' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'label' => 'NO_MATCH', 'score' => 0.88],
                    // Above the noise floor: this case asserts that NO_MATCH is
                    // reported without becoming a card, not display filtering.
                    ['rank' => 2, 'label' => 'MAJOR-15', 'score' => 0.09],
                ],
                'model' => ['name' => 'hybrid-prism-offense-v3', 'version' => 'test-version'],
                'no_match' => true,
                'guardrail' => ['reason' => 'keyboard_rearrangement_without_damage_evidence'],
            ]),
        ]);

        $result = $this->service()->classify(self::NARRATIVE);

        $this->assertTrue($result['no_match']);
        $this->assertSame(
            'keyboard_rearrangement_without_damage_evidence',
            $result['guardrail']['reason']
        );
        $this->assertCount(1, $result['suggestions']);
        $this->assertSame('MAJOR-15', $result['suggestions']->first()['offense']->code);
    }

    /** Scenario 4 - Failure: an out-of-range score is rejected and the keyword engine takes over. */
    public function test_an_out_of_range_model_score_falls_back_to_keyword_recommendations(): void
    {
        config()->set('offense_classification.enabled', true);
        $this->offense('MAJOR-15', 'major', 'Vandalism of university property', ['sinira', 'vandalism']);

        Http::fake([
            '*/predict' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-15', 'score' => 1.7],
                ],
            ]),
        ]);

        $result = $this->service()->classify(self::NARRATIVE);

        $this->assertSame('keyword_fallback', $result['source']);
        $this->assertSame('fallback', $result['status']);
        $this->assertNotNull($result['notice']);
    }

    /** Scenario 5 - Failure: an unavailable model service still returns keyword recommendations. */
    public function test_an_unavailable_model_service_still_returns_keyword_recommendations(): void
    {
        config()->set('offense_classification.enabled', true);
        $vandalism = $this->offense('MAJOR-15', 'major', 'Vandalism of university property', ['vandalism']);

        Http::fake(['*/predict' => Http::response('service unavailable', 503)]);

        $result = $this->service()->classify('The student committed vandalism on the corridor wall.');

        $this->assertSame('keyword_fallback', $result['source']);
        $this->assertSame($vandalism->id, $result['suggestions']->first()['offense']->id);
        $this->assertSame('keyword_fallback', $result['suggestions']->first()['source']);
        $this->assertSame('Relative match', $result['suggestions']->first()['percentage_label']);
    }

    private function service(): AdvisoryOffenseClassificationService
    {
        return new AdvisoryOffenseClassificationService(new ClassificationEngine);
    }

    private function offense(string $code, string $type, string $title, array $keywords = []): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $title,
            'description' => $title.'.',
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }
}
