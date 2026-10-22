<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OffenseCatalog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Advisory-only wrapper around the external offense-classification model.
 * Suggestions are never auto-applied — a human always confirms the offense
 * before a case is classified (see OffenseRecommendationAuditService, which
 * records every suggestion and the eventual decision). Falls back to the
 * deterministic ClassificationEngine if the model service is disabled,
 * unreachable, or errors.
 */
class AdvisoryOffenseClassificationService
{
    private const DISPLAY_SUGGESTION_COUNT = 3;

    /**
     * Below this model score the top-ranked offense is unreliable enough to
     * warn the officer. Calibrated on the v6 hybrid: under 0.20 the first
     * suggestion was right only 46-54% of the time and the correct offense
     * was among the three cards in 69-91% of cases, while between 0.20 and
     * 0.35 it was right 85-87% of the time and on screen in 97-100%. The
     * previous 0.35 flagged 17-19% of cases, most of them ranked correctly.
     */
    private const LOW_CONFIDENCE_SCORE = 0.20;

    /**
     * Below this score the ranking is the flat tail of the distribution
     * rather than a recommendation - typically what is left after a
     * guardrail suppresses a wrongly ranked offense. On the held-out set
     * the weakest correct top-ranked prediction scored 0.056, so this floor
     * discards noise without discarding a genuine suggestion. It is not an
     * accuracy gate: wrong predictions routinely score far above it.
     */
    private const NOISE_FLOOR_SCORE = 0.05;

    /** Codes intentionally excluded from narrative-only automated ranking. */
    private const MANUAL_ONLY_CODES = [
        'MAJOR-16', // No applicable campus publication/newsstand operation.
        'MAJOR-19', // Derived from confirmed offense history.
        'MAJOR-22', // No student-athlete dormitory at NU Clark.
    ];

    public function __construct(private readonly ClassificationEngine $keywordEngine) {}

