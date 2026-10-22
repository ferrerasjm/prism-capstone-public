<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OffenseCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic keyword-based offense classifier. This is the fallback
 * AdvisoryOffenseClassificationService uses when the ML advisory service is
 * disabled or unavailable, so its suggestions must degrade gracefully on
 * their own rather than depend on the model ever being reachable.
 */
class ClassificationEngine
{
    private const MAX_SUGGESTIONS = 3;

    private const MINIMUM_SCORE = 3.0;

    /**
     * Structured report categories with an unambiguous catalog counterpart.
     * Broad categories such as "Bringing prohibited items" are intentionally
     * excluded because they can describe several materially different offenses.
     */
    private const VIOLATION_TYPE_OFFENSE_CODES = [
        'not wear university uniform' => 'MINOR-01',
        'wear incomplete university uniform' => 'MINOR-01',
        'not wear university id' => 'MINOR-02',
        'not wear corporate attire' => 'MINOR-01',
        'wear inappropriate attire' => 'MINOR-03',
    ];

    /**
     * Common report and policy words are not useful by themselves when comparing
     * a narrative with an offense title.
     */
    private const TITLE_STOP_WORDS = [
        'a',
        'an',
        'and',
        'any',
        'as',
        'at',
        'be',
        'by',
        'commit',
        'during',
        'failure',
        'for',
        'from',
        'in',
        'inside',
        'into',
        'of',
        'on',
        'or',
        'other',
        'student',
        'the',
        'their',
        'to',
        'university',
        'without',
        'within',
    ];

    private const KEYWORD_STOP_WORDS = [
        'a',
        'an',
        'and',
        'another',
        'any',
        'as',
        'at',
        'be',
        'been',
        'being',
        'by',
        'campus',
        'caught',
        'commit',
        'during',
        'enter',
        'for',
        'found',
        'from',
        'had',
        'has',
        'have',
        'in',
        'inside',
        'into',
        'is',
        'near',
        'observe',
        'of',
        'on',
        'or',
        'report',
        'seen',
        'spot',
        'student',
        'the',
        'their',
        'to',
        'university',
        'using',
        'was',
        'were',
        'with',
        'without',
    ];

    private const PHRASE_FILLER_WORDS = ['a', 'an', 'the'];

    private const WEAK_SINGLE_KEYWORDS = [
        'area',
        'bathroom',
        'campus',
        'classroom',
        'corridor',
        'hallway',
        'laboratory',
        'library',
        'office',
        'restroom',
        'room',
    ];

    /**
     * Controlled inflection aliases improve ordinary narrative matching without
     * treating unrelated words as synonyms.
     */
    private const TOKEN_NORMALIZATIONS = [
        'accessed' => 'access',
        'accessing' => 'access',
        'bullied' => 'bully',
        'bullies' => 'bully',
        'bullying' => 'bully',
        'brought' => 'bring',
        'carried' => 'carry',
        'carrying' => 'carry',
        'copied' => 'copy',
        'copying' => 'copy',
        'during' => 'during',
        'fought' => 'fight',
        'gambled' => 'gamble',
        'gambling' => 'gamble',
        'harassed' => 'harass',
        'harassing' => 'harass',
        'harassment' => 'harass',
        'hazed' => 'haze',
        'hazing' => 'haze',
        'hid' => 'hide',
        'hiding' => 'hide',
        'intimidated' => 'intimidate',
        'intimidating' => 'intimidate',
        'intimidation' => 'intimidate',
        'observed' => 'observe',
        'observing' => 'observe',
        'piercing' => 'pierce',
        'possessed' => 'possess',
        'possessing' => 'possess',
        'possession' => 'possess',
        'reported' => 'report',
        'reporting' => 'report',
        'smoked' => 'smoke',
        'smoking' => 'smoke',
        'stole' => 'steal',
        'stolen' => 'steal',
        'taken' => 'take',
        'threatened' => 'threat',
        'threatening' => 'threat',
        'threats' => 'threat',
        'took' => 'take',
        'vaped' => 'vape',
        'vaping' => 'vape',
        'wore' => 'wear',
        'worn' => 'wear',
    ];

