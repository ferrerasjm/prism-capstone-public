<?php

namespace Tests\Feature;

use App\Models\AcademicPeriod;
use App\Models\AuditLog;
use App\Models\StudentStatusBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['first_name' => 'Test', 'last_name' => 'Student', 'email' => uniqid('status').'@nu-clark.edu.ph',
            'password' => bcrypt('TestPassword2026'), 'role' => $role, 'status' => 'active']);
    }

    private function student(string $number, array $attributes = []): User
    {
        $user = $this->user('student');
        $user->forceFill($attributes)->save();
        $user->studentProfile()->create(['student_number' => $number]);
        return $user;
    }

    private function preview(string $csv): StudentStatusBatch
    {
        $admin = $this->user('super_admin');
        $period = AcademicPeriod::firstOrCreate(['academic_year' => '2026-2027', 'term_name' => '1st Term'], ['starts_on' => '2026-06-01', 'ends_on' => '2026-09-30', 'is_active' => true]);
        $this->actingAs($admin)->post(route('admin.student-statuses.store'), [
            'academic_period_id' => $period->id, 'source_reference' => 'Registrar test', 'effective_on' => today()->toDateString(),
            'csv_file' => UploadedFile::fake()->createWithContent('statuses.csv', $csv),
        ])->assertSessionHasNoErrors()->assertRedirect();
        return StudentStatusBatch::latest('id')->firstOrFail();
    }

    public function test_single_student_form_and_older_status_dates(): void
    {
        $student = $this->student('2026-123');
        $batch = $this->preview("student_number,student_status\n2026-123,enrolled\n");
        $this->get(route('admin.student-statuses.index', ['student' => $student->id]))->assertOk()->assertSee($student->list_name);
        $this->post(route('admin.student-statuses.store'), [
            'academic_period_id' => $batch->academic_period_id, 'source_reference' => 'Confirmed transfer',
            'effective_on' => today()->toDateString(), 'student_number' => '2026-123', 'student_status' => 'transferred',
        ])->assertSessionHasNoErrors();
        $single = StudentStatusBatch::latest('id')->firstOrFail();
        $this->post(route('admin.student-statuses.apply', $single), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('transferred', $student->fresh()->student_status);
        $student->update(['student_status_on' => today()->addDay()->toDateString()]);
        $older = $this->preview("student_number,student_status\n2026-123,enrolled\n");
        $this->assertSame('Needs Checking', $older->rows[0]['result']);
    }

    public function test_valid_updates_skip_unknown_students_and_preserve_omitted_students(): void
    {
        $student = $this->student('2026-123');
        $omitted = $this->student('2026-456');
        $batch = $this->preview("student_number,student_status\n2026-123,graduated\n2099-999,enrolled\n");
        $this->assertSame('active', $student->fresh()->status);
        $this->get(route('admin.student-statuses.index'))->assertOk();
        $this->get(route('admin.student-statuses.show', $batch))->assertOk()->assertSee('Needs Checking');
        $this->post(route('admin.student-statuses.apply', $batch))->assertSessionHasErrors('confirmed');
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('graduated', $student->fresh()->student_status);
        $this->assertSame('inactive', $student->fresh()->status);
        $this->assertSame('active', $omitted->fresh()->status);
        $this->assertNull($omitted->fresh()->student_status);
        $this->assertDatabaseHas('audit_logs', ['module' => 'users', 'record_id' => $student->id]);
        $count = AuditLog::count();
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame($count, AuditLog::count());
        $this->get(route('admin.student-statuses.show', $batch))->assertOk()->assertSee('Saved Changes');
        $this->patch(route('admin.users.toggle-status', $student), ['status' => 'active'])->assertSessionHas('error');
        $this->assertSame('inactive', $student->fresh()->status);
    }

    public function test_reenrollment_restores_pending_setup_and_preserves_separate_suspensions(): void
    {
        $pending = $this->student('2026-123', ['status' => 'inactive', 'deactivation_category' => 'withdrawn', 'status_before_deactivation' => 'pending_setup']);
        $suspended = $this->student('2026-456', ['status' => 'inactive', 'deactivation_category' => 'temporary']);
        $batch = $this->preview("student_number,student_status\n2026-123,enrolled\n2026-456,graduated\n");
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('pending_setup', $pending->fresh()->status);
        $this->assertSame('temporary', $suspended->fresh()->deactivation_category);
        $batch = $this->preview("student_number,student_status\n2026-456,enrolled\n");
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('inactive', $suspended->fresh()->status);
        $this->assertSame('enrolled', $suspended->fresh()->student_status);
    }

    public function test_stale_preview_rolls_back_entire_batch(): void
    {
        $first = $this->student('2026-123');
        $second = $this->student('2026-456');
        $batch = $this->preview("student_number,student_status\n2026-123,graduated\n2026-456,transferred\n");
        $second->update(['status' => 'inactive', 'deactivation_category' => 'temporary']);
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])
            ->assertSessionHas('error', 'An account changed after this preview. Nothing was applied. Upload a fresh file and review it again.');
        $this->assertSame('active', $first->fresh()->status);
        $this->assertNull($first->fresh()->student_status);
        $this->assertNull($batch->fresh()->applied_at);
        $this->assertSame(0, AuditLog::where('module', 'student_status_batches')->count());
    }

    public function test_departure_categories_and_completed_setup_reenrollment(): void
    {
        $student = $this->student('2026-123', ['privacy_accepted_at' => now()]);
        foreach (['graduated', 'transferred', 'withdrawn', 'not_enrolled'] as $status) {
            $batch = $this->preview("student_number,student_status\n2026-123,{$status}\n");
            $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
            $this->assertSame('inactive', $student->fresh()->status);
            $this->assertSame($status, $student->fresh()->student_status);
        }
        $batch = $this->preview("student_number,student_status\n2026-123,enrolled\n");
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertSessionHasNoErrors();
        $this->assertSame('active', $student->fresh()->status);
        $this->assertNull($student->fresh()->deactivation_category);
    }

    public function test_invalid_csv_and_future_dates_are_rejected_and_officers_are_forbidden(): void
    {
        $this->student('2026-123');
        $batch = $this->preview("student_number,student_status\n2026-123,enrolled\n");
        $data = ['academic_period_id' => $batch->academic_period_id, 'source_reference' => 'Registrar test', 'effective_on' => today()->toDateString()];
        foreach (["student_number\n2026-123\n", "student_number,student_status\n", "student_number,student_status\n2026-123,invalid\n", "student_number,student_status\n2026-123,enrolled\n2026-123,graduated\n", "student_number,student_status\nABC,enrolled\n", "student_number,student_status\n2026-123,enrolled,extra\n"] as $csv) {
            $this->post(route('admin.student-statuses.store'), $data + ['csv_file' => UploadedFile::fake()->createWithContent('invalid.csv', $csv)])->assertSessionHasErrors('csv_file');
        }
        $data['effective_on'] = today()->addDay()->toDateString();
        $this->post(route('admin.student-statuses.store'), $data + ['csv_file' => UploadedFile::fake()->createWithContent('valid.csv', "student_number,student_status\n2026-123,enrolled\n")])->assertSessionHasErrors('effective_on');
        $this->assertSame(1, StudentStatusBatch::count());
        $officer = $this->user('discipline_officer');
        $this->actingAs($officer)->get(route('admin.student-statuses.index'))->assertForbidden();
        $this->get(route('admin.student-statuses.show', $batch))->assertForbidden();
        $this->post(route('admin.student-statuses.store'), $data)->assertForbidden();
        $this->post(route('admin.student-statuses.apply', $batch), ['confirmed' => 1])->assertForbidden();
    }
}
