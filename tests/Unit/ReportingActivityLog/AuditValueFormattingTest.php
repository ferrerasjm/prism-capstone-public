<?php

declare(strict_types=1);

namespace Tests\Unit\ReportingActivityLog;

use App\Models\AuditLog;
use Tests\TestCase;

/**
 * Module 6 - Reporting and Activity Log
 * Component: Audit Value and Field Formatting (App\Models\AuditLog)
 * Test Cycle No. 1 - 5 test cases.
 */
class AuditValueFormattingTest extends TestCase
{
    /** Scenario 1 - Boundary: empty values read as "Not set" instead of being blank. */
    public function test_empty_values_are_rendered_as_not_set(): void
    {
        $this->assertSame('Not set', AuditLog::formatValue(null));
        $this->assertSame('Not set', AuditLog::formatValue(''));
    }

    /** Scenario 2 - Normal: booleans read as Yes or No. */
    public function test_booleans_are_rendered_as_yes_or_no(): void
    {
        $this->assertSame('Yes', AuditLog::formatValue(true));
        $this->assertSame('No', AuditLog::formatValue(false));
    }

    /** Scenario 3 - Normal: stored codes become readable text while emails stay intact. */
    public function test_stored_codes_become_readable_text_while_emails_stay_intact(): void
    {
        $this->assertSame('Discipline Officer', AuditLog::formatValue('discipline_officer'));
        $this->assertSame('Active', AuditLog::formatValue('active'));
        $this->assertSame('officer@nu-clark.edu.ph', AuditLog::formatValue('officer@nu-clark.edu.ph'));
        $this->assertSame('DC-2026-0001', AuditLog::formatValue('DC-2026-0001'));
    }

    /** Scenario 4 - Boundary: arrays are flattened and deeper nesting is summarised. */
    public function test_arrays_are_flattened_and_deeper_nesting_is_summarised(): void
    {
        $this->assertSame(
            'Status: Active, Role: Discipline Officer',
            AuditLog::formatValue(['status' => 'active', 'role' => 'discipline_officer'])
        );
        $this->assertSame(
            'Offenses: Multiple values',
            AuditLog::formatValue(['offenses' => ['MINOR-01', 'MINOR-02']])
        );
        $this->assertSame('Active, Not set', AuditLog::formatValue(['active', null]));
    }

    /** Scenario 5 - Normal / invalid: identifier field labels adapt to the account role. */
    public function test_identifier_field_labels_adapt_to_the_account_role(): void
    {
        $this->assertSame('Student ID', AuditLog::formatField('institutional_id', 'student'));
        $this->assertSame('University ID', AuditLog::formatField('institutional_id', 'discipline_officer'));
        $this->assertSame('University ID', AuditLog::formatField('institutional_id'));
        $this->assertSame('Student ID', AuditLog::formatField('student_number'));
        $this->assertSame('Academic Unit ID', AuditLog::formatField('academic_unit_id'));
    }
}
