<?php

declare(strict_types=1);

namespace Tests\Unit\MobileUniversityServiceTracking;

use App\Models\UsAssignment;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Module 7 - Mobile University Service Tracking
 * Component: University Service Progress Tracking (App\Models\UsAssignment)
 * Test Cycle No. 1 - 5 test cases.
 *
 * These are the values the supervisor mobile app and the web console both
 * render for an assignment, so they are verified on in-memory records.
 */
class UsAssignmentProgressTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Scenario 1 - Normal: decimal hours are shown as readable hour and minute text. */
    public function test_decimal_hours_are_formatted_as_readable_hour_and_minute_text(): void
    {
        $this->assertSame('1 hour', UsAssignment::formatDuration(1));
        $this->assertSame('2 hours 30 minutes', UsAssignment::formatDuration(2.5));
        $this->assertSame('45 minutes', UsAssignment::formatDuration(0.75));
        $this->assertSame('1 hour 1 minute', UsAssignment::formatDuration(1.0167));
    }

    /** Scenario 2 - Boundary: zero and invalid durations never render as blank or negative. */
    public function test_zero_and_invalid_durations_never_render_as_blank_or_negative(): void
    {
        $this->assertSame('0 minutes', UsAssignment::formatDuration(0));
        $this->assertSame('0 minutes', UsAssignment::formatDuration(null));
        $this->assertSame('0 minutes', UsAssignment::formatDuration(-5));
    }

    /** Scenario 3 - Boundary: remaining hours never fall below zero when over-rendered. */
    public function test_remaining_hours_never_fall_below_zero_when_over_rendered(): void
    {
        $partial = $this->assignment(['total_hours_required' => 24, 'hours_rendered' => 6]);
        $over = $this->assignment(['total_hours_required' => 24, 'hours_rendered' => 30]);

        $this->assertSame(18.0, $partial->hoursRemaining());
        $this->assertSame('18 hours', $partial->remainingDuration());
        $this->assertSame(0.0, $over->hoursRemaining());
        $this->assertSame('0 minutes', $over->remainingDuration());
    }

    /** Scenario 4 - Boundary: completion percentage is capped and safe for a zero requirement. */
    public function test_completion_percentage_is_capped_and_safe_for_a_zero_requirement(): void
    {
        $this->assertSame(25, $this->assignment(['total_hours_required' => 24, 'hours_rendered' => 6])->completionPercentage());
        $this->assertSame(100, $this->assignment(['total_hours_required' => 24, 'hours_rendered' => 30])->completionPercentage());
        $this->assertSame(100, $this->assignment(['total_hours_required' => 0, 'hours_rendered' => 0])->completionPercentage());
        $this->assertSame(0, $this->assignment(['total_hours_required' => 24, 'hours_rendered' => 0])->completionPercentage());
    }

    /** Scenario 5 - Boundary / failure: an assignment turns overdue only after its target date. */
    public function test_an_assignment_turns_overdue_only_after_its_target_date(): void
    {
        $open = $this->assignment([
            'total_hours_required' => 24,
            'hours_rendered' => 6,
            'status' => 'in_progress',
            'target_completion_date' => '2026-08-14',
        ]);
        $completed = $this->assignment([
            'total_hours_required' => 24,
            'hours_rendered' => 24,
            'status' => 'completed',
            'target_completion_date' => '2026-08-14',
        ]);

        Carbon::setTestNow('2026-08-14 23:59:00');
        $this->assertFalse($open->isOverdue());

        Carbon::setTestNow('2026-08-15 00:01:00');
        $this->assertTrue($open->isOverdue());
        $this->assertFalse($completed->isOverdue());
    }

    private function assignment(array $attributes): UsAssignment
    {
        return new UsAssignment(array_merge([
            'case_resolution_id' => 1,
            'student_id' => 1,
            'office_name' => 'Learning Resource Center',
            'status' => 'in_progress',
            'start_date' => '2026-08-01',
            'target_completion_date' => '2026-08-31',
        ], $attributes));
    }
}