    /**
     * Score a report narrative against the offense catalog.
     *
     * The result remains advisory: a Discipline Officer must confirm the offense.
     * Longer and catalog-specific phrases carry more weight than generic words,
     * and nested phrases are counted once so a verbose keyword list cannot
     * artificially inflate a result.
     *
     * @return Collection<int, array{
     *     offense: OffenseCatalog,
     *     score: float,
     *     matched_keywords: array<int, string>,
     *     matched_title_terms: array<int, string>,
     *     matched_violation_type: string|null
     * }>
     */
    public function classify(
        string $description,
        array $excludedOffenseIds = [],
        ?string $violationType = null,
    ): Collection {
        $normalizedDescription = $this->canonicalize($description);
        $normalizedViolationType = $this->canonicalize((string) $violationType);

        if ($normalizedDescription === '' && $normalizedViolationType === '') {
            return collect();
        }

        $offenses = OffenseCatalog::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get([
                'id',
                'code',
                'offense_type',
                'title',
                'description',
                'keywords',
                'special_consequence',
            ]);

        if ($offenses->isEmpty()) {
            return collect();
        }

        $keywordFrequencies = $this->keywordFrequencies($offenses);
        $descriptionTokens = array_fill_keys($this->tokens($normalizedDescription), true);

        $excludedOffenseIds = array_fill_keys(
            array_map('intval', $excludedOffenseIds),
            true,
        );

        return $offenses
            ->reject(fn (OffenseCatalog $offense): bool => isset($excludedOffenseIds[$offense->id]))
            ->map(fn (OffenseCatalog $offense): array => $this->scoreOffense(
                $offense,
                $normalizedDescription,
                $descriptionTokens,
                $keywordFrequencies,
                $offenses->count(),
                $normalizedViolationType,
                $violationType,
            ))
            ->filter(fn (array $result): bool => $result['score'] >= self::MINIMUM_SCORE)
            ->sort(function (array $left, array $right): int {
                $byScore = $right['score'] <=> $left['score'];

                if ($byScore !== 0) {
                    return $byScore;
                }

                $byKeywordCount = count($right['matched_keywords'])
                    <=> count($left['matched_keywords']);

                return $byKeywordCount !== 0
                    ? $byKeywordCount
                    : strcmp($left['offense']->code, $right['offense']->code);
            })
            ->values()
            ->take(self::MAX_SUGGESTIONS);
    }

    public function isInTopSuggestions(
        string $description,
        int $offenseId,
        ?string $violationType = null,
    ): bool {
        return $this->classify($description, violationType: $violationType)
            ->pluck('offense.id')
            ->contains($offenseId);
    }

