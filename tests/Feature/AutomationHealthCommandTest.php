<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ScheduledReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutomationHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_automation_health_check_passes_when_monthly_archive_is_not_due_yet(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');

        $this->artisan('prism:automation-check')
            ->expectsOutput('PRISM automation health check')
            ->expectsOutput('Queue connection: sync')
            ->expectsOutput('Monthly archive check: waiting until after the first day of the month.')
            ->expectsOutput('Automation health looks good.')
            ->assertExitCode(0);

        Carbon::setTestNow();
    }

    public function test_automation_health_check_can_fail_when_required_archives_are_missing(): void
    {
        Carbon::setTestNow('2026-07-02 08:00:00');

        $this->artisan('prism:automation-check --fail-on-warning')
            ->expectsOutput('PRISM automation health check')
            ->expectsOutput('Previous monthly violation archive (June 2026): missing')
            ->expectsOutput('Warnings found:')
            ->assertExitCode(1);

        Carbon::setTestNow();
    }

    public function test_automation_health_check_passes_after_monthly_archive_exists(): void
    {
        Carbon::setTestNow('2026-07-02 08:00:00');

        ScheduledReport::create([
            'report_type' => 'monthly_violation_summary',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'generated_by' => null,
            'file_path' => 'reports/monthly_violation_summary_202606.pdf',
            'generated_at' => now(),
            'created_at' => now(),
        ]);

        $this->artisan('prism:automation-check --fail-on-warning')
            ->expectsOutput('Previous monthly violation archive (June 2026): archived')
            ->expectsOutput('Automation health looks good.')
            ->assertExitCode(0);

        Carbon::setTestNow();
    }
}
