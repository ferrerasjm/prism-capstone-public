<?php

declare(strict_types=1);

namespace Tests\Unit\OffenseClassificationEscalation;

use App\Models\OffenseCatalog;
use App\Services\ClassificationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Keyword Classification Suggestion Engine (App\Services\ClassificationEngine)
 * Test Cycle No. 1 - 5 test cases.
 *
 * The engine reads only the offense catalog table, which the in-memory
 * SQLite test database provides, so it is exercised directly.
 */
class KeywordClassificationSuggestionTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Invalid input: a blank narrative with no violation type yields nothing. */
    public function test_a_blank_narrative_produces_no_suggestions(): void
    {
        $this->offense('MINOR-01', 'minor', 'Non-wearing of prescribed uniform', ['uniform']);

        $results = (new ClassificationEngine)->classify('    ');

        $this->assertTrue($results->isEmpty());
    }

    /** Scenario 2 - Failure: an unrelated narrative stays below the minimum match score. */
    public function test_an_unrelated_narrative_returns_no_suggestion(): void
    {
        $this->offense('MAJOR-15', 'major', 'Vandalism of university property', ['vandalism', 'defacing property']);

        $results = (new ClassificationEngine)->classify('The student submitted the laboratory report ahead of schedule.');

        $this->assertTrue($results->isEmpty());
    }

    /** Scenario 3 - Normal: a matching narrative ranks the relevant catalog offense first. */
    public function test_a_matching_narrative_ranks_the_relevant_offense_first(): void
    {
        $vandalism = $this->offense('MAJOR-15', 'major', 'Vandalism of university property', ['vandalism']);
        $this->offense('MINOR-01', 'minor', 'Non-wearing of prescribed uniform', ['uniform']);

        $results = (new ClassificationEngine)->classify('The student committed vandalism on the corridor wall.');

        $this->assertSame($vandalism->id, $results->first()['offense']->id);
        $this->assertTrue((new ClassificationEngine)->isInTopSuggestions(
            'The student committed vandalism on the corridor wall.',
            $vandalism->id
        ));
    }

    /** Scenario 4 - Boundary: the suggestion list is capped at three entries. */
    public function test_the_suggestion_list_is_capped_at_three_entries(): void
    {
        foreach (range(1, 6) as $number) {
            $this->offense("MAJOR-CAP-{$number}", 'major', "Catalog offense {$number}", ['shared trigger phrase']);
        }

        $results = (new ClassificationEngine)->classify('The report describes a shared trigger phrase.');

        $this->assertCount(3, $results);
    }

    /** Scenario 5 - Failure: an already-charged offense is excluded from the ranking. */
    public function test_an_excluded_offense_never_appears_in_the_ranking(): void
    {
        $vandalism = $this->offense('MAJOR-15', 'major', 'Vandalism of university property', ['vandalism']);
        $engine = new ClassificationEngine;
        $narrative = 'The student committed vandalism on the corridor wall.';

        $withExclusion = $engine->classify($narrative, [$vandalism->id]);
        $withoutExclusion = $engine->classify($narrative);

        $this->assertTrue($withExclusion->isEmpty());
        $this->assertSame($vandalism->id, $withoutExclusion->first()['offense']->id);
    }

    private function offense(string $code, string $type, string $title, array $keywords): OffenseCatalog
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
