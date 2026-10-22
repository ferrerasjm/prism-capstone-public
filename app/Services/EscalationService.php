<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CaseOffense;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinePolicySetting;
use App\Models\OffenseCatalog;

/**
 * Evaluates whether a student's case history triggers habitual-offense
 * escalation. A confirmed MAJOR-19 charge closes the current escalation
 * cycle but never removes the historical findings that led to it — the
 * record stays intact even after escalation resolves.
 */
class EscalationService
{
    /**
     * Evaluate the current escalation cycle. A confirmed MAJOR-19 charge
     * closes the preceding cycle but does not remove historical findings.
     *
     * @return array{escalation_flagged: bool, escalation_flag_reason: string|null, suggested_offense_override_id: int|null}
     */
    public function check(DisciplinaryCase $case): array
    {
        $progress = $this->progress($case);
        if ($progress['current_minor_charge_count'] === 0) {
            return $this->noEscalation();
        }

        $major19 = OffenseCatalog::where('code', 'MAJOR-19')->first();

        if ($progress['current_same_type_count'] >= $progress['same_type_threshold']) {
            return [
                'escalation_flagged' => true,
                'escalation_flag_reason' => $progress['same_type_threshold'] === 3
                    ? 'Third offense of the same type in the current escalation cycle.'
                    : 'Same-type minor offense threshold of '.$progress['same_type_threshold'].' reached in the current escalation cycle.',
                'suggested_offense_override_id' => $major19?->id,
            ];
        }

        if ($progress['different_type_count'] >= $progress['different_type_threshold']) {
            return [
                'escalation_flagged' => true,
                'escalation_flag_reason' => $progress['different_type_threshold'] === 4
                    ? 'Fourth minor offense finding of a different kind in the current escalation cycle.'
                    : 'Different-type minor offense threshold of '.$progress['different_type_threshold'].' reached in the current escalation cycle.',
                'suggested_offense_override_id' => $major19?->id,
            ];
        }

        return $this->noEscalation();
    }

    /**
     * Return the counters used by the habitual-disregard rules.
     *
     * @return array{
     *     same_type_count: int,
     *     current_same_type_count: int,
     *     different_type_count: int,
     *     current_minor_charge_count: int,
     *     same_type_threshold: int,
     *     different_type_threshold: int,
     *     last_escalation_at: string|null
     * }
     */
    public function progress(DisciplinaryCase $case): array
    {
        $settings = DisciplinePolicySetting::current();
        $lastEscalation = CaseOffense::where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('code', 'MAJOR-19'))
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $case->student_id)
                ->whereNotIn('status', ['dismissed', 'voided']))
            ->latest('id')
            ->first(['id', 'confirmed_at']);

        $currentMinorFindings = $case->caseOffenses()
            ->where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('offense_type', 'minor'))
            ->when($lastEscalation, fn ($query) => $query->where('id', '>', $lastEscalation->id))
            ->orderBy('id')
            ->get(['id', 'offense_catalog_id']);

        $firstCurrentFindingId = $currentMinorFindings->min('id');

        $priorMinorFindings = CaseOffense::where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('offense_type', 'minor'))
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $case->student_id)
                ->where('id', '!=', $case->id)
                ->whereNotIn('status', ['dismissed', 'voided']))
            ->when($lastEscalation, fn ($query) => $query->where('id', '>', $lastEscalation->id))
            ->when($firstCurrentFindingId, fn ($query) => $query->where('id', '<', $firstCurrentFindingId))
            ->pluck('offense_catalog_id');

        $currentOffenseIds = $currentMinorFindings->pluck('offense_catalog_id');

        $priorCounts = $priorMinorFindings->countBy();
        $allMinorOffenseIds = $priorMinorFindings->concat($currentOffenseIds);
        $allCounts = $allMinorOffenseIds->countBy();
        $currentSameTypeCount = $currentOffenseIds
            ->map(fn ($offenseId) => (int) ($priorCounts[$offenseId] ?? 0) + 1)
            ->max() ?? 0;

        return [
            'same_type_count' => (int) ($allCounts->max() ?? 0),
            'current_same_type_count' => (int) $currentSameTypeCount,
            'different_type_count' => $allMinorOffenseIds->unique()->count(),
            'current_minor_charge_count' => $currentOffenseIds->count(),
            'same_type_threshold' => $settings->same_type_minor_threshold,
            'different_type_threshold' => $settings->different_type_minor_threshold,
            'last_escalation_at' => $lastEscalation?->confirmed_at?->toIso8601String(),
        ];
    }

    /**
     * The same counters as progress(), but for a student rather than a single
     * case — what the student themselves is shown on their My Cases page, so
     * they can see how close their minor findings sit to a MAJOR-19 escalation.
     *
     * @return array{
     *     same_type_count: int,
     *     same_type_threshold: int,
     *     different_type_count: int,
     *     different_type_threshold: int,
     *     total_findings: int,
     *     leading_offense: array{code: string, title: string, count: int}|null,
     *     last_escalation_at: string|null
     * }
     */
    public function studentProgress(int $studentId): array
    {
        $settings = DisciplinePolicySetting::current();
        $lastEscalation = CaseOffense::where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('code', 'MAJOR-19'))
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['dismissed', 'voided']))
            ->latest('id')
            ->first(['id', 'confirmed_at']);

        $findings = CaseOffense::where('status', 'confirmed')
            ->whereHas('offense', fn ($query) => $query->where('offense_type', 'minor'))
            ->whereHas('case', fn ($query) => $query
                ->where('student_id', $studentId)
                ->whereNotIn('status', ['dismissed', 'voided']))
            ->when($lastEscalation, fn ($query) => $query->where('id', '>', $lastEscalation->id))
            ->with('offense:id,code,title')
            ->orderBy('id')
            ->get(['id', 'offense_catalog_id']);

        $counts = $findings->countBy('offense_catalog_id');

        // The offense driving the same-type track; ties go to the one most
        // recently confirmed, which is the one the student just heard about.
        $leadingId = $findings
            ->reverse()
            ->sortByDesc(fn ($finding) => $counts[$finding->offense_catalog_id] ?? 0)
            ->first()?->offense_catalog_id;

        $leadingOffense = $findings->firstWhere('offense_catalog_id', $leadingId)?->offense;

        return [
            'same_type_count' => (int) ($counts->max() ?? 0),
            'same_type_threshold' => $settings->same_type_minor_threshold,
            'different_type_count' => $counts->count(),
            'different_type_threshold' => $settings->different_type_minor_threshold,
            'total_findings' => $findings->count(),
            'leading_offense' => $leadingOffense ? [
                'code' => (string) $leadingOffense->code,
                'title' => (string) $leadingOffense->title,
                'count' => (int) ($counts[$leadingId] ?? 0),
            ] : null,
            'last_escalation_at' => $lastEscalation?->confirmed_at?->toIso8601String(),
        ];
    }

    private function noEscalation(): array
    {
        return [
            'escalation_flagged' => false,
            'escalation_flag_reason' => null,
            'suggested_offense_override_id' => null,
        ];
    }
}
