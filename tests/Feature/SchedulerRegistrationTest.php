<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SchedulerRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_automation_commands_are_registered_in_the_scheduler(): void
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('reports:archive-monthly', $output);
        $this->assertStringContainsString('cases:finalize-expired-appeals', $output);
        $this->assertStringContainsString('risk:compute', $output);
        $this->assertStringContainsString('us:flag-overdue', $output);
        $this->assertStringContainsString('cases:process-nte-deadlines', $output);
        $this->assertStringContainsString('prism:automation-check', $output);
        $this->assertStringContainsString('prism:privacy-check', $output);
    }

    public function test_monthly_archive_runs_daily_so_a_missed_run_can_catch_up(): void
    {
        Artisan::call('schedule:list');

        $line = collect(preg_split('/\R/', Artisan::output()))
            ->first(fn (string $line): bool => str_contains($line, 'reports:archive-monthly'));

        $this->assertNotNull($line, 'reports:archive-monthly is not registered in the scheduler.');

        // Must not be pinned to a single minute of the month: the scheduler has no catch-up,
        // so a server that is down during that one window loses the archive permanently.
        $this->assertStringContainsString('* * *', $line, 'reports:archive-monthly must run daily, not only on a fixed day of the month.');
    }
}
