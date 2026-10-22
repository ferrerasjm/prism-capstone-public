<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OffenseCatalog;
use App\Services\AdvisoryOffenseClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for the Laravel bridge to the Tagalog RoBERTa offense
 * recommendation component (Offense Classification and Escalation
 * Support Module). The remote model service is mocked with Http::fake
 * so these tests never make a real network call.
 */
class AdvisoryOffenseClassificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_description_short_circuits_without_calling_the_model(): void
    {
        config()->set('offense_classification.enabled', true);

        $result = app(AdvisoryOffenseClassificationService::class)->classify('   ');

        $this->assertSame('none', $result['source']);
        $this->assertSame('empty', $result['status']);
        Http::assertNothingSent();
    }

    public function test_disabled_model_config_uses_keyword_fallback_without_calling_the_model(): void
    {
        config()->set('offense_classification.enabled', false);
        $offense = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('keyword_fallback', $result['source']);
        $this->assertSame($offense->id, $result['suggestions']->first()['offense']->id);
        Http::assertNothingSent();
    }

    public function test_a_non_array_predictions_payload_falls_back_to_keyword_engine(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $offense = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        Http::fake([
            '*' => Http::response(['predictions' => 'not-an-array']),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('keyword_fallback', $result['source']);
        $this->assertSame($offense->id, $result['suggestions']->first()['offense']->id);
    }

    public function test_a_prediction_with_an_out_of_range_score_falls_back_to_keyword_engine(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        Http::fake([
            '*' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-26', 'score' => 1.5],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('keyword_fallback', $result['source']);
    }

    public function test_a_prediction_missing_a_required_field_falls_back_to_keyword_engine(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        Http::fake([
            '*' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'score' => 0.9],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('keyword_fallback', $result['source']);
    }

    public function test_an_empty_predictions_array_falls_back_to_keyword_engine(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        Http::fake([
            '*' => Http::response(['predictions' => []]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('keyword_fallback', $result['source']);
    }

    public function test_a_prediction_for_a_label_absent_from_the_catalog_is_silently_dropped(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $known = $this->offense('MAJOR-26', 'Cigarettes and vapes', ['sigarilyo']);

        Http::fake([
            '*' => Http::response([
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-UNKNOWN', 'score' => 0.9],
                    ['rank' => 2, 'label' => 'MAJOR-26', 'score' => 0.5],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('Nahuling may dalang sigarilyo ang estudyante.');

        $this->assertSame('hybrid_model', $result['source']);
        $this->assertSame([$known->id], $result['suggestions']->pluck('offense.id')->all());
    }

    public function test_no_match_label_is_excluded_from_suggestions_but_reported_as_no_match(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');

        Http::fake([
            '*' => Http::response([
                'no_match' => true,
                'predictions' => [
                    ['rank' => 1, 'label' => 'NO_MATCH', 'score' => 0.9],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('This narrative does not resemble any handbook offense.');

        $this->assertSame('hybrid_model', $result['source']);
        $this->assertTrue($result['no_match']);
        $this->assertTrue($result['suggestions']->isEmpty());
    }

    public function test_a_guardrail_promoted_top_rank_is_labelled_as_a_handbook_rule(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-25', 'Deadly weapons', ['weapon']);
        $this->offense('MINOR-02', 'University ID', ['id']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => [
                    'applied' => true,
                    'action' => 'promoted',
                    'reason' => 'explicit_deadly_weapon_reference',
                    'original_label' => 'MINOR-02',
                ],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-25', 'score' => 0.258],
                    ['rank' => 2, 'label' => 'MINOR-02', 'score' => 0.126],
                ],
            ]),
        ]);

        $suggestions = app(AdvisoryOffenseClassificationService::class)
            ->classify('the student is bringing a gun')['suggestions'];

        $promoted = $suggestions->firstWhere('offense.code', 'MAJOR-25');
        $this->assertTrue($promoted['rule_backed']);
        $this->assertSame('Handbook rule', $promoted['percentage_label']);
        // The promoted card must not also be flagged low confidence: its
        // percentage is not the reason it ranks first.
        $this->assertFalse($promoted['low_confidence']);

        $runnerUp = $suggestions->firstWhere('offense.code', 'MINOR-02');
        $this->assertFalse($runnerUp['rule_backed']);
        $this->assertSame('Model Score', $runnerUp['percentage_label']);
        $this->assertTrue($runnerUp['low_confidence']);
    }

    public function test_a_confident_ranking_is_not_flagged_as_low_confidence(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-25', 'Deadly weapons', ['weapon']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => ['applied' => false, 'action' => null, 'reason' => null],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-25', 'score' => 0.92],
                ],
            ]),
        ]);

        $suggestion = app(AdvisoryOffenseClassificationService::class)
            ->classify('A student displayed a firearm to classmates.')['suggestions']
            ->first();

        $this->assertFalse($suggestion['rule_backed']);
        $this->assertFalse($suggestion['low_confidence']);
        $this->assertSame('Model Score', $suggestion['percentage_label']);
    }

    public function test_a_lead_between_twenty_and_thirty_five_percent_is_not_flagged(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-11', 'Physical injuries', ['punch']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => ['applied' => false, 'action' => null, 'reason' => null],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-11', 'score' => 0.27],
                ],
            ]),
        ]);

        $suggestion = app(AdvisoryOffenseClassificationService::class)
            ->classify('He punched his seatmate in the jaw.')['suggestions']
            ->first();

        // Leads in this band were right 85-87% of the time on the v6 hybrid,
        // so a warning here would mostly contradict a correct ranking.
        $this->assertFalse($suggestion['low_confidence']);
    }

    public function test_a_lead_below_twenty_percent_is_flagged_as_low_confidence(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-11', 'Physical injuries', ['punch']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => ['applied' => false, 'action' => null, 'reason' => null],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-11', 'score' => 0.19],
                ],
            ]),
        ]);

        $suggestion = app(AdvisoryOffenseClassificationService::class)
            ->classify('He hit a classmate.')['suggestions']
            ->first();

        // Under 0.20 the lead was right only about half the time, and the
        // correct offense was often not among the three cards at all.
        $this->assertTrue($suggestion['low_confidence']);
    }

    public function test_keyword_fallback_suggestions_carry_the_display_flags(): void
    {
        config()->set('offense_classification.enabled', false);
        $this->offense('MAJOR-26', 'Cigarettes and vapes', ['vape']);

        $suggestion = app(AdvisoryOffenseClassificationService::class)
            ->classify('The student was caught with a vape on campus.')['suggestions']
            ->first();

        $this->assertNotNull($suggestion);
        $this->assertFalse($suggestion['rule_backed']);
        $this->assertFalse($suggestion['low_confidence']);
        $this->assertSame('Relative match', $suggestion['percentage_label']);
    }

    public function test_a_flat_ranking_below_the_noise_floor_yields_no_suggestions(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-29', 'Unrelated major', ['unrelated']);
        $this->offense('MINOR-05', 'Unrelated minor', ['unrelated']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => [
                    'applied' => true,
                    'action' => 'suppressed',
                    'reason' => 'non_weapon_sense_of_the_term',
                    'original_label' => 'MAJOR-25',
                ],
                'predictions' => [
                    ['rank' => 1, 'label' => 'MAJOR-29', 'score' => 0.03],
                    ['rank' => 2, 'label' => 'MINOR-05', 'score' => 0.03],
                ],
            ]),
        ]);

        $result = app(AdvisoryOffenseClassificationService::class)
            ->classify('The student used a glue gun while preparing a project display.');

        $this->assertSame('hybrid_model', $result['source']);
        $this->assertTrue($result['suggestions']->isEmpty());
    }

    public function test_a_guardrail_promoted_offense_survives_the_noise_floor(): void
    {
        config()->set('offense_classification.enabled', true);
        config()->set('offense_classification.url', 'http://127.0.0.1:8765');
        $this->offense('MAJOR-25', 'Deadly weapons', ['weapon']);
        $this->offense('MINOR-05', 'Unrelated minor', ['unrelated']);

        Http::fake([
            '*' => Http::response([
                'guardrail' => [
                    'applied' => true,
                    'action' => 'promoted',
                    'reason' => 'explicit_deadly_weapon_reference',
                    'original_label' => 'MINOR-05',
                ],
                'predictions' => [
                    // A weapon promoted into a flat distribution still scores
                    // below the floor, and must never be dropped for it.
                    ['rank' => 1, 'label' => 'MAJOR-25', 'score' => 0.02],
                    ['rank' => 2, 'label' => 'MINOR-05', 'score' => 0.01],
                ],
            ]),
        ]);

        $suggestions = app(AdvisoryOffenseClassificationService::class)
            ->classify('gun')['suggestions'];

        $this->assertSame(['MAJOR-25'], $suggestions->pluck('offense.code')->all());
        $this->assertTrue($suggestions->first()['rule_backed']);
    }

    public function test_a_promoted_lead_still_shows_the_model_scored_runners_up(): void
    {
        config()->set('offense_classification.enabled', true);
        $this->offense('MAJOR-11', 'Physical injuries', ['punch']);
        $this->offense('MAJOR-13', 'Provocation resulting in a confrontation', []);
        $this->offense('MAJOR-42', 'Lasciviousness', []);
        // The scores below are what the v5 ensemble actually returns for this
        // narrative. MAJOR-11 scores 1.8% on its own -- the 17.4% belongs to
        // MAJOR-13, the label the rule displaces.
        Http::fake(['*' => Http::response([
            'guardrail' => ['applied' => true, 'action' => 'promoted', 'reason' => 'explicit_punch_to_person'],
            'predictions' => [
                ['rank' => 1, 'label' => 'MAJOR-11', 'score' => 0.01811841],
                ['rank' => 2, 'label' => 'MAJOR-13', 'score' => 0.17420321],
                ['rank' => 3, 'label' => 'MAJOR-42', 'score' => 0.08076785],
            ],
        ])]);
        $result = app(AdvisoryOffenseClassificationService::class)->classify(
            'During lunch break, he punched another student in the face following a verbal disagreement.'
        );
        $this->assertSame('hybrid_model', $result['source']);
        // The rule settles rank 1 only. A punch after a verbal disagreement may
        // also be MAJOR-13, and the officer selects one or more offenses, so a
        // runner-up the model scored is still offered rather than hidden.
        $this->assertSame(
            ['MAJOR-11', 'MAJOR-13', 'MAJOR-42'],
            $result['suggestions']->pluck('offense.code')->all()
        );
        $this->assertTrue($result['suggestions']->first()['rule_backed']);
        $this->assertFalse($result['suggestions']->last()['rule_backed']);
        // The promoted card carries its own score, not the displaced label's.
        $this->assertSame(1.81, $result['suggestions']->first()['display_percentage']);
        $this->assertSame(17.42, $result['suggestions'][1]['display_percentage']);
    }

    private function offense(string $code, string $title, array $keywords): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => str_starts_with($code, 'MINOR') ? 'minor' : 'major',
            'title' => $title,
            'description' => $title,
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }
}
