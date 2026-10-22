<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaseNotice;
use App\Models\DisciplinaryCase;
use App\Models\OffenseCatalog;
use App\Models\User;
use App\Notifications\NteIssuedNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OfficerNteCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_renders_the_active_nte_with_a_whole_day_count(): void
    {
        [$officer, $case] = $this->activeNte();

        $response = $this->actingAs($officer)
            ->get(route('officer.cases.show', $case))
            ->assertOk()
            ->assertSee('nte-action-grid', false)
            ->assertSee('Extend Deadline')
            ->assertSee('Correct and Reissue')
            ->assertSee('Void Without Replacement');

        $this->assertMatchesRegularExpression(
            '/\(\d+\s+days? remaining\)/',
            $response->getContent()
        );
        $response->assertDontSee('working day');
    }

    public function test_student_can_review_an_nte_with_structured_additional_content(): void
    {
        [, $case, $notice] = $this->activeNte();
        $notice->update([
            'additional_content' => [
                'offenses' => [
                    ['code' => 'MAJOR-TEST', 'title' => 'Test Offense'],
                ],
                'notes' => 'Bring any supporting documents with your response.',
            ],
        ]);

        $response = $this->actingAs($case->student)
            ->get(route('student.cases.explain', $case));

        $response->assertOk();
        $response->assertSee('Bring any supporting documents with your response.');
    }

    public function test_officer_can_extend_active_nte_and_preserve_old_deadline_in_audit_log(): void
    {
        [$officer, $case, $notice] = $this->activeNte();
        $newDeadline = $notice->response_deadline->copy()->addDays(3)->toDateString();

        $response = $this->actingAs($officer)->post(
            route('officer.cases.nte.extend', [$case, $notice]),
            ['response_deadline' => $newDeadline, 'reason' => 'Student requested additional preparation time.']
        );

        $response->assertRedirect(route('officer.cases.show', $case).'#action-panel');
        $this->assertSame($newDeadline, $notice->fresh()->response_deadline->toDateString());
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'case_notices',
            'record_id' => $notice->id,
            'action' => 'update',
        ]);
    }

    public function test_officer_can_view_the_issued_nte_document(): void
    {
        [$officer, $case] = $this->activeNte();

        $response = $this->actingAs($officer)->get(route('officer.cases.nte-notice', $case));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_officer_nte_document_is_404_when_the_case_has_no_active_nte(): void
    {
        [$officer, $case, $notice] = $this->activeNte();
        $notice->update(['is_voided' => true]);

        $this->actingAs($officer)
            ->get(route('officer.cases.nte-notice', $case))
            ->assertNotFound();
    }

    public function test_officer_case_page_links_to_the_nte_document(): void
    {
        [$officer, $case] = $this->activeNte();

        $this->actingAs($officer)
            ->get(route('officer.cases.show', $case))
            ->assertOk()
            ->assertSee('View Notice')
            // Opens in the shared preview modal, not a new tab.
            ->assertSee('data-attachment-url="'.route('officer.cases.nte-notice', $case).'"', false)
            ->assertSee('id="attachmentPreviewModal"', false);
    }

    public function test_officer_can_atomically_void_and_reissue_a_corrected_nte(): void
    {
        [$officer, $case, $notice] = $this->activeNte();

        $response = $this->actingAs($officer)->post(
            route('officer.cases.nte.reissue', [$case, $notice]),
            [
                'correction_reason' => 'The handbook page reference was entered incorrectly.',
                'handbook_section' => 'Section VI.E',
                'handbook_page' => '42',
                'incident_date' => now()->subDay()->toDateString(),
            ]
        );

        $response->assertRedirect(route('officer.cases.show', $case).'#action-panel');
        $this->assertDatabaseHas('case_notices', ['id' => $notice->id, 'is_voided' => true]);
        $this->assertDatabaseHas('case_notices', [
            'case_id' => $case->id,
            'handbook_page' => '42',
            'is_voided' => false,
        ]);
        $this->assertSame(2, CaseNotice::where('case_id', $case->id)->count());
        $this->assertSame('pending_nte', $case->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'type' => NteIssuedNotification::class,
            'notifiable_id' => $case->student_id,
        ]);
        $notification = $case->student->notifications()
            ->where('type', NteIssuedNotification::class)
            ->firstOrFail();
        $this->assertSame(
            route('student.cases.explain', $case, false),
            $notification->data['url']
        );
    }

    public function test_nte_uses_catalog_handbook_reference_without_manual_input(): void
    {
        [$officer, $case, $notice] = $this->activeNte();
        $case->confirmedOffense->update([
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '37',
        ]);
        $notice->update(['is_voided' => true]);
        $case->update(['status' => 'open']);

        $response = $this->actingAs($officer)->post(
            route('officer.cases.issue-nte', $case),
            ['incident_date' => now()->subDay()->toDateString()]
        );

        $response->assertRedirect(route('officer.cases.show', $case).'#action-panel');
        $this->assertDatabaseHas('case_notices', [
            'case_id' => $case->id,
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '37',
            'is_voided' => false,
        ]);
    }

    public function test_voiding_an_nte_reopens_case_without_deleting_notice(): void
    {
        [$officer, $case, $notice] = $this->activeNte();

        $response = $this->actingAs($officer)->post(
            route('officer.cases.nte.void', [$case, $notice]),
            ['reason' => 'The notice was issued using incorrect incident details.']
        );

        $response->assertRedirect(route('officer.cases.show', $case).'#action-panel');
        $this->assertDatabaseHas('case_notices', ['id' => $notice->id, 'is_voided' => true]);
        $this->assertSame('open', $case->fresh()->status);
    }

    public function test_clicking_a_stale_nte_notification_opens_the_case_without_an_info_warning(): void
    {
        [, $case, $notice] = $this->activeNte();
        $case->student->notify(new NteIssuedNotification($case, $notice));
        $notification = $case->student->unreadNotifications()->firstOrFail();
        $case->update(['status' => 'open']);

        $response = $this->actingAs($case->student)->post(
            route('student.notifications.open', $notification)
        );

        $response->assertRedirect(route('student.cases.show', $case));
        $response->assertSessionMissing('info');
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_student_can_view_the_full_notice_to_explain_document(): void
    {
        [, $case, $notice] = $this->activeNte();

        // The notice is served as a PDF now, so the document itself is asserted through
        // its headers rather than by scanning HTML.
        $response = $this->actingAs($case->student)
            ->get(route('student.cases.nte-notice', $case))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString(
            'notice-to-explain-'.$case->case_number.'.pdf',
            $response->headers->get('Content-Disposition'),
        );

        $this->assertNotNull($notice);
    }

    private function activeNte(): array
    {
        $this->withoutMiddleware(PreventRequestForgery::class);

        $officer = User::create([
            'last_name' => 'Officer',
            'first_name' => 'Discipline',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'discipline_officer',
            'status' => 'active',
        ]);

        $offense = OffenseCatalog::create([
            'code' => 'MAJOR-TEST',
            'offense_type' => 'major',
            'title' => 'Test Offense',
            'description' => 'Test offense description.',
            'keywords' => ['test'],
            'created_at' => now(),
        ]);

        $student = User::create([
            'last_name' => 'Student',
            'first_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'student',
            'status' => 'active',
        ]);

        $case = DisciplinaryCase::create([
            'case_number' => 'CASE-'.fake()->unique()->numerify('#####'),
            'case_type' => 'major',
            'student_id' => $student->id,
            'case_type_locked' => true,
            'assigned_to' => $officer->id,
            'status' => 'pending_nte',
            'confirmed_offense_id' => $offense->id,
            'assigned_sanction_category' => 2,
            'prior_offense_count' => 0,
        ]);

        $notice = CaseNotice::create([
            'case_id' => $case->id,
            'notice_type' => 'notice_to_explain',
            'issued_by' => $officer->id,
            'issued_at' => now(),
            'handbook_section' => 'Section VI.E',
            'handbook_page' => '41',
            'incident_date' => now()->subDay()->toDateString(),
            'response_deadline' => now()->addWeek()->toDateString(),
            'is_voided' => false,
            'created_at' => now(),
        ]);

        return [$officer, $case, $notice];
    }
}