    /**
     * Rank possible offenses without selecting or confirming any classification.
     *
     * @return array{
     *   source: string,
     *   status: string,
     *   suggestions: Collection<int, array<string, mixed>>,
     *   predictions: array<int, array{rank: int, label: string, score: float}>,
     *   model: array<string, mixed>|null,
     *   no_match: bool,
     *   guardrail: array<string, mixed>|null,
     *   notice: string|null
     * }
     */
    public function classify(
        string $description,
        array $excludedOffenseIds = [],
        ?string $violationType = null,
    ): array {
        $description = trim($description);
        if ($description === '') {
            return $this->emptyResult('No incident description was provided.');
        }

        if (! config('offense_classification.enabled', false)) {
            return $this->keywordFallback(
                $description,
                $excludedOffenseIds,
                $violationType,
                'The language model is disabled; keyword recommendations are shown as a fallback.',
            );
        }

        try {
            $excludedCodes = OffenseCatalog::query()
                ->whereIn('id', array_map('intval', $excludedOffenseIds))
                ->pluck('code')
                ->reject(fn (string $code): bool => in_array($code, self::MANUAL_ONLY_CODES, true))
                ->all();

            $request = Http::acceptJson()
                ->asJson()
                ->connectTimeout((float) config('offense_classification.connect_timeout_seconds', 1))
                ->timeout((float) config('offense_classification.timeout_seconds', 15));

            $token = trim((string) config('offense_classification.token', ''));
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->post(
                rtrim((string) config('offense_classification.url'), '/').'/predict',
                [
                    'text' => $description,
                    // Request one extra candidate because NO_MATCH is not an offense card.
                    'top_k' => min(10, self::DISPLAY_SUGGESTION_COUNT + 1),
                    'excluded_labels' => array_values($excludedCodes),
                ],
            );

            if (! $response->successful()) {
                throw new \RuntimeException('The model service returned an unsuccessful response.', $response->status());
            }

            $payload = $response->json();
            $predictions = $this->validatePredictions($payload['predictions'] ?? null);
            $guardrail = is_array($payload['guardrail'] ?? null) ? $payload['guardrail'] : null;
            $suggestions = $this->mapPredictionsToCatalog($predictions, $guardrail);

            return [
                'source' => 'hybrid_model',
                'status' => 'success',
                'suggestions' => $suggestions,
                'predictions' => $predictions,
                'model' => is_array($payload['model'] ?? null) ? $payload['model'] : null,
                'no_match' => (bool) ($payload['no_match'] ?? false),
                'guardrail' => $guardrail,
                'notice' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Advisory offense model unavailable; using keyword fallback.', [
                'exception' => $exception::class,
                'http_status' => isset($response) ? $response->status() : null,
            ]);

            return $this->keywordFallback(
                $description,
                $excludedOffenseIds,
                $violationType,
                'The language model is temporarily unavailable; keyword recommendations are shown as a fallback.',
            );
        }
    }

    public function isInTopSuggestions(
        string $description,
        int $offenseId,
        ?string $violationType = null,
        array $excludedOffenseIds = [],
    ): bool {
        $classification = $this->classify(
            $description,
            $excludedOffenseIds,
            $violationType,
        );

        return $classification['suggestions']
            ->pluck('offense.id')
            ->contains($offenseId);
    }

    /** @return array<int, array{rank: int, label: string, score: float}> */
    private function validatePredictions(mixed $predictions): array
    {
        if (! is_array($predictions) || count($predictions) === 0) {
            throw new \UnexpectedValueException('The model returned no predictions.');
        }

        $validated = [];
        foreach ($predictions as $prediction) {
            if (! is_array($prediction)
                || ! is_string($prediction['label'] ?? null)
                || ! is_numeric($prediction['score'] ?? null)
                || ! is_numeric($prediction['rank'] ?? null)
            ) {
                throw new \UnexpectedValueException('The model returned an invalid prediction.');
            }

            $score = (float) $prediction['score'];
            if ($score < 0 || $score > 1) {
                throw new \UnexpectedValueException('The model returned an invalid score.');
            }

            $validated[] = [
                'rank' => (int) $prediction['rank'],
                'label' => $prediction['label'],
                'score' => $score,
            ];
        }

        usort($validated, fn (array $left, array $right): int => $left['rank'] <=> $right['rank']);

        return $validated;
    }

    /**
     * @param  array<int, array{rank: int, label: string, score: float}>  $predictions
     * @param  array<string, mixed>|null  $guardrail
     */
    private function mapPredictionsToCatalog(array $predictions, ?array $guardrail = null): Collection
    {
        // A promoted top rank was decided by a handbook rule, so the percentage
        // beside it is the model's own score and not the reason it is ranked
        // first. The card says so rather than presenting the two as the same.
        $promotedByRule = ($guardrail['applied'] ?? false)
            && ($guardrail['action'] ?? null) === 'promoted';

        $codes = collect($predictions)
            ->pluck('label')
            ->reject(fn (string $label): bool => $label === 'NO_MATCH')
            ->values();
        $offenses = OffenseCatalog::query()
            ->where('is_active', true)
            ->whereIn('code', $codes->all())
            ->get([
                'id',
                'code',
                'offense_type',
                'title',
                'description',
                'keywords',
                'special_consequence',
            ])
            ->keyBy('code');

        $candidates = collect($predictions)
            ->reject(fn (array $prediction): bool => $prediction['label'] === 'NO_MATCH')
            ->filter(fn (array $prediction): bool => $offenses->has($prediction['label']))
            ->values();

        if ($candidates->isEmpty()) {
            return collect();
        }

        $lead = $candidates->first();
        $leadPromoted = $promotedByRule && $lead['rank'] === 1;
        // Confident on its own score, not merely surviving because a rule
        // rescued it -- a promoted-but-still-noise-level lead (e.g. a bare
        // "gun" ranked first at 2% by rule) must not drag an equally noisy,
        // unrelated rank 2 along with it. Promotion reorders without rewriting
        // scores, so this is always the lead label's own probability.
        $leadConfident = $lead['score'] >= self::NOISE_FLOOR_SCORE;

        if (! $leadConfident && ! $leadPromoted) {
            // Even the strongest real match is noise-level, so no suggestions is
            // more honest than a list of near-zero guesses -- the officer is
            // shown the catalog search instead.
            return collect();
        }

        // Once the lead is confident in its own right, ranks 2-3 are kept
        // regardless of their own score: they are already understood as
        // secondary options, and each renders its own low-confidence styling
        // (LOW_CONFIDENCE_SCORE) rather than being silently dropped. Gating every
        // rank on the same floor is what collapsed the 3-card panel down to a
        // single suggestion once the v5 model started scoring confidently on one
        // label and thinly everywhere else -- the floor was calibrated against
        // v3's flatter distribution, not v5's more decisive one. A merely
        // rule-promoted (not confident) lead does not extend that grace to the
        // rest of an otherwise flat, noise-level ranking.
        $relaxFloorBelowLead = $leadConfident;

        // A promotion settles rank 1 only. The ranks below it are ordinary
        // model suggestions and are kept or dropped on the same noise floor as
        // any other case, so every guardrail presents the panel the same way.
        return $candidates
            ->filter(fn (array $prediction, int $index): bool => $index === 0
                || $relaxFloorBelowLead
                || $prediction['score'] >= self::NOISE_FLOOR_SCORE)
            ->map(function (array $prediction) use ($offenses, $promotedByRule): array {
                $ruleBacked = $promotedByRule && $prediction['rank'] === 1;

                return [
                    'offense' => $offenses->get($prediction['label']),
                    'score' => round($prediction['score'] * 100, 2),
                    'display_percentage' => round($prediction['score'] * 100, 2),
                    'percentage_label' => $ruleBacked ? 'Handbook rule' : 'Model Score',
                    'model_score' => $prediction['score'],
                    'model_rank' => $prediction['rank'],
                    'rule_backed' => $ruleBacked,
                    'low_confidence' => ! $ruleBacked
                        && $prediction['score'] < self::LOW_CONFIDENCE_SCORE,
                    'matched_keywords' => [],
                    'matched_title_terms' => [],
                    'matched_violation_type' => null,
                    'source' => 'hybrid_model',
                ];
            })
            ->take(self::DISPLAY_SUGGESTION_COUNT)
            ->values();
    }

    private function keywordFallback(
        string $description,
        array $excludedOffenseIds,
        ?string $violationType,
        string $notice,
    ): array {
        $manualOnlyIds = OffenseCatalog::query()
            ->whereIn('code', self::MANUAL_ONLY_CODES)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $excludedOffenseIds = array_values(array_unique([
            ...array_map('intval', $excludedOffenseIds),
            ...$manualOnlyIds,
        ]));
        $suggestions = $this->keywordEngine
            ->classify($description, $excludedOffenseIds, $violationType)
            ->map(function (array $suggestion): array {
                $suggestion['source'] = 'keyword_fallback';
                $suggestion['rule_backed'] = false;
                $suggestion['low_confidence'] = false;

                return $suggestion;
            })
            ->take(self::DISPLAY_SUGGESTION_COUNT);
        $totalScore = (float) $suggestions->sum('score');
        $suggestions = $suggestions->map(function (array $suggestion) use ($totalScore): array {
            $suggestion['display_percentage'] = $totalScore > 0
                ? round(((float) $suggestion['score'] / $totalScore) * 100, 2)
                : 0.0;
            $suggestion['percentage_label'] = 'Relative match';

            return $suggestion;
        });

        return [
            'source' => 'keyword_fallback',
            'status' => 'fallback',
            'suggestions' => $suggestions,
            'predictions' => $suggestions->values()->map(fn (array $suggestion, int $index): array => [
                'rank' => $index + 1,
                'label' => $suggestion['offense']->code,
                'score' => (float) $suggestion['display_percentage'] / 100,
            ])->all(),
            'model' => null,
            'no_match' => false,
            'guardrail' => null,
            'notice' => $notice,
        ];
    }

    private function emptyResult(string $notice): array
    {
        return [
            'source' => 'none',
            'status' => 'empty',
            'suggestions' => collect(),
            'predictions' => [],
            'model' => null,
            'no_match' => false,
            'guardrail' => null,
            'notice' => $notice,
        ];
    }
}
