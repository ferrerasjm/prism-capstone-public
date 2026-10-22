<?php

declare(strict_types=1);

namespace Tests\Unit\ReportingActivityLog;

use App\Support\PieChart;
use PHPUnit\Framework\TestCase;

/**
 * Module 6 - Reporting and Activity Log
 * Component: Report Chart Segment Computation (App\Support\PieChart)
 * Test Cycle No. 1 - 5 test cases.
 *
 * PieChart is shared by the on-screen reports and the PDF export, so the
 * same wedge geometry must be produced for both. It is pure trigonometry
 * with no framework dependency.
 */
class ReportChartSegmentTest extends TestCase
{
    /** Scenario 1 - Boundary: a report period with no data produces no wedges. */
    public function test_a_period_with_no_data_produces_no_wedges(): void
    {
        $this->assertCount(0, PieChart::segments([]));
        $this->assertCount(0, PieChart::segments([
            ['label' => 'Minor', 'value' => 0, 'color' => '#1d4ed8'],
            ['label' => 'Major', 'value' => 0, 'color' => '#b91c1c'],
        ]));
    }

    /** Scenario 2 - Normal: zero-value categories are dropped from the chart. */
    public function test_zero_value_categories_are_dropped_from_the_chart(): void
    {
        $segments = PieChart::segments([
            ['label' => 'Minor', 'value' => 8, 'color' => '#1d4ed8'],
            ['label' => 'Major', 'value' => 0, 'color' => '#b91c1c'],
            ['label' => 'Escalated', 'value' => 2, 'color' => '#a16207'],
        ]);

        $this->assertCount(2, $segments);
        $this->assertSame(['Minor', 'Escalated'], $segments->pluck('label')->all());
    }

    /** Scenario 3 - Normal: percentages are derived from the total and rounded to one decimal. */
    public function test_percentages_are_derived_from_the_total_and_rounded_to_one_decimal(): void
    {
        $segments = PieChart::segments([
            ['label' => 'Minor', 'value' => 1, 'color' => '#1d4ed8'],
            ['label' => 'Major', 'value' => 1, 'color' => '#b91c1c'],
            ['label' => 'Escalated', 'value' => 1, 'color' => '#a16207'],
        ]);

        $this->assertSame([33.3, 33.3, 33.3], $segments->pluck('pct')->all());
        $this->assertSame(75.0, PieChart::segments([
            ['label' => 'Minor', 'value' => 3, 'color' => '#1d4ed8'],
            ['label' => 'Major', 'value' => 1, 'color' => '#b91c1c'],
        ])->first()['pct']);
    }

    /** Scenario 4 - Boundary: a single category fills the circle and needs no wedge path. */
    public function test_a_single_category_fills_the_circle_without_a_wedge_path(): void
    {
        $segments = PieChart::segments([
            ['label' => 'Minor', 'value' => 12, 'color' => '#1d4ed8'],
        ]);

        $this->assertCount(1, $segments);
        $this->assertSame(100.0, $segments->first()['pct']);
        $this->assertNull($segments->first()['path']);
    }

    /** Scenario 5 - Boundary: wedges larger than a half circle set the SVG large-arc flag. */
    public function test_wedges_larger_than_a_half_circle_set_the_large_arc_flag(): void
    {
        $segments = PieChart::segments([
            ['label' => 'Minor', 'value' => 9, 'color' => '#1d4ed8'],
            ['label' => 'Major', 'value' => 1, 'color' => '#b91c1c'],
        ]);

        $large = $segments->first();
        $small = $segments->last();

        $this->assertNotNull($large['path']);
        $this->assertNotNull($small['path']);
        $this->assertMatchesRegularExpression('/ 1,1 /', $large['path']);
        $this->assertMatchesRegularExpression('/ 0,1 /', $small['path']);
    }
}
