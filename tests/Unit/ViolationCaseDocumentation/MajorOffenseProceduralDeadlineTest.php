<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Services\MajorOffenseWorkflow;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Major Offense Procedural Deadlines (App\Services\MajorOffenseWorkflow)
 * Test Cycle No. 1 - 5 test cases.
 *
 * Every helper is a pure static date calculation, so no application
 * container or database is required.
 */
class MajorOffenseProceduralDeadlineTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: the NTE response deadline is five calendar days from the start of the day. */
    public function test_the_nte_response_deadline_is_five_calendar_days_from_the_start_of_day(): void
    {
        $deadline = MajorOffenseWorkflow::nteResponseDeadline(Carbon::parse('2026-08-03 16:45'));

        $this->assertSame('2026-08-08 00:00:00', $deadline->toDateTimeString());
        $this->assertSame(5, MajorOffenseWorkflow::NTE_RESPONSE_DAYS);
    }

    /** Scenario 2 - Normal: evaluation and UPCC convening both fall two calendar days out. */
    public function test_evaluation_and_upcc_convening_deadlines_are_two_calendar_days_out(): void
    {
        $receivedAt = Carbon::parse('2026-08-03 09:00');

        $this->assertSame(
            '2026-08-05',
            MajorOffenseWorkflow::explanationEvaluationDeadline($receivedAt)->toDateString()
        );
        $this->assertSame(
            '2026-08-05',
            MajorOffenseWorkflow::upccConveningDeadline($receivedAt)->toDateString()
        );
    }

    /** Scenario 3 - Boundary: the conference notice window spans day 5 through day 7. */
    public function test_the_conference_notice_window_spans_the_fifth_through_seventh_day(): void
    {
        $window = MajorOffenseWorkflow::conferenceWindow(Carbon::parse('2026-08-03 23:59'));

        $this->assertSame('2026-08-08', $window['earliest']->toDateString());
        $this->assertSame('2026-08-10', $window['latest']->toDateString());
        $this->assertTrue($window['earliest']->lessThan($window['latest']));
    }

    /** Scenario 4 - Boundary: records filing keeps the time of day and adds four hours. */
    public function test_records_filing_adds_four_hours_and_preserves_the_time_of_day(): void
    {
        $finalizedAt = Carbon::parse('2026-08-03 21:30');

        $this->assertSame(
            '2026-08-04 01:30:00',
            MajorOffenseWorkflow::recordsFilingDeadline($finalizedAt)->toDateTimeString()
        );
        $this->assertSame(
            '2026-08-13 09:00:00',
            MajorOffenseWorkflow::proceedingsDeadline(Carbon::parse('2026-08-03 09:00'))->toDateTimeString()
        );
    }

    /** Scenario 5 - Failure / boundary: days remaining is zero on the deadline and negative after it. */
    public function test_days_remaining_is_zero_on_the_deadline_and_negative_once_it_has_passed(): void
    {
        $deadline = Carbon::parse('2026-08-08 00:00');

        $this->assertSame(3, MajorOffenseWorkflow::wholeDaysRemaining($deadline, Carbon::parse('2026-08-05 23:59')));
        $this->assertSame(0, MajorOffenseWorkflow::wholeDaysRemaining($deadline, Carbon::parse('2026-08-08 07:15')));
        $this->assertSame(-2, MajorOffenseWorkflow::wholeDaysRemaining($deadline, Carbon::parse('2026-08-10 12:00')));
    }
}
