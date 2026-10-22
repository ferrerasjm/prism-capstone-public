<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OffenseCatalog;
use App\Services\ClassificationEngine;
use Database\Seeders\OffenseCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_keywords_only_match_complete_words(): void
    {
        $idOffense = $this->offense(
            'MINOR-ID',
            'Non-wearing or failure to bring University ID',
            ['id'],
        );

        $results = (new ClassificationEngine)->classify(
            'The incident occurred while the student was waiting inside the laboratory.'
        );

        $this->assertFalse($results->pluck('offense.id')->contains($idOffense->id));
    }

    public function test_other_short_catalog_terms_do_not_match_inside_unrelated_words(): void
    {
        $ied = $this->offense('MAJOR-IED', 'Explosive device offense', ['ied']);
        $pod = $this->offense('MAJOR-POD', 'Vape pod offense', ['pod']);
        $mod = $this->offense('MAJOR-MOD', 'Vape mod offense', ['mod']);

        $results = (new ClassificationEngine)->classify(
            'The student tried to move the modern podium after the program.'
        );

        $ids = $results->pluck('offense.id');
        $this->assertFalse($ids->contains($ied->id));
        $this->assertFalse($ids->contains($pod->id));
        $this->assertFalse($ids->contains($mod->id));
    }

    public function test_specific_phrases_outrank_shared_generic_keywords(): void
    {
        $this->offense(
            'MAJOR-BULLY',
            'Bullying',
            ['harassment', 'intimidation'],
        );
        $sexualHarassment = $this->offense(
            'MAJOR-SH',
            'Gender-based sexual harassment',
            ['harassment', 'sexual harassment', 'unwanted sexual advances'],
        );

        $results = (new ClassificationEngine)->classify(
            'The complaint described unwanted sexual advances and sexual harassment.'
        );

        $this->assertSame($sexualHarassment->id, $results->first()['offense']->id);
        $this->assertContains(
            'unwanted sexual advances',
            $results->first()['matched_keywords'],
        );
        $this->assertContains(
            'sexual harassment',
            $results->first()['matched_keywords'],
        );
        $this->assertNotContains('harassment', $results->first()['matched_keywords']);
    }

    public function test_nested_keyword_phrases_are_counted_only_once(): void
    {
        $vaping = $this->offense(
            'MAJOR-VAPE',
            'Bringing in or smoking cigarettes and vapes',
            ['vape', 'with vape', 'caught with vape'],
        );

        $result = (new ClassificationEngine)
            ->classify('The student was caught with a vape near the entrance.')
            ->firstWhere('offense.id', $vaping->id);

        $this->assertNotNull($result);
        $this->assertSame(['caught with vape'], $result['matched_keywords']);
    }

    public function test_plural_forms_match_catalog_keywords(): void
    {
        $smoking = $this->offense(
            'MAJOR-SMOKE',
            'Bringing in or smoking cigarettes',
            ['cigarette'],
        );

        $results = (new ClassificationEngine)->classify(
            'Security found two cigarettes in the student’s bag.'
        );

        $this->assertSame($smoking->id, $results->first()['offense']->id);
        $this->assertContains('cigarette', $results->first()['matched_keywords']);
    }

    public function test_title_concepts_can_support_a_match_when_wording_differs(): void
    {
        $networkAccess = $this->offense(
            'MAJOR-NETWORK',
            'Accessing a university computer or network without authority',
            ['hacking'],
        );

        $results = (new ClassificationEngine)->classify(
            'The student entered the computer network without authority.'
        );

        $this->assertSame($networkAccess->id, $results->first()['offense']->id);
        $this->assertEqualsCanonicalizing(
            ['computer', 'network', 'authority'],
            $results->first()['matched_title_terms'],
        );
    }

    public function test_generic_report_boilerplate_is_not_treated_as_offense_evidence(): void
    {
        $drugOffense = $this->offense(
            'MAJOR-DRUG',
            'Unauthorized possession or use of prohibited drugs',
            ['student was found with'],
        );

        $results = (new ClassificationEngine)->classify(
            'The student was found with a borrowed library book.'
        );

        $this->assertFalse($results->pluck('offense.id')->contains($drugOffense->id));
    }

    public function test_unambiguous_report_category_strengthens_the_correct_offense(): void
    {
        $uniform = $this->offense(
            'MINOR-01',
            'Non-wearing of prescribed uniform',
            ['uniform'],
        );
        $id = $this->offense(
            'MINOR-02',
            'Non-wearing or failure to bring University ID',
            ['id'],
        );

        $results = (new ClassificationEngine)->classify(
            'The student entered through the main gate.',
            violationType: 'Not wearing University ID',
        );

        $this->assertSame($id->id, $results->first()['offense']->id);
        $this->assertSame(
            'Not wearing University ID',
            $results->first()['matched_violation_type'],
        );
        $this->assertNotSame($uniform->id, $results->first()['offense']->id);
    }

    public function test_exclusions_are_applied_before_the_top_three_are_selected(): void
    {
        $offenses = collect(range(1, 4))->map(fn (int $number): OffenseCatalog => $this->offense(
            "MAJOR-TIE-{$number}",
            "Catalog offense {$number}",
            ['shared evidence'],
        ));

        $results = (new ClassificationEngine)->classify(
            'The report contains shared evidence.',
            [$offenses->first()->id],
        );

        $this->assertCount(3, $results);
        $this->assertFalse($results->pluck('offense.id')->contains($offenses->first()->id));
    }

    public function test_controlled_inflections_match_the_same_offense_concepts(): void
    {
        $bullying = $this->offense(
            'MAJOR-BULLY-INFLECTION',
            'Bullying',
            ['bullying', 'intimidation'],
        );

        $results = (new ClassificationEngine)->classify(
            'The student repeatedly bullied and intimidated a classmate.'
        );

        $this->assertSame($bullying->id, $results->first()['offense']->id);
        $this->assertEqualsCanonicalizing(
            ['bullying', 'intimidation'],
            $results->first()['matched_keywords'],
        );
    }

    public function test_exact_specific_title_outranks_related_but_different_aliases(): void
    {
        $genderBasedHarassment = $this->offense(
            'MAJOR-GENDER-HARASSMENT',
            'Gender-based sexual harassment',
            ['harassment', 'sexual harassment', 'unwanted advances'],
        );
        $this->offense(
            'MAJOR-ADVANCES',
            'Making sexual advances',
            ['sexual advances', 'unwanted sexual'],
        );

        $results = (new ClassificationEngine)->classify(
            'A complaint reported unwanted advances and gender-based sexual harassment.'
        );

        $this->assertSame(
            $genderBasedHarassment->id,
            $results->first()['offense']->id,
        );
    }

    public function test_seeded_catalog_recognizes_high_signal_english_and_taglish_narratives(): void
    {
        $this->seed(OffenseCatalogSeeder::class);
        $engine = new ClassificationEngine;

        $samples = [
            'Nahuling nag vape ang estudyante sa loob ng campus.' => 'MAJOR-26',
            'Nangopya sa exam at gumamit ng kodigo ang estudyante.' => 'MAJOR-01',
            'The student threatened to hurt a classmate.' => 'MAJOR-27',
            'Binully at intimidated a classmate online.' => 'MAJOR-36',
            'Security found marijuana and drug paraphernalia in the bag.' => 'MAJOR-38',
        ];

        foreach ($samples as $narrative => $expectedCode) {
            $this->assertSame(
                $expectedCode,
                $engine->classify($narrative)->first()['offense']->code ?? null,
                "Unexpected top recommendation for: {$narrative}",
            );
        }

        $this->assertTrue(
            $engine->classify(
                'The student was found with a borrowed library book inside the laboratory.'
            )->isEmpty(),
        );
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
