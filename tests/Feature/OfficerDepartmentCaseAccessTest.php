<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerDepartmentCaseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_discipline_officer_requires_an_academic_department(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'first_name' => 'Department',
                'last_name' => 'Officer',
                'institutional_id' => 'NU-DO-1001',
                'email' => 'department.officer@nu-clark.edu.ph',
                'role' => 'discipline_officer',
            ])
            ->assertSessionHasErrors('academic_unit_ids', null, 'createUser');
    }

    public function test_officer_only_sees_cases_from_the_assigned_department_while_coordinator_sees_all(): void
    {
        $units = AcademicUnit::query()->with('programs')->orderBy('id')->take(2)->get();
        $this->assertCount(2, $units);

        $officer = $this->user('discipline_officer', 'officer@nu-clark.edu.ph', $units[0]->id);
        $coordinator = $this->user('discipline_coordinator', 'coordinator@nu-clark.edu.ph');
        $ownStudent = $this->student('own.student@example.test', $units[0]->programs->firstOrFail());
        $otherStudent = $this->student('other.student@example.test', $units[1]->programs->firstOrFail());
        $ownCase = $this->case('CASE-DEPT-OWN', $ownStudent, $officer);
        $otherCase = $this->case('CASE-DEPT-OTHER', $otherStudent, $officer);

        $this->actingAs($officer)
            ->get(route('officer.cases.index'))
            ->assertOk()
            ->assertSee('Department')
            ->assertSee($units[0]->name)
            ->assertSee($ownCase->case_number)
            ->assertDontSee($otherCase->case_number);

        $this->get(route('officer.cases.search-suggestions', ['q' => 'CASE-DEPT']))
            ->assertOk()
            ->assertJsonFragment(['case_number' => $ownCase->case_number])
            ->assertJsonMissing(['case_number' => $otherCase->case_number]);

        $this->get(route('officer.cases.show', $ownCase))
            ->assertOk()
            ->assertSee('Next step')
            ->assertSee('Complete this action to move the case forward.')
            ->assertSee('case-supporting-disclosure', false)
            ->assertSee('case-sidebar-disclosure', false)
            ->assertSee('More Actions')
            ->assertDontSee('Case Process');

        $this->get(route('officer.cases.show', $otherCase))->assertForbidden();

        $this->actingAs($coordinator)
            ->get(route('coordinator.case-records.index'))
            ->assertOk()
            ->assertSee($ownCase->case_number)
            ->assertSee($otherCase->case_number);
    }

    private function student(string $email, AcademicProgram $program): User
    {
        $student = $this->user('student', $email);
        $student->studentProfile()->create([
            'student_number' => fake()->unique()->numerify('2026-#####'),
            'program' => $program->name,
        ]);

        return $student;
    }

    private function case(string $number, User $student, User $officer): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'minor',
            'case_type_locked' => true,
            'status' => 'open',
            'prior_offense_count' => 0,
        ]);
    }

    private function user(string $role, string $email, ?int $academicUnitId = null): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
            'academic_unit_id' => $academicUnitId,
        ]);
    }
}
