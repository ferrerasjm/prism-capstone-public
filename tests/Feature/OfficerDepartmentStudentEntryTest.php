<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\DisciplinaryCase;
use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerDepartmentStudentEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_lookup_returns_only_students_from_the_officers_department(): void
    {
        [$officer, $ownStudent, $otherStudent] = $this->departmentUsers();

        $this->actingAs($officer)
            ->getJson(route('officer.students.lookup', ['q' => 'Department Student']))
            ->assertOk()
            ->assertJsonFragment(['user_id' => $ownStudent->id])
            ->assertJsonMissing(['user_id' => $otherStudent->id]);
    }

    public function test_out_of_department_student_cannot_be_submitted_to_either_entry_form(): void
    {
        [$officer, $ownStudent, $otherStudent] = $this->departmentUsers();

        $violationData = [
            'student_id' => $otherStudent->id,
            'date_of_incident' => today()->toDateString(),
            'location' => 'Main Gate',
            'description' => 'A documented violation observed by the discipline officer.',
            'violation_type' => 'Bringing prohibited items',
        ];

        $this->actingAs($officer)
            ->post(route('officer.violations.store'), $violationData)
            ->assertSessionHasErrors('student_id');

        $this->actingAs($officer)
            ->post(route('officer.incidents.store'), [
                ...$violationData,
                'description' => 'A sufficiently detailed incident received through a non-system channel.',
            ])
            ->assertSessionHasErrors('student_id');

        $this->assertDatabaseMissing('incident_reports', ['student_id' => $otherStudent->id]);

        $this->actingAs($officer)
            ->post(route('officer.violations.store'), [
                ...$violationData,
                'student_id' => $ownStudent->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('incident_reports', [
            'source_type' => 'violation_slip',
            'student_id' => $ownStudent->id,
        ]);
    }

    public function test_report_with_out_of_department_student_cannot_be_converted_to_case(): void
    {
        [$officer, , $otherStudent] = $this->departmentUsers();
        $report = IncidentReport::create([
            'report_number' => 'IR-DEPT-BLOCKED',
            'source_type' => 'faculty_report',
            'student_id' => $otherStudent->id,
            'date_of_incident' => today(),
            'description' => 'A report involving a student assigned to another academic department.',
            'status' => 'under_review',
        ]);

        $this->actingAs($officer)
            ->post(route('officer.incidents.convert', $report))
            ->assertForbidden();

        $this->assertSame('under_review', $report->fresh()->status);
        $this->assertSame(0, DisciplinaryCase::query()->where('incident_report_id', $report->id)->count());
    }

    public function test_incident_worklist_is_department_scoped_and_sorted_by_incident_date(): void
    {
        [$officer, $ownStudent, $otherStudent] = $this->departmentUsers();
        $ownStudent->update(['middle_name' => 'Patricia']);

        $olderOwnReport = IncidentReport::create([
            'report_number' => 'IR-DEPT-OWN-OLDER',
            'source_type' => 'faculty_report',
            'student_id' => $ownStudent->id,
            'date_of_incident' => '2026-08-15',
            'description' => 'An older report involving a student in the assigned department.',
            'status' => 'pending_review',
        ]);
        $newerOwnReport = IncidentReport::create([
            'report_number' => 'IR-DEPT-OWN-NEWER',
            'source_type' => 'security_report',
            'student_id' => $ownStudent->id,
            'date_of_incident' => '2026-08-25',
            'description' => 'A newer report involving a student in the assigned department.',
            'status' => 'pending_review',
        ]);
        $otherDepartmentReport = IncidentReport::create([
            'report_number' => 'IR-DEPT-OTHER',
            'source_type' => 'faculty_report',
            'student_id' => $otherStudent->id,
            'date_of_incident' => '2026-08-30',
            'description' => 'A report involving a student outside the assigned department.',
            'status' => 'pending_review',
        ]);

        $defaultResponse = $this->actingAs($officer)
            ->get(route('officer.incidents.index'))
            ->assertOk()
            ->assertSeeInOrder([$newerOwnReport->report_number, $olderOwnReport->report_number])
            ->assertDontSee($otherDepartmentReport->report_number)
            ->assertSee('Department Student, Own P.')
            ->assertDontSee('Own Patricia Department Student')
            ->assertViewHas('stats', fn (array $stats) => $stats['pending'] === 2)
            ->assertViewHas('sort', 'incident_date')
            ->assertViewHas('direction', 'desc')
            ->assertSee('aria-sort="descending"', false)
            ->assertSee('direction=asc', false);

        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*href="'.preg_quote(route('officer.incidents.show', $newerOwnReport), '/').'"[^>]*>\s*'
                .preg_quote($newerOwnReport->report_number, '/').'\s*<\/a>/',
            $defaultResponse->getContent()
        );

        $this->actingAs($officer)
            ->get(route('officer.incidents.index', [
                'sort' => 'incident_date',
                'direction' => 'asc',
            ]))
            ->assertOk()
            ->assertSeeInOrder([$olderOwnReport->report_number, $newerOwnReport->report_number])
            ->assertViewHas('direction', 'asc')
            ->assertSee('aria-sort="ascending"', false)
            ->assertSee('direction=desc', false);

        $this->actingAs($officer)
            ->getJson(route('officer.incidents.search-suggestions', ['q' => 'IR-DEPT']))
            ->assertOk()
            ->assertJsonFragment(['report_number' => $newerOwnReport->report_number])
            ->assertJsonFragment(['report_number' => $olderOwnReport->report_number])
            ->assertJsonMissing(['report_number' => $otherDepartmentReport->report_number]);

        $this->actingAs($officer)
            ->get(route('officer.incidents.show', $otherDepartmentReport))
            ->assertForbidden();
    }

    public function test_officer_can_reopen_an_unidentified_report_they_directly_encoded(): void
    {
        [$officer] = $this->departmentUsers();

        $this->actingAs($officer)
            ->post(route('officer.incidents.store'), [
                'date_of_incident' => '2026-08-30',
                'location' => 'Main Building',
                'description' => 'An unidentified incident that requires follow-up before linking a student.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $report = IncidentReport::query()
            ->where('reported_by_user_id', $officer->id)
            ->whereNull('student_id')
            ->firstOrFail();

        $this->actingAs($officer)
            ->get(route('officer.incidents.show', $report))
            ->assertOk();
    }

    public function test_multiple_department_assignments_are_combined_for_student_lookup(): void
    {
        $units = AcademicUnit::query()->with('programs')->whereNull('scope_year_level')->orderBy('id')->take(3)->get();
        $this->assertCount(3, $units);
        $officer = $this->user('discipline_officer', 'Multiple', 'multiple.department@example.test');
        $officer->academicUnits()->sync([$units[0]->id, $units[1]->id]);
        $officer->update(['academic_unit_id' => $units[0]->id]);
        $first = $this->student('First', 'first.unit@example.test', $units[0]->programs->firstOrFail(), '2026-MULTI-001');
        $second = $this->student('Second', 'second.unit@example.test', $units[1]->programs->firstOrFail(), '2026-MULTI-002');
        $excluded = $this->student('Excluded', 'excluded.unit@example.test', $units[2]->programs->firstOrFail(), '2026-MULTI-003');

        $this->actingAs($officer)
            ->getJson(route('officer.students.lookup', ['q' => 'Department Student']))
            ->assertJsonFragment(['user_id' => $first->id])
            ->assertJsonFragment(['user_id' => $second->id])
            ->assertJsonMissing(['user_id' => $excluded->id]);
    }

    public function test_bsit_abbreviation_matches_bs_information_technology_department(): void
    {
        $program = AcademicProgram::query()
            ->where('name', 'BS Information Technology')
            ->firstOrFail();
        $officer = $this->user('discipline_officer', 'Technology', 'technology.officer@example.test');
        $officer->academicUnits()->sync([$program->academic_unit_id]);
        $student = $this->user('student', 'BSIT', 'bsit.student@example.test');
        $student->studentProfile()->create([
            'student_number' => '2023-BSIT-001',
            'program' => 'BSIT',
        ]);

        $this->actingAs($officer)
            ->getJson(route('officer.students.lookup', ['q' => '2023-BSIT']))
            ->assertOk()
            ->assertJsonFragment([
                'user_id' => $student->id,
                'program' => 'BSIT',
            ]);
    }

    public function test_grade_11_and_grade_12_assignments_are_scoped_by_year_level(): void
    {
        $program = AcademicProgram::query()->firstOrFail();
        $grade11Unit = AcademicUnit::query()->where('scope_year_level', 'Grade 11')->firstOrFail();
        $grade12Unit = AcademicUnit::query()->where('scope_year_level', 'Grade 12')->firstOrFail();
        $grade11Officer = $this->user('discipline_officer', 'Grade Eleven', 'grade11@example.test');
        $grade12Officer = $this->user('discipline_officer', 'Grade Twelve', 'grade12@example.test');
        $grade11Officer->academicUnits()->sync([$grade11Unit->id]);
        $grade12Officer->academicUnits()->sync([$grade12Unit->id]);
        $grade11 = $this->student('Eleven', 'eleven.student@example.test', $program, '2026-GRADE-011');
        $grade12 = $this->student('Twelve', 'twelve.student@example.test', $program, '2026-GRADE-012');
        $grade11->studentProfile()->update(['year_level' => 'Grade 11']);
        $grade12->studentProfile()->update(['year_level' => 'Grade 12']);

        $this->actingAs($grade11Officer)
            ->getJson(route('officer.students.lookup', ['q' => 'student']))
            ->assertJsonFragment(['user_id' => $grade11->id])
            ->assertJsonMissing(['user_id' => $grade12->id]);

        $this->actingAs($grade12Officer)
            ->getJson(route('officer.students.lookup', ['q' => 'student']))
            ->assertJsonFragment(['user_id' => $grade12->id])
            ->assertJsonMissing(['user_id' => $grade11->id]);
    }

    private function departmentUsers(): array
    {
        $units = AcademicUnit::query()->with('programs')->orderBy('id')->take(2)->get();
        $this->assertCount(2, $units);

        $ownProgram = $units[0]->programs->firstOrFail();
        $otherProgram = $units[1]->programs->firstOrFail();
        $officer = $this->user('discipline_officer', 'Officer', 'officer.department@example.test');
        $officer->update(['academic_unit_id' => $units[0]->id]);

        return [
            $officer,
            $this->student('Own', 'own.department.student@example.test', $ownProgram, '2026-DEPT-OWN'),
            $this->student('Other', 'other.department.student@example.test', $otherProgram, '2026-DEPT-OTHER'),
        ];
    }

    private function student(string $firstName, string $email, AcademicProgram $program, string $number): User
    {
        $student = $this->user('student', $firstName, $email);
        $student->studentProfile()->create([
            'student_number' => $number,
            'program' => $program->name,
        ]);

        return $student;
    }

    private function user(string $role, string $firstName, string $email): User
    {
        return User::create([
            'first_name' => $firstName,
            'last_name' => 'Department Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
