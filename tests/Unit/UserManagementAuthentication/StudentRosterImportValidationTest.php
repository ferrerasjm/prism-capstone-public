<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Services\StudentImportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Module 1 - User Management and Authentication
 * Component: Student Roster Import Row Validation (App\Services\StudentImportService)
 * Test Cycle No. 1 - 5 test cases.
 *
 * validateRow() is pure string logic with no database or container
 * dependencies, so the service is instantiated without its constructor
 * and the rule is exercised directly.
 */
class StudentRosterImportValidationTest extends TestCase
{
    /** Scenario 1 - Normal: a complete row using the official student domain is accepted. */
    public function test_a_complete_row_with_the_official_domain_returns_no_error(): void
    {
        $result = $this->validate([
            'student_number' => '2026-000001',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'middle_name' => 'Reyes',
            'email' => 'maria.santos@students.nu-clark.edu.ph',
        ]);

        $this->assertNull($result);
    }

    /** Scenario 2 - Invalid: a blank student number is reported with its row number. */
    public function test_a_missing_student_number_is_reported_with_the_row_number(): void
    {
        $result = $this->validate([
            'student_number' => '   ',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'email' => 'maria.santos@students.nu-clark.edu.ph',
        ], 7);

        $this->assertSame('Row 7: Student number is required.', $result);
    }

    /** Scenario 3 - Invalid: a malformed email address is rejected. */
    public function test_a_malformed_email_address_is_rejected(): void
    {
        $result = $this->validate([
            'student_number' => '2026-000002',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'email' => 'maria.santos(at)students.nu-clark.edu.ph',
        ], 3);

        $this->assertSame('Row 3: Official student email is invalid.', $result);
    }

    /** Scenario 4 - Invalid: a well-formed address outside the student domain is rejected. */
    public function test_an_email_outside_the_official_student_domain_is_rejected(): void
    {
        $result = $this->validate([
            'student_number' => '2026-000003',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
            'email' => 'maria.santos@gmail.com',
        ], 4);

        $this->assertSame(
            'Row 4: Email must use the @students.nu-clark.edu.ph domain.',
            $result
        );
    }

    /**
     * Scenario 5 - Boundary / failure: whitespace-only names and an entirely
     * absent email column are reported in the documented rule order.
     */
    public function test_blank_names_and_an_absent_email_column_are_reported_in_rule_order(): void
    {
        $missingLastName = $this->validate([
            'student_number' => '2026-000004',
            'last_name' => '  ',
            'first_name' => '  ',
            'email' => 'maria.santos@students.nu-clark.edu.ph',
        ], 5);

        $missingFirstName = $this->validate([
            'student_number' => '2026-000005',
            'last_name' => 'Santos',
            'first_name' => '',
            'email' => 'maria.santos@students.nu-clark.edu.ph',
        ], 6);

        $absentEmailColumn = $this->validate([
            'student_number' => '2026-000006',
            'last_name' => 'Santos',
            'first_name' => 'Maria',
        ], 8);

        $this->assertSame('Row 5: Last name is required.', $missingLastName);
        $this->assertSame('Row 6: First name is required.', $missingFirstName);
        $this->assertSame('Row 8: Official student email is required.', $absentEmailColumn);
    }

    private function validate(array $row, int $rowNumber = 2): ?string
    {
        $service = (new \ReflectionClass(StudentImportService::class))
            ->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(StudentImportService::class, 'validateRow');

        return $method->invoke($service, $row, $rowNumber);
    }
}
