<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\RiskFactorWeight;
use App\Models\RiskScore;
use App\Models\RiskScoreSnapshot;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes PRISM's deterministic, rule-based risk score for a student.
 * Not machine learning — weights come from the configurable
 * `risk_factor_weights` table and the formula is documented in
 * docs/RISK_SCORING_METHODOLOGY.md. Snapshots the result per academic term
 * so history and trend views have a stable record to read from.
 */
class RiskScoringService
{
    private ?Collection $activeWeights = null;

    public function computeForStudent(
        int $studentId,
        string $trigger = 'unspecified',
        ?int $triggeredBy = null
    ): RiskScore {
        $rawFactors = $this->gatherFactors($studentId);
        $result = $this->calculate($rawFactors, $this->weights());

        return DB::transaction(function () use ($studentId, $trigger, $triggeredBy, $result): RiskScore {
            $previous = RiskScore::query()
                ->where('student_id', $studentId)
                ->lockForUpdate()
                ->first();

            $previousScore = $previous ? (float) $previous->composite_score : null;
            $difference = $previousScore === null
                ? null
                : $result['composite_score'] - $previousScore;
            $trend = match (true) {
                $difference === null => null,
                $difference > 2 => 'increasing',
                $difference < -2 => 'decreasing',
                default => 'stable',
            };

            $score = RiskScore::updateOrCreate(
                ['student_id' => $studentId],
                [
                    'composite_score' => $result['composite_score'],
                    'risk_band' => $result['risk_band'],
                    'trend' => $trend,
                    'contributing_factors' => $result['factors'],
                    'computed_at' => now(),
                ]
            );

            RiskScoreSnapshot::create([
                'student_id' => $studentId,
                'snapshot_type' => 'event',
                'composite_score' => $result['composite_score'],
                'previous_composite_score' => $previousScore,
                'risk_band' => $result['risk_band'],
                'previous_risk_band' => $previous?->risk_band,
                'contributing_factors' => $result['factors'],
                'factor_changes' => $this->factorChanges(
                    $previous?->contributing_factors,
                    $result['factors']
                ),
                'methodology_version' => config('risk_scoring.methodology_version'),
                'trigger' => $trigger,
                'triggered_by' => $triggeredBy && $triggeredBy > 0 ? $triggeredBy : null,
                'term' => null,
                'snapshot_date' => today(),
                'created_at' => now(),
            ]);

            return $score;
        });
    }

    public function computeForAllStudents(
        string $trigger = 'scheduled_recompute',
        ?int $triggeredBy = null
    ): int {
        $this->activeWeights = null;
        $count = 0;

        foreach (User::query()
            ->where('role', 'student')
            ->whereHas('cases')
            ->select('id')
            ->lazyById() as $student) {
            $this->computeForStudent($student->id, $trigger, $triggeredBy);
            $count++;
        }

        return $count;
    }

    public function snapshotTerm(string $term): int
    {
        $count = 0;
        $now = now();

        RiskScore::query()->chunkById(500, function (Collection $scores) use ($term, $now, &$count): void {
            $snapshots = $scores->map(fn (RiskScore $score) => [
                'student_id' => $score->student_id,
                'snapshot_type' => 'term',
                'composite_score' => $score->composite_score,
                'previous_composite_score' => null,
                'risk_band' => $score->risk_band,
                'previous_risk_band' => null,
                'contributing_factors' => json_encode($score->contributing_factors),
                'factor_changes' => null,
                'methodology_version' => config('risk_scoring.methodology_version'),
                'trigger' => 'term_archive',
                'triggered_by' => null,
                'term' => $term,
                'snapshot_date' => $now->toDateString(),
                'created_at' => $now,
            ])->all();

            RiskScoreSnapshot::insert($snapshots);
            $count += count($snapshots);
        });

        return $count;
    }

