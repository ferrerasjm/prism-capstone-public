<?php

declare(strict_types=1);

namespace Tests\Unit\ViolationCaseDocumentation;

use App\Services\IncidentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module 2 - Violation and Case Documentation
 * Component: Reference Number Allocation (App\Services\IncidentReportService)
 * Test Cycle No. 1 - 5 test cases.
 */
class CaseReferenceNumberAllocationTest extends TestCase
{
    use RefreshDatabase;

    /** Scenario 1 - Normal: the first case number of the year is DC-<year>-0001. */
    public function test_the_first_case_number_of_the_year_starts_at_one(): void
    {
        $number = (new IncidentReportService)->generateCaseNumber();

        $this->assertSame(sprintf('DC-%d-0001', now()->year), $number);
    }

    /** Scenario 2 - Normal: consecutive allocations never repeat a number. */
    public function test_consecutive_allocations_are_unique_and_sequential(): void
    {
        $service = new IncidentReportService;

        $numbers = [
            $service->generateCaseNumber(),
            $service->generateCaseNumber(),
            $service->generateCaseNumber(),
        ];

        $this->assertSame([
            sprintf('DC-%d-0001', now()->year),
            sprintf('DC-%d-0002', now()->year),
            sprintf('DC-%d-0003', now()->year),
        ], $numbers);
        $this->assertCount(3, array_unique($numbers));
    }

    /** Scenario 3 - Normal: incident reports use their own IR sequence. */
    public function test_incident_reports_use_a_separate_ir_sequence(): void
    {
        $service = new IncidentReportService;

        $service->generateCaseNumber();
        $reportNumber = $service->generateReportNumber();

        $this->assertSame(sprintf('IR-%d-0001', now()->year), $reportNumber);
        $this->assertSame(
            2,
            DB::table('number_sequences')->count(),
            'Cases and incident reports must not share one counter.'
        );
    }

    /** Scenario 4 - Boundary: allocation seeds from existing records when no counter row exists. */
    public function test_allocation_seeds_from_the_highest_existing_number_for_the_year(): void
    {
        $year = now()->year;
        DB::table('number_sequences')->delete();
        $this->seedCaseRow(sprintf('DC-%d-0007', $year));

        $number = (new IncidentReportService)->generateCaseNumber();

        $this->assertSame(sprintf('DC-%d-0008', $year), $number);
    }

    /** Scenario 5 - Boundary: sequence values stay zero-padded to four digits. */
    public function test_sequence_values_remain_zero_padded_to_four_digits(): void
    {
        $year = now()->year;
        DB::table('number_sequences')->delete();
        $this->seedCaseRow(sprintf('DC-%d-0099', $year));

        $service = new IncidentReportService;

        $this->assertSame(sprintf('DC-%d-0100', $year), $service->generateCaseNumber());
        $this->assertMatchesRegularExpression(
            '/^DC-\d{4}-\d{4}$/',
            $service->generateCaseNumber()
        );
    }

    private function seedCaseRow(string $caseNumber): void
    {
        DB::table('cases')->insert([
            'case_number' => $caseNumber,
            'student_id' => null,
            'status' => 'open',
            'prior_offense_count' => 0,
            'escalation_flagged' => false,
            'forwarded_to_coordinator' => false,
            'void_requires_coordinator_approval' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
