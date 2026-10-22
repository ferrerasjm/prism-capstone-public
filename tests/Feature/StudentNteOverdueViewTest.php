<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The student case page must reflect a lapsed, still-unanswered NTE deadline:
 * before the fix it kept showing the plain "you are required to submit" prompt
 * with a now-past date and no sign the window had closed.
 */
class StudentNteOverdueViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_nte_before_the_deadline_shows_the_plain_prompt(): void
    {
        [$student, $case] = $this->pendingNteCase(today()->addDays(3)->toDateString());

        $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk()
            ->assertSee('You are required to submit your written explanation.')
            ->assertDontSee('Your written explanation is overdue')
            ->assertDontSee('Explanation Overdue')
            ->assertSee('Respond Now');
    }

    public function test_pending_nte_past_the_deadline_shows_the_overdue_state(): void
    {
        [$student, $case] = $this->pendingNteCase(today()->subDays(2)->toDateString());

        $response = $this->actingAs($student)
            ->get(route('student.cases.show', $case))
            ->assertOk();

        $response->assertSee('Your written explanation is overdue');
        $response->assertSee('until the Discipline Officer proceeds with the case');
        $response->assertSee('Explanation Overdue');
        // The student can still act on it.
        $response->assertSee('Respond Now');
        $response->assertDontSee('You are required to submit your written explanation.');
    }

    /** @return array{0: User, 1: DisciplinaryCase} */
    private function pendingNteCase(string $deadline): array
    {
        $officer = User::create([
            'last_name' => 'Reyes',
            'first_name' => 'Officer',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $student = User::create([
            'last_name' => 'Santos',
            'first_name' => 'Student',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'NTE-'.fake()->unique()->numerify('#####'),
            'student_id' => $student->id,
            'assigned_to' => $officer->id,
            'case_type' => 'major',
            'case_type_locked' => true,
            'status' => 'pending_nte',
            'prior_offense_count' => 0,
        ]);

        CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now(),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '41',
            'incident_date' => today()->subWeek(),
            'response_deadline' => $deadline,
            'is_voided' => false,
            'created_at' => now(),
        ]);

        return [$student, $case];
    }
}