    /**
     * @param  array<string, true>  $descriptionTokens
     * @param  array<string, int>  $keywordFrequencies
     * @return array{
     *     offense: OffenseCatalog,
     *     score: float,
     *     matched_keywords: array<int, string>,
     *     matched_title_terms: array<int, string>,
     *     matched_violation_type: string|null
     * }
     */
    private function scoreOffense(
        OffenseCatalog $offense,
        string $normalizedDescription,
        array $descriptionTokens,
        array $keywordFrequencies,
        int $offenseCount,
        string $normalizedViolationType,
        ?string $violationType,
    ): array {
        $matchingKeywords = collect($offense->keywords ?? [])
            ->filter(fn (mixed $keyword): bool => is_string($keyword))
            ->map(function (string $keyword) use ($descriptionTokens): array {
                $normalized = $this->canonicalize($keyword);
                $informativeTerms = $this->informativeKeywordTerms($normalized);
                $matchedInformativeTerms = array_filter(
                    $informativeTerms,
                    fn (string $term): bool => isset($descriptionTokens[$term]),
                );

                return [
                    'original' => trim($keyword),
                    'normalized' => $normalized,
                    'token_count' => count($this->tokens($normalized)),
                    'informative_terms' => $informativeTerms,
                    'informative_token_count' => count($informativeTerms),
                    'informative_coverage' => $informativeTerms === []
                        ? 0.0
                        : count($matchedInformativeTerms) / count($informativeTerms),
                    'match_strength' => 0.0,
                ];
            })
            ->filter(fn (array $keyword): bool => $keyword['normalized'] !== '')
            ->filter(fn (array $keyword): bool => $keyword['informative_token_count'] > 0)
            ->reject(fn (array $keyword): bool => $keyword['informative_token_count'] === 1
                && in_array(
                    $keyword['informative_terms'][0],
                    self::WEAK_SINGLE_KEYWORDS,
                    true,
                ))
            ->unique('normalized')
            ->map(function (array $keyword) use (
                $normalizedDescription,
                $descriptionTokens
            ): array {
                $isExactMatch = $this->containsPhrase(
                    $normalizedDescription,
                    $keyword['normalized'],
                );
                $isConceptMatch = ! $isExactMatch
                    && $keyword['informative_token_count'] >= 2
                    && $keyword['informative_coverage'] >= 0.66
                    && isset($descriptionTokens[$keyword['informative_terms'][0]]);

                $keyword['match_strength'] = $isExactMatch
                    ? 1.0
                    : ($isConceptMatch ? 0.55 : 0.0);

                return $keyword;
            })
            ->filter(fn (array $keyword): bool => $keyword['match_strength'] > 0)
            ->sort(function (array $left, array $right): int {
                $byMatchStrength = $right['match_strength'] <=> $left['match_strength'];

                if ($byMatchStrength !== 0) {
                    return $byMatchStrength;
                }

                $byInformativeTerms = $right['informative_token_count']
                    <=> $left['informative_token_count'];

                if ($byInformativeTerms !== 0) {
                    return $byInformativeTerms;
                }

                $byTokenCount = $right['token_count'] <=> $left['token_count'];

                return $byTokenCount !== 0
                    ? $byTokenCount
                    : strlen($right['normalized']) <=> strlen($left['normalized']);
            })
            ->values()
            ->all();

        $matchingKeywords = $this->removeNestedKeywordMatches($matchingKeywords);

        $keywordScore = array_reduce(
            $matchingKeywords,
            fn (float $score, array $keyword): float => $score + $this->keywordWeight(
                $keyword,
                $keywordFrequencies[$keyword['normalized']] ?? 1,
                $offenseCount,
            ),
            0.0,
        );

        $normalizedTitle = $this->canonicalize($offense->title);
        $titleTerms = $this->significantTitleTerms($normalizedTitle);
        $matchedTitleTerms = array_values(array_filter(
            $titleTerms,
            fn (string $term): bool => isset($descriptionTokens[$term]),
        ));

        $titleScore = 0.0;
        if ($normalizedTitle !== '' && $this->containsPhrase($normalizedDescription, $normalizedTitle)) {
            $titleScore = 20.0;
        } elseif (count($titleTerms) >= 2) {
            $coverage = count($matchedTitleTerms) / count($titleTerms);

            if (count($matchedTitleTerms) >= 2 && $coverage >= 0.5) {
                $titleScore = 2.0 + (5.0 * $coverage);
            }
        }

        $mappedOffenseCode = self::VIOLATION_TYPE_OFFENSE_CODES[$normalizedViolationType] ?? null;
        $matchesViolationType = $mappedOffenseCode !== null
            && hash_equals($mappedOffenseCode, $offense->code);

        return [
            'offense' => $offense,
            'score' => round($keywordScore + $titleScore + ($matchesViolationType ? 14.0 : 0.0), 2),
            'matched_keywords' => array_values(array_column($matchingKeywords, 'original')),
            'matched_title_terms' => $matchedTitleTerms,
            'matched_violation_type' => $matchesViolationType ? $violationType : null,
        ];
    }

    /**
     * Count how many catalog entries use each phrase. Shared phrases such as
     * "harassment" are deliberately less influential than unique phrases.
     *
     * @param  Collection<int, OffenseCatalog>  $offenses
     * @return array<string, int>
     */
    private function keywordFrequencies(Collection $offenses): array
    {
        $frequencies = [];

        foreach ($offenses as $offense) {
            $phrasesForOffense = [];

            foreach ($offense->keywords ?? [] as $keyword) {
                if (! is_string($keyword)) {
                    continue;
                }

                $normalized = $this->canonicalize($keyword);
                if ($normalized !== '') {
                    $phrasesForOffense[$normalized] = true;
                }
            }

            foreach (array_keys($phrasesForOffense) as $phrase) {
                $frequencies[$phrase] = ($frequencies[$phrase] ?? 0) + 1;
            }
        }

        return $frequencies;
    }

    /**
     * @param  array<int, array{
     *     original: string,
     *     normalized: string,
     *     token_count: int,
     *     informative_terms: array<int, string>,
     *     informative_token_count: int,
     *     informative_coverage: float,
     *     match_strength: float
     * }>  $matches
     * @return array<int, array{
     *     original: string,
     *     normalized: string,
     *     token_count: int,
     *     informative_terms: array<int, string>,
     *     informative_token_count: int,
     *     informative_coverage: float,
     *     match_strength: float
     * }>
     */
    private function removeNestedKeywordMatches(array $matches): array
    {
        $selected = [];

        foreach ($matches as $candidate) {
            $isContainedByStrongerMatch = collect($selected)->contains(
                fn (array $selectedKeyword): bool => $this->containsPhrase(
                    $selectedKeyword['normalized'],
                    $candidate['normalized'],
                ) || $this->sharesKeywordConcept($selectedKeyword, $candidate)
            );

            if (! $isContainedByStrongerMatch) {
                $selected[] = $candidate;
            }
        }

        return $selected;
    }

