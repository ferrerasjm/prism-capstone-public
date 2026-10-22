<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\UpccReferral;
use App\Models\User;
use App\Notifications\UpccAppealSubmittedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentCaseUpccStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_student_sees_appeal_window_instead_of_pending_case_conference(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [$student, $case] = $this->caseWithOpenAppealWindow();

        $showResponse = $this->actingAs($student)->get(route('student.cases.show', $case));

        $showResponse->assertOk();
        $showResponse->assertSee('Appeal Window Open');
        $showResponse->assertSee('UPCC Decision');
        $showResponse->assertSee('Decision Summary');
        $showResponse->assertSee('UPCC decision has been distributed to the student.');
        $showResponse->assertSee('Appeal Deadline');
        $showResponse->assertSee('No Appeal');
        $showResponse->assertSee('Confirm No Appeal');
        $showResponse->assertSee('data-bs-target="#noAppealModal"', false);
        $showResponse->assertDontSee("return confirm('Confirm that you will not appeal", false);
        $showResponse->assertSee('Submit Appeal');
        $showResponse->assertSee('You may file an appeal until July 19, 2026');
        $showResponse->assertDontSee('Pending Case Conference');

        $indexResponse = $this->actingAs($student)->get(route('student.cases.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Appeal Window Open');

        $statusResponse = $this->actingAs($student)->getJson(route('student.cases.status', $case));

        $statusResponse->assertOk()
            ->assertJsonPath('student_state', 'upcc_appeal_window');
    }

    public function test_appeal_precedes_incident_card_containing_submitted_explanation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [$student, $case] = $this->caseWithOpenAppealWindow();

        $case->explanation()->create([
            'submitted_at' => now()->subDay(),
            'submission_channel' => 'portal',
            'admission_type' => 'full_denial',
            'explanation_text' => 'I am submitting my account of the incident for consideration.',
            'word_count' => 10,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get(route('student.cases.show', $case));

        $response->assertOk();
        $response->assertSeeInOrder([
            'UPCC Decision',
            'Incident and Allegation',
            'Your Submitted Explanation',
        ]);
        $response->assertSee('<div class="prism-card student-incident-card"', false);
        $response->assertSee('student-explanation-section', false);
        $response->assertDontSee('student-explanation-disclosure', false);
        $response->assertDontSee('student-submitted-card', false);
    }

    public function test_student_can_waive_appeal_and_advance_case_during_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [$student, $case, $coordinator] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($student)->post(route('student.cases.appeal.waive', $case));

        $response->assertRedirect(route('student.cases.show', $case));
        $response->assertSessionHas(
            'success',
            'No appeal recorded. The UPCC decision is now final and the case may proceed.'
        );

        $referral = $case->upccReferral()->firstOrFail();
        $this->assertSame($student->id, $referral->appeal_waived_by);
        $this->assertSame('2026-07-14 10:00:00', $referral->appeal_waived_at->toDateTimeString());
        $this->assertSame('2026-07-14 10:00:00', $referral->decision_final_at->toDateTimeString());
        $this->assertFalse($referral->isAppealWindowOpen());
        $this->assertSame('pending_resolution', $case->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'action' => 'update',
            'reason' => 'Student expressly selected No Appeal during the open appeal window.',
        ]);

        $showResponse = $this->actingAs($student)->get(route('student.cases.show', $case));
        $showResponse->assertOk();
        $showResponse->assertSee('Finalized');
        $showResponse->assertDontSee('Submit Appeal');
        $showResponse->assertDontSee('No Appeal');

        $coordinatorResponse = $this->actingAs($coordinator)->get(route('coordinator.cases.progress', $case));
        $coordinatorResponse->assertOk();
        $coordinatorResponse->assertSee('Record Resolution');
        $coordinatorResponse->assertSee('The UPCC decision is final. Record the formal resolution to close the case.');
        $coordinatorResponse->assertSee('Open Resolution Form');
        $coordinatorResponse->assertDontSee('Encode an appeal here');
        $coordinatorResponse->assertDontSee('name="appeal_notes"', false);
    }

    public function test_student_can_submit_appeal_during_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        Storage::fake('local');
        [$student, $case, $coordinator] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($student)->post(
            route('student.cases.appeal.store', $case),
            [
                'appeal_notes' => 'I respectfully request reconsideration because I have additional context and supporting details for the UPCC decision.',
                'attachments' => [
                    UploadedFile::fake()->create('appeal-proof.pdf', 128, 'application/pdf'),
                ],
            ]
        );

        $response->assertRedirect(route('student.cases.show', $case));

        $referral = $case->upccReferral()->firstOrFail();
        $this->assertTrue($referral->appeal_filed);
        $this->assertSame('reconsideration_filed', $referral->status);
        $this->assertSame($student->id, $referral->appeal_filed_by);
        $this->assertSame('student_portal', $referral->appeal_source);
        $this->assertSame('2026-07-14 10:00:00', $referral->appeal_filed_at->toDateTimeString());
        $attachment = $referral->appealAttachments()->firstOrFail();
        $this->assertSame('appeal-proof.pdf', $attachment->file_name);
        Storage::disk('local')->assertExists($attachment->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $student->id,
            'module' => 'upcc_referrals',
            'record_id' => $referral->id,
            'action' => 'update',
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => UpccAppealSubmittedNotification::class,
            'notifiable_id' => $coordinator->id,
        ]);

        $showResponse = $this->actingAs($student)->get(route('student.cases.show', $case));

        $showResponse->assertOk();
        $showResponse->assertSee('Appeal Filed');
        $showResponse->assertSee('Your appeal has been recorded and is awaiting reconsideration review.');
        $showResponse->assertSee('Supporting Attachments');
        $showResponse->assertSee('appeal-proof.pdf');
        $showResponse->assertDontSee('Submit Appeal');

        $coordinatorResponse = $this->actingAs($coordinator)->get(route('coordinator.cases.show', $case));

        $coordinatorResponse->assertOk();
        // Attachments now render inside the collapsible "Appeal" section on the
        // coordinator page, not as a standalone "Appeal Attachments" card.
        $coordinatorResponse->assertSee('Appeal');
        $coordinatorResponse->assertSee('appeal-proof.pdf');
    }

    public function test_coordinator_can_record_external_appeal_without_optional_notes_during_open_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 10:00:00'));
        [, $case, $coordinator] = $this->caseWithOpenAppealWindow();

        $response = $this->actingAs($coordinator)->post(
            route('coordinator.cases.file-appeal', $case),
            ['appeal_notes' => '']
        );

        $response->assertRedirect(route('coordinator.cases.progress', $case));
        $response->assertSessionHas('success', 'Appeal recorded. The case is pending reconsideration.');

        $referral = $case->upccReferral()->firstOrFail();
        $this->assertTrue($referral->appeal_filed);
        $this->assertSame('reconsideration_filed', $referral->status);
        $this->assertSame('coordinator_entry', $referral->appeal_source);
        $this->assertSame($coordinator->id, $referral->appeal_filed_by);
        $this->assertNull($referral->appeal_notes);
    }

    private function caseWithOpenAppealWindow(): array
    {
        $student = $this->user('student');
        $coordinator = $this->user('discipline_coordinator');

        $case = DisciplinaryCase::create([
            'case_number' => 'STUDENT-UPCC-'.fake()->unique()->numerify('####'),
            'case_type' => 'major',
            'student_id' => $student->id,
            'assigned_to' => $coordinator->id,
            'status' => 'pending_upcc',
            'case_type_locked' => true,
            'prior_offense_count' => 0,
            'forwarded_to_coordinator' => true,
            'forwarded_at' => now()->subDays(8),
        ]);

        UpccReferral::create([
            'case_id' => $case->id,
            'referred_by' => $coordinator->id,
            'referred_at' => now()->subDays(7),
            'scheduled_conference_at' => now()->subDays(2),
            'conference_location' => 'SDO Conference Room',
            'status' => 'concluded',
            'proceedings_type' => 'full_hearing',
            'decision_summary' => 'UPCC decision has been distributed to the student.',
            'decision_issued_at' => now()->subDay(),
            'presidents_council_action' => 'approved',
            'presidents_council_acted_at' => now(),
            'decision_distributed_at' => now(),
            'appeal_window_start' => today()->toDateString(),
            'appeal_window_end' => today()->addDays(5)->toDateString(),
            'appeal_filed' => false,
        ]);

        return [$student, $case, $coordinator];
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'StudentStatus',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
