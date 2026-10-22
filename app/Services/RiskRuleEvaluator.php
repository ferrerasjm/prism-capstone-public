<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DisciplinaryCase;
use App\Models\Sanction;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RiskRuleEvaluator
{
    public const SOURCES = [
        'confirmed_cases' => 'Confirmed Violations',
        'open_cases' => 'Ongoing Cases',
        'escalated_cases' => 'Escalated Cases',
        'non_compliant_sanctions' => 'Sanction Non-Compliance',
    ];

    public const SOURCE_HELP = [
        'confirmed_cases' => 'Resolved cases with a confirmed offense.',
        'open_cases' => 'Cases still under review or awaiting resolution.',
        'escalated_cases' => 'Escalated cases, excluding dismissed or voided cases.',
        'non_compliant_sanctions' => 'Non-compliant sanctions or evaluations needing further intervention.',
    ];

    public static function defaults(string $code): ?array
    {
        $base = ['source' => 'confirmed_cases', 'case_type' => 'any', 'months' => null, 'threshold' => 1];

        return match ($code) {
            'total_offense_count' => $base,
            'recent_violation' => array_replace($base, ['months' => 6]),
            'major_offense_count' => array_replace($base, ['case_type' => 'major']),
            'repeat_offender_flag' => array_replace($base, ['threshold' => 2]),
            'unresolved_cases' => array_replace($base, ['source' => 'open_cases']),
            'escalation_flag' => array_replace($base, ['source' => 'escalated_cases']),
            'non_compliant_sanctions' => array_replace($base, ['source' => 'non_compliant_sanctions']),
            default => null,
        };
    }

    public static function validate(array $rule): array
    {
        $validated = Validator::make(['rule' => $rule], [
            'rule' => ['required', 'array:source,case_type,months,threshold'],
            'rule.source' => ['required', Rule::in(array_keys(self::SOURCES))],
            'rule.case_type' => ['required', Rule::in(($rule['source'] ?? null) === 'non_compliant_sanctions' ? ['any'] : ['any', 'minor', 'major'])],
            'rule.months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'rule.threshold' => ['required', 'integer', 'min:1', 'max:1000'],
        ])->validate()['rule'];

        return [
            'source' => $validated['source'],
            'case_type' => $validated['case_type'],
            'months' => empty($validated['months']) ? null : (int) $validated['months'],
            'threshold' => (int) $validated['threshold'],
        ];
    }

    public function count(int $studentId, array $rule): int
    {
        $rule = self::validate($rule);
        if ($rule['source'] === 'non_compliant_sanctions') {
            $query = Sanction::where('student_id', $studentId)->where(function ($query): void {
                $query->where('status', 'non_compliant')->orWhere(function ($query): void {
                    $query->where('sanction_type', 'evaluation_monitoring')
                        ->where('assessment_outcome', 'needs_further_intervention');
                });
            });
            $date = 'created_at';
        } else {
            $query = DisciplinaryCase::where('student_id', $studentId);
            $date = 'created_at';
            if ($rule['source'] === 'confirmed_cases') {
                $query->where('status', 'resolved')
                    ->whereHas('caseOffenses', fn ($q) => $q->where('status', 'confirmed'));
                $date = 'resolved_at';
            } elseif ($rule['source'] === 'open_cases') {
                $query->whereIn('status', ['open', 'pending_nte', 'awaiting_explanation', 'under_review', 'pending_upcc', 'pending_resolution']);
            } else {
                $query->where('escalation_flagged', true)->whereNotIn('status', ['dismissed', 'voided']);
            }
            if ($rule['case_type'] !== 'any') {
                $query->where('case_type', $rule['case_type']);
            }
        }
        if (! empty($rule['months'])) {
            $query->whereBetween($date, [now()->subMonths((int) $rule['months']), now()]);
        }

        return $query->count();
    }
}
