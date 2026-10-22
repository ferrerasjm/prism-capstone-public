<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Models\StudentProfile;
use Tests\TestCase;

/**
 * Module 1 - User Management and Authentication
 * Component: Student Onboarding Profile Rules (App\Models\StudentProfile)
 * Test Cycle No. 1 - 5 test cases.
 */
class StudentOnboardingProfileRulesTest extends TestCase
{
    /** Scenario 1 - Normal: a profile with every required field is complete. */
    public function test_a_profile_with_all_required_fields_is_complete(): void
    {
        $profile = $this->profile();

        $this->assertTrue($profile->isOnboardingComplete());
    }

    /** Scenario 2 - Failure: any single missing required field blocks completion. */
    public function test_any_single_missing_required_field_blocks_completion(): void
    {
        foreach (['program', 'year_level', 'contact_number', 'guardian_name'] as $field) {
            $profile = $this->profile();
            $profile->{$field} = null;

            $this->assertFalse(
                $profile->isOnboardingComplete(),
                "Onboarding must stay incomplete while [{$field}] is missing."
            );
        }
    }

    /** Scenario 3 - Boundary: the data-accuracy acknowledgement is itself required. */
    public function test_onboarding_is_incomplete_without_the_data_accuracy_acknowledgement(): void
    {
        $profile = $this->profile();
        $profile->data_accuracy_acknowledged_at = null;

        $this->assertFalse($profile->isOnboardingComplete());
    }

    /** Scenario 4 - Normal: program aliases resolve to the canonical catalog name. */
    public function test_program_aliases_resolve_to_the_canonical_catalog_name(): void
    {
        $this->assertSame(
            'BS Information Technology',
            StudentProfile::canonicalProgramName('BSIT')
        );
        $this->assertSame(
            'BS Information Technology',
            StudentProfile::canonicalProgramName('  BS Information Technology  ')
        );
        $this->assertSame('BS Psychology', StudentProfile::canonicalProgramName('BS Psychology'));
        $this->assertNull(StudentProfile::canonicalProgramName(null));
    }

    /** Scenario 5 - Invalid / boundary: abbreviation falls back safely for blank or unknown programs. */
    public function test_program_abbreviation_handles_known_alias_blank_and_unknown_values(): void
    {
        $this->assertSame('BSIT', StudentProfile::abbreviatedProgramName('BS Information Technology'));
        $this->assertSame('BSIT', StudentProfile::abbreviatedProgramName('BSIT'));
        $this->assertSame('-', StudentProfile::abbreviatedProgramName('   '));
        $this->assertSame('Not set', StudentProfile::abbreviatedProgramName(null, 'Not set'));
        $this->assertSame('BS Nursing', StudentProfile::abbreviatedProgramName('BS Nursing'));
    }

    private function profile(): StudentProfile
    {
        return new StudentProfile([
            'user_id' => 1,
            'student_number' => '2026-00001',
            'program' => 'BS Information Technology',
            'year_level' => 2,
            'contact_number' => '09171234567',
            'guardian_name' => 'Ana Santos',
            'data_accuracy_acknowledged_at' => now(),
        ]);
    }
}
