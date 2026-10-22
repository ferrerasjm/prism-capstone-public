<?php

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerCaseSanctionTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_issue_nfi_before_university_service_office_is_assigned(): void
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => 'nfi-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-NFI-001',
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

        $this->actingAs($officer)
            ->get(route('officer.us.create', $resolution))
            ->assertRedirect(route('officer.cases.show', $case))
            ->assertSessionHas('error', 'Issue the Notice of Formative Intervention before creating the University Service assignment.');

        $this->actingAs($officer)
            ->post(route('officer.cases.issue-nfi', $case))
            ->assertRedirect(route('officer.cases.show', $case));

        $this->assertDatabaseHas('case_notices', [
            'case_id' => $case->id,
            'notice_type' => 'notice_of_formative_intervention',
            'response_deadline' => null,
        ]);
        $this->assertDatabaseCount('us_assignments', 0);
    }

    public function test_reloading_resolution_form_after_case_is_resolved_redirects_to_case_details(): void
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => 'resolved-officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-RESOLVED-001',
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'prior_offense_count' => 0,
        ]);

        $this->actingAs($officer)
            ->get(route('officer.cases.resolution', $case))
            ->assertRedirect(route('officer.cases.show', $case))
            ->assertSessionHas('info', 'This case is no longer pending resolution.');
    }

    public function test_recommended_sanction_category_does_not_require_override_reason(): void
    {
        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => 'officer@example.test',
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-TEST-001',
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
            'suggested_sanction_category' => 2,
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);

        $response = $this
            ->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-sanction', $case), [
                'sanction_category' => '2',
            ]);

        $response
            ->assertRedirect(route('officer.cases.show', $case).'#action-panel')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'assigned_sanction_category' => 2,
            'sanction_override_reason' => null,
        ]);
    }

    public function test_a_sanction_override_reason_must_meet_the_same_length_floor_as_other_overrides(): void
    {
        $officer = $this->user('discipline_officer', 'override-length-officer@example.test');
        $student = $this->user('student', 'override-length-student@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-OVERRIDE-LENGTH',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'open',
            'prior_offense_count' => 0,
            'suggested_sanction_category' => 2,
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-sanction', $case), [
                'sanction_category' => '3',
                'override_reason' => 'too short',
            ])
            ->assertSessionHasErrors('override_reason');

        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'assigned_sanction_category' => null,
        ]);

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.confirm-sanction', $case), [
                'sanction_category' => '3',
                'override_reason' => 'Repeat offender within the same term, so formative intervention is not proportionate.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cases', [
            'id' => $case->id,
            'assigned_sanction_category' => 3,
        ]);
    }

    public function test_officer_cannot_record_a_resolution_for_another_officers_case(): void
    {
        $assignedOfficer = $this->user('assigned-officer@example.test');
        $otherOfficer = $this->user('other-officer@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-AUTH-001',
            'case_type' => 'major',
            'assigned_to' => $assignedOfficer->id,
            'status' => 'pending_resolution',
            'prior_offense_count' => 0,
        ]);

        $this->actingAs($otherOfficer)
            ->post(route('officer.cases.resolution.store', $case), [
                'intervention_category' => 3,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('case_resolutions', ['case_id' => $case->id]);
    }

    private function user(string $email): User
    {
        return User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);
    }
}