    /**
     * @param  array{
     *     original: string,
     *     normalized: string,
     *     token_count: int,
     *     informative_terms: array<int, string>,
     *     informative_token_count: int,
     *     informative_coverage: float,
     *     match_strength: float
     * }  $keyword
     */
    private function keywordWeight(array $keyword, int $frequency, int $offenseCount): float
    {
        $baseWeight = match (true) {
            $keyword['informative_token_count'] === 1 => 3.0,
            $keyword['informative_token_count'] === 2 => 4.0,
            default => min(9.0, 5.0 + $keyword['informative_token_count']),
        };

        $rarityRatio = max(1.0, $offenseCount / max(1, $frequency));
        $specificityMultiplier = 1.0 + min(1.0, log($rarityRatio, 2) / 6);

        return $baseWeight * $specificityMultiplier * $keyword['match_strength'];
    }

    /**
     * @param  array{informative_terms: array<int, string>}  $left
     * @param  array{informative_terms: array<int, string>}  $right
     */
    private function sharesKeywordConcept(array $left, array $right): bool
    {
        $smallestTermCount = min(
            count($left['informative_terms']),
            count($right['informative_terms']),
        );

        if ($smallestTermCount === 0) {
            return false;
        }

        $sharedTermCount = count(array_intersect(
            $left['informative_terms'],
            $right['informative_terms'],
        ));

        return ($sharedTermCount / $smallestTermCount) >= 0.67;
    }

    /**
     * @return array<int, string>
     */
    private function significantTitleTerms(string $normalizedTitle): array
    {
        return array_values(array_unique(array_filter(
            $this->tokens($normalizedTitle),
            fn (string $token): bool => ! in_array($token, self::TITLE_STOP_WORDS, true)
                && (strlen($token) >= 3 || in_array($token, ['ai', 'id'], true)),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function informativeKeywordTerms(string $normalizedKeyword): array
    {
        return array_values(array_unique(array_filter(
            $this->tokens($normalizedKeyword),
            fn (string $token): bool => ! in_array($token, self::KEYWORD_STOP_WORDS, true)
                && (strlen($token) >= 3 || in_array($token, ['ai', 'id'], true)),
        )));
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        if ($phrase === '' || $text === '') {
            return false;
        }

        if (str_contains(" {$text} ", " {$phrase} ")) {
            return true;
        }

        $textWithoutFillers = $this->withoutPhraseFillers($text);
        $phraseWithoutFillers = $this->withoutPhraseFillers($phrase);

        return $phraseWithoutFillers !== ''
            && str_contains(" {$textWithoutFillers} ", " {$phraseWithoutFillers} ");
    }

    private function canonicalize(string $text): string
    {
        $text = mb_strtolower(Str::ascii($text));
        $text = preg_replace('/\b([a-z])\s*\.\s*([a-z])\b/u', '$1$2', $text);
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return implode(' ', array_map(
            fn (string $token): string => $this->canonicalToken($token),
            $this->tokens(trim($text)),
        ));
    }

    private function canonicalToken(string $token): string
    {
        if (isset(self::TOKEN_NORMALIZATIONS[$token])) {
            return self::TOKEN_NORMALIZATIONS[$token];
        }

        $token = strlen($token) > 3 ? Str::singular($token) : $token;

        if (isset(self::TOKEN_NORMALIZATIONS[$token])) {
            return self::TOKEN_NORMALIZATIONS[$token];
        }

        if (strlen($token) >= 6 && str_ends_with($token, 'ing')) {
            $stem = substr($token, 0, -3);

            if (strlen($stem) >= 3 && substr($stem, -1) === substr($stem, -2, 1)) {
                $stem = substr($stem, 0, -1);
            }

            return self::TOKEN_NORMALIZATIONS[$stem] ?? $stem;
        }

        if (strlen($token) >= 5 && str_ends_with($token, 'ied')) {
            return substr($token, 0, -3).'y';
        }

        if (strlen($token) >= 6 && str_ends_with($token, 'ed')) {
            $stem = substr($token, 0, -2);

            if (strlen($stem) >= 3 && substr($stem, -1) === substr($stem, -2, 1)) {
                $stem = substr($stem, 0, -1);
            }

            return self::TOKEN_NORMALIZATIONS[$stem] ?? $stem;
        }

        return $token;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $text): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', trim($text)) ?: [],
            static fn (string $token): bool => $token !== '',
        ));
    }

    private function withoutPhraseFillers(string $text): string
    {
        return implode(' ', array_filter(
            $this->tokens($text),
            static fn (string $token): bool => ! in_array(
                $token,
                self::PHRASE_FILLER_WORDS,
                true,
            ),
        ));
    }
}
