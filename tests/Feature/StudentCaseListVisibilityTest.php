<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * What a student is shown about a case the office has not classified yet, and
 * about one the office voided.
 *
 * A voided case is the office declaring the case should not have existed, so it
 * no longer sits in the student's list; a dismissed case is a real outcome and
 * stays. Neither list may fall through to a vague "In Progress" for a case with
 * no confirmed offense.
 */
class StudentCaseListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unclassified_case_reads_as_under_review_not_in_progress(): void
    {
        $student = $this->student();
        $this->case($student, 'DC-VIS-0001', 'under_review');

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('DC-VIS-0001')
            ->assertSee('Under Review')
            ->assertSee('Unclassified')
            ->assertDontSee('In Progress');
    }

    public function test_a_voided_case_leaves_the_student_case_list(): void
    {
        $student = $this->student();
        $this->case($student, 'DC-VIS-0002', 'voided');
        $this->case($student, 'DC-VIS-0003', 'open');

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('DC-VIS-0003')
            ->assertDontSee('DC-VIS-0002')
            ->assertDontSee('Voided');
    }

    public function test_a_dismissed_case_stays_visible_as_an_outcome(): void
    {
        $student = $this->student();
        $this->case($student, 'DC-VIS-0004', 'dismissed');

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('DC-VIS-0004')
            ->assertSee('Dismissed');
    }

    public function test_the_dashboard_hides_voided_cases_too(): void
    {
        $student = $this->student();
        $this->case($student, 'DC-VIS-0005', 'voided');
        $this->case($student, 'DC-VIS-0006', 'under_review');

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('DC-VIS-0006')
            ->assertDontSee('DC-VIS-0005');
    }

    public function test_an_explanation_under_evaluation_says_so(): void
    {
        $student = $this->student();
        $this->case($student, 'DC-VIS-0007', 'awaiting_explanation');

        $this->actingAs($student)
            ->get(route('student.cases.index'))
            ->assertOk()
            ->assertSee('Explanation Under Review');
    }

    private function case(User $student, string $number, string $status): DisciplinaryCase
    {
        return DisciplinaryCase::create([
            'case_number' => $number,
            'student_id' => $student->id,
            'case_type' => 'major',
            'case_type_locked' => false,
            'status' => $status,
            'prior_offense_count' => 0,
        ]);
    }

    private function student(): User
    {
        return User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Mika',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }
}
