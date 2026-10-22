<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\RosterReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RegistrarRosterReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reason_is_required_only_for_other_and_hidden_reason_is_not_recorded(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@students.nu-clark.edu.ph');
        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $student), [
            'deactivation_category' => 'other',
        ])->assertSessionHasErrors('reason');
        $this->assertSame('active', $student->fresh()->status);
        $this->patch(route('admin.users.toggle-status', $student), [
            'deactivation_category' => 'graduated',
        ])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $student->fresh()->status);
        $this->assertNull(AuditLog::where('module', 'users')->latest('id')->firstOrFail()->reason);
        $this->patch(route('admin.users.toggle-status', $student))->assertSessionHasNoErrors();
        $this->patch(route('admin.users.toggle-status', $student), [
            'deactivation_category' => 'transferred', 'reason' => 'Stale hidden text',
        ])->assertSessionHasNoErrors();
        $this->assertNull(AuditLog::where('module', 'users')->latest('id')->firstOrFail()->reason);
        $this->patch(route('admin.users.toggle-status', $student))->assertSessionHasNoErrors();
        $this->patch(route('admin.users.toggle-status', $student), [
            'deactivation_category' => 'other', 'reason' => 'Registrar requested an account review.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Registrar requested an account review.', AuditLog::where('module', 'users')->latest('id')->firstOrFail()->reason);
    }

    private function user(string $role, string $email): User
    {
        return User::create(['first_name' => 'Test', 'last_name' => 'Review', 'email' => $email,
            'password' => bcrypt('TestPassword2026'), 'role' => $role, 'status' => 'active']);
    }

    public function test_obsolete_roster_pages_are_removed_and_records_preserved(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $period = AcademicPeriod::create(['academic_year' => '2026-2027', 'term_name' => '1st Term', 'starts_on' => '2026-06-01', 'ends_on' => '2026-09-30', 'is_active' => true]);
        $review = RosterReview::create(['academic_period_id' => $period->id, 'reviewed_by' => $admin->id,
            'source_reference' => 'Legacy test', 'roster_date' => today(), 'file_hash' => str_repeat('a', 64),
            'comparison' => [['number' => '2026-999', 'user_id' => null, 'name' => 'Not yet registered', 'status' => '-', 'result' => 'New']]]);
        $this->actingAs($admin)->get('/admin/roster-reviews')->assertNotFound();
        $this->get('/admin/roster-reviews/'.$review->id)->assertNotFound();
        $this->post('/admin/roster-reviews')->assertNotFound();
        $this->post('/admin/roster-reviews/'.$review->id.'/complete', ['confirmed' => 1])->assertNotFound();
        $this->assertNull($review->fresh()->completed_at);
        $this->get(route('admin.student-statuses.index'))->assertOk()->assertDontSee('Earlier Roster Reviews');
    }

    public function test_pending_status_survives_without_audit_history_and_records_category(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@students.nu-clark.edu.ph');
        $student->update(['status' => 'pending_setup']);
        $this->actingAs($admin)->patch(route('admin.users.toggle-status', $student), [
            'reason' => 'Registrar confirmed withdrawal', 'deactivation_category' => 'withdrawn', 'deactivation_effective_on' => today()->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('withdrawn', $student->fresh()->deactivation_category);
        $this->assertSame('pending_setup', $student->fresh()->status_before_deactivation);
        AuditLog::where('module', 'users')->delete();
        $this->patch(route('admin.users.toggle-status', $student))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('pending_setup', $student->fresh()->status);
    }

    public function test_coordinator_historical_report_lookup_includes_inactive_student(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coord@nu-clark.edu.ph');
        $student = $this->user('student', 'student@students.nu-clark.edu.ph');
        $student->update(['status' => 'inactive']);
        $student->studentProfile()->create(['student_number' => '2026-123']);
        $this->actingAs($coordinator)->getJson(route('coordinator.reports.student-lookup', ['q' => '2026-123']))
            ->assertOk()->assertJsonPath('results.0.user_id', $student->id)->assertJsonPath('results.0.status', 'inactive');
    }
}
