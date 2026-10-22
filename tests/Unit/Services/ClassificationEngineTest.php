<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OffenseCatalog;
use App\Services\ClassificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for the rule-based classification suggestion component
 * (Offense Classification and Escalation Support Module).
 *
 * OffenseCatalog is an Eloquent model with no external I/O beyond the
 * sqlite in-memory test database configured in phpunit.xml, so it is
 * exercised directly rather than mocked.
 */
class ClassificationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_description_and_no_violation_type_returns_empty_collection_without_querying(): void
    {
        $results = (new ClassificationEngine)->classify('   ');

        $this->assertTrue($results->isEmpty());
    }

    public function test_empty_catalog_returns_empty_collection(): void
    {
        $results = (new ClassificationEngine)->classify('The student was caught smoking near the gate.');

        $this->assertTrue($results->isEmpty());
    }

    public function test_description_below_minimum_score_is_excluded_from_results(): void
    {
        $this->offense('MAJOR-WEAK', 'Unrelated catalog offense', ['isolated']);

        $results = (new ClassificationEngine)->classify('Nothing relevant happened here at all.');

        $this->assertTrue($results->isEmpty());
    }

    public function test_results_are_capped_at_three_suggestions(): void
    {
        foreach (range(1, 5) as $number) {
            $this->offense("MAJOR-CAP-{$number}", "Catalog offense {$number}", ['shared trigger phrase']);
        }

        $results = (new ClassificationEngine)->classify('The report describes a shared trigger phrase.');

        $this->assertCount(3, $results);
    }

    public function test_is_in_top_suggestions_returns_false_when_offense_id_is_not_ranked(): void
    {
        $matching = $this->offense('MAJOR-MATCH', 'Vandalism of property', ['vandalism']);
        $other = $this->offense('MAJOR-OTHER', 'Unrelated offense', ['isolated']);

        $engine = new ClassificationEngine;

        $this->assertTrue($engine->isInTopSuggestions('The student committed vandalism.', $matching->id));
        $this->assertFalse($engine->isInTopSuggestions('The student committed vandalism.', $other->id));
    }

    public function test_violation_type_only_input_can_still_match_without_a_narrative(): void
    {
        $uniform = $this->offense('MINOR-01', 'Non-wearing of prescribed uniform', ['uniform']);

        $results = (new ClassificationEngine)->classify('', violationType: 'Not wear university uniform');

        $this->assertSame($uniform->id, $results->first()['offense']->id);
    }

    public function test_unknown_violation_type_does_not_throw_and_falls_back_to_keyword_scoring(): void
    {
        $this->offense('MINOR-01', 'Non-wearing of prescribed uniform', ['uniform']);

        $results = (new ClassificationEngine)->classify(
            'The student was seen without a uniform.',
            violationType: 'Some unmapped category',
        );

        $this->assertNotNull($results->first());
        $this->assertNull($results->first()['matched_violation_type']);
    }

    public function test_excluded_offense_ids_never_appear_even_as_the_only_strong_match(): void
    {
        $offense = $this->offense('MAJOR-EXCLUDE', 'Vandalism of property', ['vandalism']);

        $results = (new ClassificationEngine)->classify(
            'The student committed vandalism.',
            [$offense->id],
        );

        $this->assertTrue($results->isEmpty());
    }

    private function offense(string $code, string $title, array $keywords): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => str_starts_with($code, 'MINOR') ? 'minor' : 'major',
            'title' => $title,
            'description' => $title.'.',
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }
}
