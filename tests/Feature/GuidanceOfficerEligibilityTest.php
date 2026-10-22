<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GuidanceReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Department rule behind the Guidance Officer pre-assignment offered on the resolution
 * forms and the Sanctions screen. Both routes share
 * GuidanceReferralService::eligibleOfficersForStudent(), so a referral cannot be sent to
 * an officer who does not cover the student's department.
 */
class GuidanceOfficerEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_officers_covering_the_students_department_are_eligible(): void
    {
        [$covering, $other, $student] = $this->departmentScenario();

        $eligible = (new GuidanceReferralService)->eligibleOfficersForStudent($student);

        $this->assertTrue(
            $eligible->contains('id', $covering->id),
            'The officer assigned to the student\'s department must be eligible.'
        );
        $this->assertFalse(
            $eligible->contains('id', $other->id),
            'An officer from an unrelated department must not be eligible.'
        );
    }

    public function test_all_active_officers_are_offered_when_no_officer_covers_the_department(): void
    {
        [, $other, $student] = $this->departmentScenario();

        // Drop the covering officer's department link so nothing matches the student.
        User::where('role', 'guidance_officer')
            ->get()
            ->each(fn (User $officer) => $officer->guidanceAcademicUnits()->detach());

        $eligible = (new GuidanceReferralService)->eligibleOfficersForStudent($student);

        $this->assertTrue(
            $eligible->contains('id', $other->id),
            'The fallback must offer every active officer so a referral is never unassignable.'
        );
    }

    public function test_inactive_officers_are_never_eligible(): void
    {
        [$covering, , $student] = $this->departmentScenario();
        $covering->update(['status' => 'inactive']);

        $eligible = (new GuidanceReferralService)->eligibleOfficersForStudent($student);

        $this->assertFalse($eligible->contains('id', $covering->id));
    }

    /**
     * A student in one department, an officer covering it, and an officer covering another.
     *
     * @return array{0: User, 1: User, 2: User}
     */
    private function departmentScenario(): array
    {
        $units = AcademicUnit::query()->orderBy('id')->take(2)->get();
        $this->assertCount(2, $units, 'Expected at least two seeded academic units.');
        [$studentUnit, $otherUnit] = [$units[0], $units[1]];

        $program = AcademicProgram::create([
            'academic_unit_id' => $studentUnit->id,
            'name' => 'BS Eligibility Testing',
        ]);

        $student = $this->makeUser('student', 'eligibility.student@nu-clark.edu.ph');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-77001',
            'program' => $program->name,
            'year_level' => '3',
        ]);

        $covering = $this->makeUser('guidance_officer', 'covering.guidance@nu-clark.edu.ph');
        $covering->guidanceAcademicUnits()->attach($studentUnit->id);

        $other = $this->makeUser('guidance_officer', 'other.guidance@nu-clark.edu.ph');
        $other->guidanceAcademicUnits()->attach($otherUnit->id);

        return [$covering, $other, $student];
    }

    private function makeUser(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
