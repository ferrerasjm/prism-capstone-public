<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pure constants holder for the major-offense disciplinary timeline —
 * the number of days each stage (complaint submission, NTE issuance and
 * response, and so on) is allowed to take under the handbook process.
 * No behaviour lives here; other services reference these constants when
 * computing due dates and overdue flags.
 */
final class MajorOffenseWorkflow
{
    public const COMPLAINT_SUBMISSION_DAYS = 1;

    public const VIOLATION_SUMMARY_DAYS = 1;

    public const REPORT_EVALUATION_DAYS = 3;

    public const NTE_ISSUANCE_DAYS = 1;

    public const NTE_RESPONSE_DAYS = 5;

    public const EXPLANATION_EVALUATION_DAYS = 2;

    public const UPCC_CONVENING_DAYS = 2;

    public const CONFERENCE_NOTICE_MIN_DAYS = 5;

    public const CONFERENCE_NOTICE_MAX_DAYS = 7;

    public const PROCEEDINGS_MAX_DAYS = 10;

    public const DECISION_DAYS = 3;

    public const FINAL_REPORT_DAYS = 3;

    public const COUNCIL_ACTION_DAYS = 3;

    public const APPEAL_DAYS = 5;

    public const RECORDS_FILING_HOURS = 4;

    public static function nteResponseDeadline(CarbonInterface $receivedAt): Carbon
    {
        return self::calendarDateDeadline($receivedAt, self::NTE_RESPONSE_DAYS);
    }

    public static function explanationEvaluationDeadline(CarbonInterface $receivedAt): Carbon
    {
        return self::calendarDateDeadline($receivedAt, self::EXPLANATION_EVALUATION_DAYS);
    }

    public static function upccConveningDeadline(CarbonInterface $receivedAt): Carbon
    {
        return self::calendarDateDeadline($receivedAt, self::UPCC_CONVENING_DAYS);
    }

    /**
     * @return array{earliest: Carbon, latest: Carbon}
     */
    public static function conferenceWindow(CarbonInterface $explanationReceivedAt): array
    {
        return [
            'earliest' => self::calendarDateDeadline(
                $explanationReceivedAt,
                self::CONFERENCE_NOTICE_MIN_DAYS
            ),
            'latest' => self::calendarDateDeadline(
                $explanationReceivedAt,
                self::CONFERENCE_NOTICE_MAX_DAYS
            ),
        ];
    }

    public static function proceedingsDeadline(CarbonInterface $conferenceStartedAt): Carbon
    {
        return self::timestampDeadline($conferenceStartedAt, self::PROCEEDINGS_MAX_DAYS);
    }

    public static function decisionDeadline(CarbonInterface $proceedingsEndedAt): Carbon
    {
        return self::timestampDeadline($proceedingsEndedAt, self::DECISION_DAYS);
    }

    public static function finalReportDeadline(CarbonInterface $proceedingsEndedAt): Carbon
    {
        return self::timestampDeadline($proceedingsEndedAt, self::FINAL_REPORT_DAYS);
    }

    public static function councilActionDeadline(CarbonInterface $reportReceivedAt): Carbon
    {
        return self::timestampDeadline($reportReceivedAt, self::COUNCIL_ACTION_DAYS);
    }

    /**
     * Student Handbook VI.G.4 counts the appeal window in WORKING days, unlike
     * every other deadline here. PR-002 step 13 says calendar days; the handbook
     * governs, and it is also the reading that favours the student.
     */
    public static function appealDeadline(CarbonInterface $decisionReceivedAt): Carbon
    {
        return self::workingDayDeadline($decisionReceivedAt, self::APPEAL_DAYS);
    }

    public static function recordsFilingDeadline(CarbonInterface $decisionFinalizedAt): Carbon
    {
        return self::copy($decisionFinalizedAt)->addHours(self::RECORDS_FILING_HOURS);
    }

    public static function wholeDaysRemaining(CarbonInterface $deadline, ?CarbonInterface $from = null): int
    {
        $start = $from ? self::copy($from)->startOfDay() : today();
        $end = self::copy($deadline)->startOfDay();
        $days = (int) round($start->diffInDays($end, true));

        return $end->lt($start) ? -$days : $days;
    }

    private static function workingDayDeadline(CarbonInterface $from, int $days): Carbon
    {
        $deadline = self::copy($from)->startOfDay();

        for ($counted = 0; $counted < $days; $counted++) {
            do {
                $deadline->addDay();
            } while ($deadline->isWeekend());
        }

        return $deadline;
    }

    private static function calendarDateDeadline(CarbonInterface $from, int $days): Carbon
    {
        return self::copy($from)->startOfDay()->addDays($days);
    }

    private static function timestampDeadline(CarbonInterface $from, int $days): Carbon
    {
        return self::copy($from)->addDays($days);
    }

    private static function copy(CarbonInterface $date): Carbon
    {
        return Carbon::parse($date->toDateTimeString(), $date->getTimezone());
    }
}
