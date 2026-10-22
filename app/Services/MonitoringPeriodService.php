<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\Sanction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Category 1 probation and Category 2 evaluation both watch a dated window over a
 * student's record, so the window query lives here once. What differs is not the
 * question but the authority over the answer:
 *
 *  - Probation is a conduct test the handbook decides. Its window covers "any major
 *    offense and/or infringement of the University Rules and Regulations"
 *    (Section VI.E, Category 1), so violation slips count alongside cases.
 *  - Evaluation is a formative assessment the officer renders. New cases are evidence
 *    the officer weighs, not a trip-wire, so its window covers cases only.
 */
class MonitoringPeriodService
{
    /**
     * The window a sanction watches: from issuance to its end date, or to the point it
     * was closed if that came first.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(Sanction $sanction): array
    {
        $start = $sanction->created_at;
        $end = $sanction->completed_at
            ?? $sanction->due_date?->endOfDay()
            ?? now();

        return [$start, $end];
    }

    /**
     * Disciplinary cases opened against the student during the window, excluding the
     * case that produced the sanction in the first place.
     */
    public function casesDuring(Sanction $sanction): Collection
    {
        [$start, $end] = $this->window($sanction);

        return DisciplinaryCase::query()
            ->with('confirmedOffense')
            ->where('student_id', $sanction->student_id)
            ->when(
                $sanction->resolution?->case_id,
                fn ($query, $originatingCaseId) => $query->whereKeyNot($originatingCaseId),
            )
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get();
    }

    /**
     * Violation slips issued to the student during the window. These are the minor-offense
     * "infringements" that Category 1 probation escalates on but that never become cases
     * on their own, so a case-only query would miss them entirely.
     */
    public function violationSlipsDuring(Sanction $sanction): Collection
    {
        [$start, $end] = $this->window($sanction);

        return IncidentReport::query()
            ->where('student_id', $sanction->student_id)
            ->where('source_type', 'violation_slip')
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get();
    }

    /**
     * Every infringement that counts against a probation period.
     */
    public function probationBreachCount(Sanction $sanction): int
    {
        return $this->casesDuring($sanction)->count()
            + $this->violationSlipsDuring($sanction)->count();
    }
}
