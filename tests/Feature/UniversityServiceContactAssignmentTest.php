<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\UniversityServiceOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UniversityServiceContactAssignmentTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidAssignmentDates')]
    public function test_invalid_assignment_dates_are_rejected_and_shown_as_a_toast(int $startOffset, int $endOffset, string $field): void
    {
        [$officer, $resolution] = $this->assignmentContext();

        $this->actingAs($officer)
            ->from(route('officer.us.create', $resolution))
            ->post(route('officer.us.store', $resolution), [
                'office_name' => 'Facilities Management Office',
                'contact_key' => 'config:facilities-contact',
                'start_date' => today()->addDays($startOffset)->toDateString(),
                'target_completion_date' => today()->addDays($endOffset)->toDateString(),
            ])
            ->assertSessionHasErrors($field)
            ->assertSessionHas('error');

        $this->assertDatabaseCount('us_assignments', 0);
        $this->get(route('officer.us.create', $resolution))
            ->assertOk()
            ->assertSee('prism-toast-danger', false)
            ->assertDontSee('prism-alert-danger', false)
            ->assertSee('prism-card-header prism-card-header--blue', false)
            ->assertSee('min="'.today()->toDateString().'"', false);
    }

    public static function invalidAssignmentDates(): array
    {
        return [
            'past start' => [-1, 2, 'start_date'],
            'past completion' => [1, -1, 'target_completion_date'],
            'same-day completion' => [1, 1, 'target_completion_date'],
            'completion before start' => [3, 2, 'target_completion_date'],
        ];
    }

    public function test_officer_can_assign_university_service_to_a_directory_contact_without_a_supervisor_account(): void
    {
        [$officer, $resolution] = $this->assignmentContext();

        $response = $this->actingAs($officer)->post(
            route('officer.us.store', $resolution),
            [
                'office_name' => 'Facilities Management Office',
                'contact_key' => 'config:facilities-contact',
                'start_date' => today()->addDay()->toDateString(),
                'target_completion_date' => today()->addWeek()->toDateString(),
            ],
        );

        $assignment = $resolution->usAssignment()->firstOrFail();

        $response->assertRedirect(route('officer.us.show', $assignment));
        $this->assertDatabaseHas('us_assignments', [
            'id' => $assignment->id,
            'office_name' => 'Facilities Management Office',
            'supervisor_id' => null,
            'contact_person_name' => 'Sir Facilities Contact',
            'contact_person_email' => null,
        ]);
        $this->assertDatabaseMissing('users', ['role' => 'us_supervisor']);
    }

    public function test_contact_must_belong_to_the_selected_office(): void
    {
        [$officer, $resolution] = $this->assignmentContext();

        $this->actingAs($officer)
            ->from(route('officer.us.create', $resolution))
            ->post(route('officer.us.store', $resolution), [
                'office_name' => 'Facilities Management Office',
                'contact_key' => 'config:registrar-contact',
                'start_date' => today()->addDay()->toDateString(),
                'target_completion_date' => today()->addWeek()->toDateString(),
            ])
            ->assertRedirect(route('officer.us.create', $resolution))
            ->assertSessionHasErrors('contact_key');

        $this->assertDatabaseCount('us_assignments', 0);
    }

    public function test_existing_private_contact_keys_remain_valid_for_new_assignments(): void
    {
        [$officer, $resolution] = $this->assignmentContext();
        config(['university_service.contacts.Facilities Management Office' => [
            'existing-private-contact' => ['name' => 'Demo Supervisor', 'email' => 'supervisor@example.test'],
        ]]);

        $this->actingAs($officer)->post(route('officer.us.store', $resolution), [
            'office_name' => 'Facilities Management Office',
            'contact_key' => 'config:existing-private-contact',
            'start_date' => today()->addDay()->toDateString(),
            'target_completion_date' => today()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('us_assignments', [
            'case_resolution_id' => $resolution->id,
            'contact_person_name' => 'Demo Supervisor',
            'contact_person_email' => 'supervisor@example.test',
        ]);
    }

    private function assignmentContext(): array
    {
        UniversityServiceOffice::query()->updateOrCreate(
            ['name' => 'Facilities Management Office'],
            ['is_active' => true],
        );

        $officer = $this->user('discipline_officer', 'contact-officer@example.test');
        $student = $this->user('student', 'contact-student@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-US-CONTACT-001',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);

        $resolution = CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 20,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_of_formative_intervention',
            'issued_by' => $officer->id,
            'issued_at' => now(),
            'handbook_section' => 'Formative Intervention',
            'handbook_page' => '1',
            'incident_date' => today(),
            'is_voided' => false,
            'created_at' => now(),
        ]);

        return [$officer, $resolution];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'first_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
