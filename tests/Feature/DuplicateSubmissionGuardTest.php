<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A slow page invites a second click on Submit, which used to file the same
 * complaint or report twice. Each form carries a one-time submission token;
 * only the first POST carrying it may write.
 */
class DuplicateSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_double_submitted_student_complaint_is_filed_once(): void
    {
        $student = $this->student();
        $token = (string) Str::uuid();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($student)
            ->post(route('student.complaints.store'), $this->complaintData($token))
            ->assertRedirect(route('student.complaints.index'))
            ->assertSessionHas('success');

        $this->actingAs($student)
            ->post(route('student.complaints.store'), $this->complaintData($token))
            ->assertRedirect(route('student.complaints.index'))
            ->assertSessionHas('info')
            ->assertSessionMissing('success');

        $this->assertSame(1, IncidentReport::where('reported_by_user_id', $student->id)->count());
    }

    public function test_a_second_complaint_with_its_own_token_still_goes_through(): void
    {
        $student = $this->student();
        $this->withoutMiddleware(PreventRequestForgery::class);

        foreach ([(string) Str::uuid(), (string) Str::uuid()] as $token) {
            $this->actingAs($student)
                ->post(route('student.complaints.store'), $this->complaintData($token))
                ->assertSessionHas('success');
        }

        $this->assertSame(2, IncidentReport::where('reported_by_user_id', $student->id)->count());
    }

    public function test_double_submitted_incident_report_is_filed_once(): void
    {
        $reporter = $this->reportingUser();
        $token = (string) Str::uuid();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($reporter)
            ->post(route('reporter.reports.store'), $this->reportData($token))
            ->assertRedirect(route('reporter.reports.index'))
            ->assertSessionHas('success');

        $this->actingAs($reporter)
            ->post(route('reporter.reports.store'), $this->reportData($token))
            ->assertRedirect(route('reporter.reports.index'))
            ->assertSessionHas('info');

        $this->assertSame(1, IncidentReport::where('reported_by_user_id', $reporter->id)->count());
    }

    public function test_one_students_token_does_not_block_another_students_submission(): void
    {
        $token = (string) Str::uuid();
        $this->withoutMiddleware(PreventRequestForgery::class);

        $first = $this->student();
        $second = $this->student();

        $this->actingAs($first)
            ->post(route('student.complaints.store'), $this->complaintData($token))
            ->assertSessionHas('success');

        $this->actingAs($second)
            ->post(route('student.complaints.store'), $this->complaintData($token))
            ->assertSessionHas('success');

        $this->assertSame(2, IncidentReport::count());
    }

    public function test_the_create_form_carries_a_submission_token(): void
    {
        $this->actingAs($this->student())
            ->get(route('student.complaints.create'))
            ->assertOk()
            ->assertSee('name="submission_token"', false);
    }

    private function complaintData(string $token): array
    {
        return [
            'submission_token' => $token,
            'date_of_incident' => now()->toDateString(),
            'time_of_incident' => '09:15',
            'location' => 'Library, 2nd floor',
            'description' => 'A complete description of the incident, long enough to satisfy the fifty character minimum.',
            'disclaimer' => '1',
        ];
    }

    private function reportData(string $token): array
    {
        return [
            'submission_token' => $token,
            'date_of_incident' => now()->toDateString(),
            'time_of_incident' => '10:30',
            'location' => 'Main Lobby',
            'description' => 'A detailed incident report with enough information for SDO review.',
            'disclaimer' => '1',
        ];
    }

    private function student(): User
    {
        return User::create([
            'last_name' => 'Santos',
            'first_name' => 'Nina',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);
    }

    private function reportingUser(): User
    {
        return User::create([
            'last_name' => 'Reporter',
            'first_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'reporting_user',
            'office_name' => 'Security Office',
            'status' => 'active',
        ]);
    }
}
