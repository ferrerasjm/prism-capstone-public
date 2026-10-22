<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\ScheduledReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MonthlyViolationArchiveCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_archives_the_previous_month_violation_summary(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-08-01 00:00:00');
        $this->case('minor', 'resolved', '2026-07-12 09:00:00');
        $this->case('major', 'open', '2026-07-20 14:00:00');
        $this->case('minor', 'resolved', '2026-06-30 14:00:00');

        $this->artisan('reports:archive-monthly')
            ->expectsOutputToContain('Archived monthly violation summary for July 2026')
            ->assertSuccessful();

        $report = ScheduledReport::firstOrFail();

        $this->assertSame('monthly_violation_summary', $report->report_type);
        $this->assertSame('2026-07-01', $report->period_start->toDateString());
        $this->assertSame('2026-07-31', $report->period_end->toDateString());
        $this->assertNull($report->generated_by);
        $this->assertStringEndsWith('.pdf', $report->file_path);
        Storage::disk('local')->assertExists($report->file_path);
    }

    public function test_it_skips_duplicate_monthly_archives_unless_forced(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-08-01 00:00:00');

        $this->artisan('reports:archive-monthly')->assertSuccessful();
        $this->artisan('reports:archive-monthly')
            ->expectsOutput('Monthly violation archive for July 2026 already exists. Use --force to generate another copy.')
            ->assertSuccessful();

        $this->assertSame(1, ScheduledReport::count());

        $this->artisan('reports:archive-monthly --force')->assertSuccessful();

        $this->assertSame(2, ScheduledReport::count());
    }

    public function test_it_can_archive_a_selected_month_as_excel(): void
    {
        Storage::fake('local');
        Carbon::setTestNow('2026-08-01 00:00:00');
        $this->case('minor', 'resolved', '2026-06-12 09:00:00');
        $this->case('major', 'open', '2026-07-20 14:00:00');

        $this->artisan('reports:archive-monthly --month=6 --year=2026 --format=xlsx')
            ->expectsOutputToContain('Archived monthly violation summary for June 2026')
            ->assertSuccessful();

        $report = ScheduledReport::firstOrFail();
        $workbook = IOFactory::load(Storage::disk('local')->path($report->file_path));
        $activity = $workbook->getSheetByName('Monthly Case Activity');

        $this->assertSame('2026-06-01', $report->period_start->toDateString());
        $this->assertSame('2026-06-30', $report->period_end->toDateString());
        $this->assertStringEndsWith('.xlsx', $report->file_path);
        $this->assertSame('June', $activity->getCell('A5')->getValue());
        $this->assertSame(1, $activity->getCell('B5')->getValue());
        $this->assertSame(0, $activity->getCell('C5')->getValue());
        $this->assertSame(1, $activity->getCell('D5')->getValue());
        $this->assertSame(0, $activity->getCell('E5')->getValue());
        $this->assertSame(5, $activity->getHighestDataRow());
        $workbook->disconnectWorksheets();
    }

    public function test_it_requires_month_and_year_to_be_used_together(): void
    {
        $this->artisan('reports:archive-monthly --month=7')
            ->expectsOutput('Use --month and --year together, or omit both to archive the previous month.')
            ->assertExitCode(2);
    }

    private function case(string $type, string $status, string $createdAt): DisciplinaryCase
    {
        $officer = User::firstOrCreate(
            ['email' => 'archive-officer@example.test'],
            [
                'last_name' => 'Officer',
                'first_name' => 'Archive',
                'password' => Hash::make('password'),
                'role' => 'discipline_officer',
                'status' => 'active',
            ]
        );

        $student = User::firstOrCreate(
            ['email' => 'archive-student@example.test'],
            [
                'last_name' => 'Student',
                'first_name' => 'Archive',
                'password' => Hash::make('password'),
                'role' => 'student',
                'status' => 'active',
            ]
        );

        $offense = OffenseCatalog::firstOrCreate(
            ['code' => strtoupper($type).'-ARCHIVE'],
            [
                'offense_type' => $type,
                'title' => ucfirst($type).' archive offense',
                'description' => 'Archive command test offense.',
                'keywords' => ['archive'],
                'created_at' => $createdAt,
            ]
        );

        $case = DisciplinaryCase::create([
            'case_number' => 'ARCHIVE-'.strtoupper($type).'-'.str_replace(['-', ':', ' '], '', $createdAt),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => $type,
            'status' => $status,
            'confirmed_offense_id' => $offense->id,
            'prior_offense_count' => 0,
            'resolved_at' => $status === 'resolved' ? $createdAt : null,
        ]);
        $case->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        $case->caseOffenses()->create([
            'offense_catalog_id' => $offense->id,
            'status' => 'confirmed',
            'confirmed_by' => $officer->id,
            'confirmed_at' => $createdAt,
        ]);

        return $case;
    }
}
