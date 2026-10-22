<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Services\IncidentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferenceNumberGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_number_sequence_continues_from_existing_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00'));

        IncidentReport::create([
            'report_number' => 'IR-2026-0007',
            'source_type' => 'direct_encoding',
            'date_of_incident' => today(),
            'description' => 'Existing incident report used to seed the yearly sequence.',
            'status' => 'under_review',
        ]);

        IncidentReport::create([
            'report_number' => 'IR-2026-MANUAL',
            'source_type' => 'direct_encoding',
            'date_of_incident' => today(),
            'description' => 'Non-standard report numbers should not break sequence allocation.',
            'status' => 'under_review',
        ]);

        $service = new IncidentReportService;

        $this->assertSame('IR-2026-0008', $service->generateReportNumber());
        $this->assertSame('IR-2026-0009', $service->generateReportNumber());

        $this->assertDatabaseHas('number_sequences', [
            'sequence_key' => 'incident_reports:2026',
            'prefix' => 'IR',
            'year' => 2026,
            'current_value' => 9,
        ]);

        Carbon::setTestNow();
    }

    public function test_case_number_sequence_uses_locked_sequence_row_and_existing_case_numbers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 09:00:00'));

        DisciplinaryCase::create([
            'case_number' => 'DC-2026-0042',
            'case_type' => 'minor',
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);

        DB::table('number_sequences')->insert([
            'sequence_key' => 'disciplinary_cases:2026',
            'prefix' => 'DC',
            'year' => 2026,
            'current_value' => 7,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new IncidentReportService;

        $this->assertSame('DC-2026-0043', $service->generateCaseNumber());
        $this->assertSame('DC-2026-0044', $service->generateCaseNumber());

        $this->assertDatabaseHas('number_sequences', [
            'sequence_key' => 'disciplinary_cases:2026',
            'prefix' => 'DC',
            'year' => 2026,
            'current_value' => 44,
        ]);

        Carbon::setTestNow();
    }
}
