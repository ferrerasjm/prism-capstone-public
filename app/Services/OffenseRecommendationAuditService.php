<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\OffenseRecommendationAudit;

/**
 * Records every offense-classification suggestion — model or keyword —
 * shown to an officer, and the decision they eventually made. This is
 * the audit trail that makes AdvisoryOffenseClassificationService's
 * suggestions accountable: nothing is classified without a traceable
 * human decision on record.
 */
class OffenseRecommendationAuditService
{
    public function record(
        DisciplinaryCase $case,
        string $description,
        array $excludedOffenseIds,
        array $classification,
        ?int $requestedBy,
    ): OffenseRecommendationAudit {
        $contextHash = $this->contextHash($description, $excludedOffenseIds);
        $model = $classification['model'] ?? [];
        $guardrail = $classification['guardrail'] ?? [];

        return OffenseRecommendationAudit::query()->updateOrCreate(
            [
                'case_id' => $case->id,
                'context_hash' => $contextHash,
            ],
            [
                'description_hash' => hash('sha256', $description),
                'source' => $classification['source'],
                'status' => $classification['status'],
                'model_name' => $model['name'] ?? null,
                'model_version' => $model['version'] ?? null,
                'recommendations' => $classification['predictions'],
                'no_match' => $classification['no_match'],
                'guardrail_reason' => $guardrail['reason'] ?? null,
                'requested_by' => $requestedBy,
            ],
        );
    }

    public function recordDecision(
        OffenseRecommendationAudit $audit,
        int $selectedOffenseId,
        int $decidedBy,
    ): void {
        $recommendedCodes = collect($audit->recommendations ?? [])
            ->pluck('label')
            ->filter(fn (mixed $code): bool => is_string($code) && $code !== 'NO_MATCH');
        $selectedCode = OffenseCatalog::query()
            ->whereKey($selectedOffenseId)
            ->value('code');

        $audit->update([
            'selected_offense_id' => $selectedOffenseId,
            'decision_was_recommended' => $selectedCode !== null
                && $recommendedCodes->contains($selectedCode),
            'decided_by' => $decidedBy,
            'decided_at' => now(),
        ]);
    }

    private function contextHash(string $description, array $excludedOffenseIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $excludedOffenseIds)));
        sort($ids);

        return hash('sha256', $description.'|'.implode(',', $ids));
    }
}
