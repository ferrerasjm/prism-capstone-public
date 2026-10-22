<?php

declare(strict_types=1);

namespace Tests\Unit\UserManagementAuthentication;

use App\Models\AcademicUnit;
use App\Models\StudentProfile;
use App\Models\User;
use App\Rules\StudentInOfficerDepartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Module 1 - User Management and Authentication
 * Component: Officer Department Scoping
 *            (App\Models\StudentProfile::scopeForOfficerDepartment,
 *             App\Rules\StudentInOfficerDepartment)
 * Test Cycle No. 1 - 5 test cases.
 *
 * This is the access-control boundary that keeps a Discipline Officer's
 * caseload inside their assigned academic unit. The real academic catalog
 * installed by the migrations is used rather than invented units, so the
 * program-to-unit mapping under test is the production one.
 */
class OfficerDepartmentScopingTest extends TestCase
{
    use RefreshDatabase;

    private const ENGINEERING_UNIT = 'School of Engineering, Architecture, and Technology';

    private const SCIENCES_UNIT = 'School of Education, Arts, and Sciences';

    private const GRADE_11_UNIT = 'Grade 11 (Senior High School)';

    /** Scenario 1 - Normal: a program-scoped officer sees only their own unit's students. */
    public function test_a_program_scoped_officer_sees_only_their_own_units_students(): void
    {
        $officer = $this->officerFor(self::ENGINEERING_UNIT);
        $this->studentWithProfile('itstudent', 'BS Information Technology', '2');
        $this->studentWithProfile('psychstudent', 'BS Psychology', '2');

        $programs = StudentProfile::query()->forOfficerDepartment($officer)->pluck('program')->all();

        $this->assertSame(['BS Information Technology'], $programs);
    }

    /** Scenario 2 - Boundary: a program stored under its alias stays inside the same unit. */
    public function test_a_program_alias_is_scoped_to_the_same_academic_unit(): void
    {
        $officer = $this->officerFor(self::ENGINEERING_UNIT);
        $this->studentWithProfile('aliased', 'BSIT', '3');

        $this->assertSame(
            1,
            StudentProfile::query()->forOfficerDepartment($officer)->count(),
            'A profile stored under the BSIT alias must still fall inside its unit.'
        );
    }

    /** Scenario 3 - Normal: a year-level-scoped unit selects by year level, not by program. */
    public function test_a_year_level_scoped_unit_selects_by_year_level(): void
    {
        $officer = $this->officerFor(self::GRADE_11_UNIT);

        $this->studentWithProfile('shs11', 'Computing and Technologies (STEM_CT)', 'Grade 11');
        $this->studentWithProfile('shs12', 'Computing and Technologies (STEM_CT)', 'Grade 12');

        $levels = StudentProfile::query()->forOfficerDepartment($officer)->pluck('year_level')->all();

        $this->assertSame(['Grade 11'], $levels);
    }

    /** Scenario 4 - Failure: an officer with no assigned unit sees no students at all. */
    public function test_an_officer_without_an_assigned_unit_sees_no_students(): void
    {
        $this->studentWithProfile('orphan', 'BS Civil Engineering', '1');

        $officer = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Unassigned',
            'email' => 'unassigned.officer@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $this->assertSame(0, StudentProfile::query()->forOfficerDepartment($officer)->count());
    }

    /** Scenario 5 - Failure: the validation rule refuses a student outside the officer's department. */
    public function test_the_validation_rule_refuses_a_student_outside_the_department(): void
    {
        $officer = $this->officerFor(self::ENGINEERING_UNIT);

        $inside = $this->studentWithProfile('inside', 'BS Information Technology', '2');
        $outside = $this->studentWithProfile('outside', 'BS Psychology', '2');

        $this->assertNull($this->ruleFailure($officer, $inside->id));
        $this->assertSame(
            'The selected student is not part of your assigned department.',
            $this->ruleFailure($officer, $outside->id)
        );
    }

    private function ruleFailure(User $officer, int $studentId): ?string
    {
        $message = null;

        (new StudentInOfficerDepartment($officer))->validate(
            'student_id',
            $studentId,
            function (string $failure) use (&$message): void {
                $message = $failure;
            }
        );

        return $message;
    }

    private function officerFor(string $unitName): User
    {
        $unit = AcademicUnit::where('name', $unitName)->firstOrFail();

        $officer = User::create([
            'last_name' => 'Lim',
            'first_name' => 'Scoped',
            'email' => 'scoped.officer.'.$unit->id.'@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
            'academic_unit_id' => $unit->id,
        ]);

        $officer->academicUnits()->attach($unit->id);

        return $officer->fresh();
    }

    private function studentWithProfile(string $handle, string $program, string $yearLevel): User
    {
        $student = User::create([
            'last_name' => 'Santos',
            'first_name' => ucfirst($handle),
            'email' => "{$handle}@students.nu-clark.edu.ph",
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-'.str_pad((string) $student->id, 5, '0', STR_PAD_LEFT),
            'program' => $program,
            'year_level' => $yearLevel,
        ]);

        return $student;
    }
}
