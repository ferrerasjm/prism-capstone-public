<?php

namespace App\Console\Commands;

use App\Services\RiskScoringService;
use Illuminate\Console\Command;

/**
 * Recomputes the rule-based risk score for every student with case
 * history, and optionally writes a term snapshot. Scheduled daily; the
 * scoring model itself lives in RiskScoringService.
 */
class ComputeRiskScores extends Command
{
    protected $signature = 'risk:compute {--snapshot= : Take a term snapshot with this term label}';

    protected $description = 'Compute risk scores for all students with cases';

    public function handle(RiskScoringService $service): int
    {
        $this->info('Computing risk scores...');
        $count = $service->computeForAllStudents();
        $this->info("Computed scores for {$count} student(s).");

        $term = $this->option('snapshot');
        if ($term) {
            $snapped = $service->snapshotTerm($term);
            $this->info("Snapshot saved for term '{$term}': {$snapped} record(s).");
        }

        return 0;
    }
}