    private function gatherFactors(int $studentId): array
    {
        $values = [];
        $evaluator = new RiskRuleEvaluator;
        foreach ($this->weights() as $factor) {
            $rule = $factor->rule ?? RiskRuleEvaluator::defaults($factor->factor_code);
            $count = $evaluator->count($studentId, $rule);
            $values[$factor->factor_code] = $factor->factor_type === 'boolean'
                ? (int) ($count >= (int) $rule['threshold'])
                : $count;
        }

        return $values;
    }

    private function weights(): Collection
    {
        if ($this->activeWeights !== null) {
            return $this->activeWeights;
        }

        $weights = RiskFactorWeight::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (RiskFactorWeight $factor) => $factor->rule !== null
                || RiskRuleEvaluator::defaults($factor->factor_code) !== null);
        $totalWeight = $weights->sum(fn (RiskFactorWeight $weight) => (float) $weight->weight);

        if ($weights->isEmpty() || abs($totalWeight - 1.0) > 0.0001) {
            throw new \LogicException(
                'Risk scoring requires at least one active factor and active weights totaling exactly 1.0000.'
            );
        }

        return $this->activeWeights = $weights;
    }

    private function factorChanges(?array $previousFactors, array $currentFactors): array
    {
        foreach ($previousFactors ?? [] as $code => $factor) {
            if (! array_key_exists($code, $currentFactors)) {
                $currentFactors[$code] = array_replace($factor, ['contribution' => 0]);
            }
        }

        return collect($currentFactors)
            ->map(function (array $factor, string $code) use ($previousFactors): array {
                $previousContribution = (float) ($previousFactors[$code]['contribution'] ?? 0);
                $currentContribution = (float) $factor['contribution'];

                return [
                    'factor_code' => $code,
                    'factor_name' => $factor['factor_name'],
                    'previous_contribution' => $previousContribution,
                    'current_contribution' => $currentContribution,
                    'delta' => round($currentContribution - $previousContribution, 2),
                ];
            })
            ->filter(fn (array $change) => abs($change['delta']) >= 0.01)
            ->sortByDesc(fn (array $change) => abs($change['delta']))
            ->values()
            ->all();
    }

    private function calculate(array $rawFactors, Collection $weights): array
    {
        $composite = 0.0;
        $factors = [];

        foreach ($weights as $weight) {
            $rawValue = $rawFactors[$weight->factor_code] ?? 0;

            if ($weight->factor_type === 'boolean') {
                $contribution = (bool) $rawValue ? ((float) $weight->weight * 100) : 0.0;
                $factors[$weight->factor_code] = [
                    'factor_name' => $weight->factor_name,
                    'description' => $weight->description,
                    'factor_type' => 'boolean',
                    'triggered' => (bool) $rawValue,
                    'weight' => (float) $weight->weight,
                    'contribution' => round($contribution, 2),
                ];
            } else {
                $cap = $weight->count_cap ?? 1;
                $cappedValue = min($rawValue, $cap);
                $contribution = ($cappedValue / $cap) * (float) $weight->weight * 100;
                $factors[$weight->factor_code] = [
                    'factor_name' => $weight->factor_name,
                    'description' => $weight->description,
                    'factor_type' => 'count',
                    'raw_value' => $rawValue,
                    'capped_value' => $cappedValue,
                    'cap' => $cap,
                    'weight' => (float) $weight->weight,
                    'contribution' => round($contribution, 2),
                ];
            }

            $factors[$weight->factor_code]['rule'] = $weight->rule ?? RiskRuleEvaluator::defaults($weight->factor_code);
            $composite += $contribution;
        }

        $composite = round($composite, 2);

        return [
            'composite_score' => $composite,
            'risk_band' => $this->resolveBand($composite),
            'factors' => $factors,
        ];
    }

    private function resolveBand(float $score): string
    {
        foreach (config('risk_scoring.bands') as $band => $threshold) {
            if ($score >= $threshold) {
                return $band;
            }
        }

        return 'low';
    }
}
