<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StudentHandbook;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentHandbookManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_discipline_coordinator_can_open_management_page(): void
    {
        $this->actingAs($this->user('discipline_coordinator', 'coordinator-handbooks@example.test'))
            ->get(route('coordinator.handbooks.index'))
            ->assertOk()
            ->assertSee('Upload New Edition')
            ->assertSee('Edition Library')
            ->assertSee('name="edition_year"', false)
            ->assertSee('>Edition</span>', false)
            ->assertSee('<span class="visually-hidden">Actions</span>', false)
            ->assertDontSee('<i class="bi bi-check-lg"></i> Save Changes', false);

        $this->actingAs($this->user('super_admin', 'admin-handbooks@example.test'))
            ->get(route('coordinator.handbooks.index'))
            ->assertForbidden();

        $this->actingAs($this->user('discipline_officer', 'officer-handbooks@example.test'))
            ->get(route('coordinator.handbooks.index'))
            ->assertForbidden();
    }

    public function test_coordinator_can_upload_a_private_pdf_as_a_draft(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator', 'coordinator-handbooks@example.test');

        $this->actingAs($coordinator)
            ->post(route('coordinator.handbooks.store'), [
                'title' => 'NU Clark Student Handbook',
                'edition_year' => '2026',
                'academic_year' => '2026-2027',
                'effective_date' => today()->toDateString(),
                'notes' => 'Updated conduct policies.',
                'handbook_file' => UploadedFile::fake()
                    ->create('student-handbook-2026.pdf', 256, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $handbook = StudentHandbook::query()->where('edition', '2026 Edition')->firstOrFail();

        $this->assertSame('draft', $handbook->status);
        $this->assertSame($coordinator->id, $handbook->uploaded_by);
        Storage::disk('local')->assertExists($handbook->file_path);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $coordinator->id,
            'action' => 'create',
            'module' => 'student_handbooks',
            'record_id' => $handbook->id,
        ]);
    }

    public function test_coordinator_edits_a_draft_using_only_its_four_digit_edition_year(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator', 'coordinator-edit-handbook@example.test');
        $draft = $this->handbook('2026 Edition', 'draft', today()->toDateString());

        $this->actingAs($coordinator)
            ->patch(route('coordinator.handbooks.update', $draft), [
                'edit_target_id' => $draft->id,
                'edit_title' => 'NU Clark Student Handbook',
                'edit_edition_year' => '2027',
                'edit_academic_year' => '2027-2028',
                'edit_effective_date' => today()->toDateString(),
                'edit_notes' => 'Revised draft.',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_handbooks', [
            'id' => $draft->id,
            'edition' => '2027 Edition',
            'academic_year' => '2027-2028',
        ]);
    }

    public function test_edition_year_must_be_exactly_four_digits(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator', 'coordinator-invalid-edition@example.test');

        $this->actingAs($coordinator)
            ->from(route('coordinator.handbooks.index'))
            ->post(route('coordinator.handbooks.store'), [
                'title' => 'NU Clark Student Handbook',
                'edition_year' => '2026 Edition',
                'effective_date' => today()->toDateString(),
                'handbook_file' => UploadedFile::fake()
                    ->create('invalid-edition.pdf', 128, 'application/pdf'),
            ])
            ->assertRedirect(route('coordinator.handbooks.index'))
            ->assertSessionHasErrors('edition_year');

        $this->assertDatabaseMissing('student_handbooks', [
            'edition' => '2026 Edition Edition',
        ]);
    }

    public function test_publishing_a_draft_archives_the_previous_edition_and_updates_student_dashboard(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator', 'coordinator-publish-handbook@example.test');
        $student = $this->user('student', 'student-handbooks@example.test');
        $previous = StudentHandbook::current();
        $draft = $this->handbook('2026 Edition', 'draft', today()->toDateString());
        Storage::disk('local')->put($draft->file_path, '%PDF-1.4 new edition');

        $this->actingAs($coordinator)
            ->patch(route('coordinator.handbooks.publish', $draft))
            ->assertSessionHas('success');

        $this->assertSame('published', $draft->fresh()->status);
        $this->assertSame('archived', $previous?->fresh()->status);
        $this->assertSame(1, StudentHandbook::query()->published()->count());

        $this->actingAs($student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('2026 Edition')
            ->assertSee(route('student.handbook.view'))
            ->assertSee(route('student.handbook.download'));

        $this->actingAs($student)
            ->get(route('student.handbook.download'))
            ->assertOk()
            ->assertDownload('NU Clark Student Handbook 2026 Edition.pdf');
    }

    public function test_future_effective_draft_cannot_be_published(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator-future-handbook@example.test');
        $draft = $this->handbook('2027 Edition', 'draft', today()->addDay()->toDateString());

        $this->actingAs($coordinator)
            ->patch(route('coordinator.handbooks.publish', $draft))
            ->assertSessionHas('error');

        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_published_and_archived_editions_are_immutable(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator-immutable-handbook@example.test');
        $published = StudentHandbook::current();

        $this->actingAs($coordinator)
            ->patch(route('coordinator.handbooks.update', $published), [
                'edit_target_id' => $published?->id,
                'edit_title' => 'Changed',
                'edit_edition' => 'Changed Edition',
                'edit_effective_date' => today()->toDateString(),
            ])
            ->assertSessionHas('error');

        $this->actingAs($coordinator)
            ->delete(route('coordinator.handbooks.destroy', $published), [
                'delete_target_id' => $published?->id,
                'delete_reason' => 'Attempted removal.',
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('student_handbooks', [
            'id' => $published?->id,
            'status' => 'published',
            'edition' => '2025 Edition',
        ]);
    }

    public function test_draft_deletion_requires_a_reason_and_removes_its_private_file(): void
    {
        Storage::fake('local');
        $coordinator = $this->user('discipline_coordinator', 'coordinator-delete-handbook@example.test');
        $draft = $this->handbook('Discarded Edition', 'draft', today()->toDateString());
        Storage::disk('local')->put($draft->file_path, '%PDF-1.4 draft');

        $this->actingAs($coordinator)
            ->from(route('coordinator.handbooks.index'))
            ->delete(route('coordinator.handbooks.destroy', $draft), [
                'delete_target_id' => $draft->id,
                'delete_reason' => '',
            ])
            ->assertRedirect(route('coordinator.handbooks.index'))
            ->assertSessionHasErrors('delete_reason');

        $this->actingAs($coordinator)
            ->delete(route('coordinator.handbooks.destroy', $draft), [
                'delete_target_id' => $draft->id,
                'delete_reason' => 'Duplicate draft upload.',
            ])
            ->assertSessionHas('success');

        $this->assertModelMissing($draft);
        Storage::disk('local')->assertMissing($draft->file_path);

        $audit = AuditLog::query()
            ->where('module', 'student_handbooks')
            ->where('record_id', $draft->id)
            ->where('action', 'void')
            ->firstOrFail();
        $this->assertSame('Duplicate draft upload.', $audit->reason);
    }

    private function handbook(string $edition, string $status, string $effectiveDate): StudentHandbook
    {
        return StudentHandbook::create([
            'title' => 'NU Clark Student Handbook',
            'edition' => $edition,
            'effective_date' => $effectiveDate,
            'file_path' => 'student-handbooks/'.strtolower(str_replace(' ', '-', $edition)).'.pdf',
            'original_filename' => "{$edition}.pdf",
            'file_size' => 1024,
            'checksum_sha256' => str_repeat('a', 64),
            'status' => $status,
        ]);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'first_name' => 'Handbook',
            'last_name' => 'Manager',
            'email' => $email,
            'password' => Hash::make('Password123'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
