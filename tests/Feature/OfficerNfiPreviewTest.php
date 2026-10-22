<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseResolution;
use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerNfiPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_officer_can_preview_the_notice_before_issuing_it(): void
    {
        [$officer, $case] = $this->resolvedCase('DC-NFI-PREVIEW');

        $response = $this->actingAs($officer)->get(route('officer.cases.nfi', $case));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        // Named as a preview so a saved copy is never mistaken for the issued notice.
        $this->assertStringContainsString(
            'preview-notice-of-formative-intervention-'.$case->case_number.'.pdf',
            $response->headers->get('Content-Disposition'),
        );

        // Previewing must not record the notice or activate anything.
        $this->assertDatabaseMissing('case_notices', [
            'case_id' => $case->id,
            'notice_type' => 'notice_of_formative_intervention',
        ]);
    }

    public function test_the_preview_becomes_the_issued_document_once_the_notice_exists(): void
    {
        [$officer, $case] = $this->resolvedCase('DC-NFI-ISSUED');

        $this->actingAs($officer)
            ->from(route('officer.cases.show', $case))
            ->post(route('officer.cases.issue-nfi', $case))
            ->assertRedirect();

        $response = $this->actingAs($officer)->get(route('officer.cases.nfi', $case));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'notice-of-formative-intervention-'.$case->case_number.'.pdf',
            $response->headers->get('Content-Disposition'),
        );
        $this->assertStringNotContainsString('preview-', $response->headers->get('Content-Disposition'));
    }

    public function test_previewing_before_a_resolution_exists_is_refused(): void
    {
        $officer = $this->user('discipline_officer', 'nfi-none-officer@example.test');
        $student = $this->user('student', 'nfi-none-student@example.test');

        $case = DisciplinaryCase::create([
            'case_number' => 'DC-NFI-NO-RESOLUTION',
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'under_review',
            'prior_offense_count' => 0,
        ]);

        $this->actingAs($officer)
            ->get(route('officer.cases.nfi', $case))
            ->assertRedirect(route('officer.cases.show', $case))
            ->assertSessionHas('error');
    }

    /**
     * @return array{0: User, 1: DisciplinaryCase}
     */
    private function resolvedCase(string $caseNumber): array
    {
        $officer = $this->user('discipline_officer', "officer-{$caseNumber}@example.test");
        $student = $this->user('student', "student-{$caseNumber}@example.test");

        $case = DisciplinaryCase::create([
            'case_number' => $caseNumber,
            'student_id' => $student->id,
            'case_type' => 'major',
            'assigned_to' => $officer->id,
            'status' => 'resolved',
            'prior_offense_count' => 0,
            'assigned_sanction_category' => 2,
        ]);

        CaseResolution::create([
            'case_id' => $case->id,
            'resolution_level' => 'sdo_level',
            'intervention_category' => 2,
            'us_hours_assigned' => 16,
            'counseling_referred' => true,
            'resolved_by' => $officer->id,
            'resolved_at' => now(),
            'appeal_deadline' => now()->addDays(5),
            'appeal_filed' => false,
            'created_at' => now(),
        ]);

        return [$officer, $case->fresh()];
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
