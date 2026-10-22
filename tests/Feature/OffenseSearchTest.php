<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OffenseCatalog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 3 - Offense Classification and Escalation Support
 * Component: Offense Catalog Search (App\Http\Controllers\Officer\CaseController::offenseSearch)
 * Test Cycle No. 1 - 5 test cases.
 *
 * This is a plain catalog lookup used while charging a case (searching by
 * code, title, description, or keyword), distinct from the ClassificationEngine's
 * narrative-ranking suggestions already covered by KeywordClassificationSuggestionTest.
 * It has no service layer, so it is exercised through the route.
 */
class OffenseSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The controller orders results with the MySQL-only FIELD() function,
        // which SQLite (this suite's test connection) does not provide.
        DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function (...$args) {
            $needle = array_shift($args);
            foreach ($args as $index => $value) {
                if ($needle === $value) {
                    return $index + 1;
                }
            }

            return 0;
        });
    }

    /** Scenario 1 - Normal: a query matching the offense code returns that offense. */
    public function test_a_query_matching_the_offense_code_returns_it(): void
    {
        $officer = $this->officer();
        $vandalism = $this->offense('MAJOR-15', 'major', 'Vandalism of university property', 'Destruction or defacement of University property.', ['vandalism']);
        $this->offense('MINOR-01', 'minor', 'Non-wearing of prescribed uniform', 'Attending class without the prescribed uniform.', ['uniform']);

        $response = $this->actingAs($officer)
            ->getJson(route('officer.cases.offense-search', ['q' => 'MAJOR-15']))
            ->assertOk();

        $response->assertJsonCount(1);
        $this->assertSame($vandalism->id, $response->json('0.id'));
    }

    /** Scenario 2 - Normal: a query matching only the keywords column still finds the offense. */
    public function test_a_query_matching_only_the_keywords_column_finds_the_offense(): void
    {
        $officer = $this->officer();
        $offense = $this->offense('MAJOR-26', 'major', 'Possession of prohibited items', 'Possession of items prohibited inside University premises.', ['sigarilyo', 'vape']);

        $response = $this->actingAs($officer)
            ->getJson(route('officer.cases.offense-search', ['q' => 'sigarilyo']))
            ->assertOk();

        $response->assertJsonCount(1);
        $this->assertSame($offense->id, $response->json('0.id'));
    }

    /** Scenario 3 - Boundary: an empty query returns the catalog capped at fifteen entries, minor offenses first. */
    public function test_an_empty_query_returns_up_to_fifteen_entries_with_minor_offenses_first(): void
    {
        $officer = $this->officer();
        foreach (range(1, 10) as $number) {
            $this->offense("MAJOR-{$number}", 'major', "Major catalog offense {$number}", "Major catalog offense {$number}.", []);
        }
        foreach (range(1, 10) as $number) {
            $this->offense("MINOR-{$number}", 'minor', "Minor catalog offense {$number}", "Minor catalog offense {$number}.", []);
        }

        $response = $this->actingAs($officer)
            ->getJson(route('officer.cases.offense-search', ['q' => '']))
            ->assertOk();

        $response->assertJsonCount(15);
        $this->assertSame('minor', $response->json('0.offense_type'));
    }

    /** Scenario 4 - Failure: a query matching no offense returns an empty list rather than an error. */
    public function test_a_query_matching_nothing_returns_an_empty_list(): void
    {
        $officer = $this->officer();
        $this->offense('MINOR-01', 'minor', 'Non-wearing of prescribed uniform', 'Attending class without the prescribed uniform.', ['uniform']);

        $response = $this->actingAs($officer)
            ->getJson(route('officer.cases.offense-search', ['q' => 'nonexistentquerytext']))
            ->assertOk();

        $response->assertJsonCount(0);
    }

    /** Scenario 5 - Failure: an unauthenticated request is redirected instead of returning catalog data. */
    public function test_an_unauthenticated_request_cannot_reach_the_search_endpoint(): void
    {
        $this->offense('MINOR-01', 'minor', 'Non-wearing of prescribed uniform', 'Attending class without the prescribed uniform.', ['uniform']);

        $this->get(route('officer.cases.offense-search', ['q' => 'uniform']))
            ->assertRedirect(route('login'));
    }

    private function offense(string $code, string $type, string $title, string $description, array $keywords): OffenseCatalog
    {
        return OffenseCatalog::create([
            'code' => $code,
            'offense_type' => $type,
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'created_at' => now(),
        ]);
    }

    private function officer(): User
    {
        return User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Discipline',
            'email' => 'offense-search.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }
}
