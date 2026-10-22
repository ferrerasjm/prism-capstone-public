<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AcademicUnit;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AcademicProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_add_units_and_programs(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');

        $this->actingAs($admin)
            ->post(route('admin.academic-units.store'), ['name' => 'School of Computing'])
            ->assertSessionHas('success');

        $unit = AcademicUnit::query()->where('name', 'School of Computing')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.academic-programs.store'), [
                'academic_unit_id' => $unit->id,
                'name' => 'BS Computer Science',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('academic_programs', [
            'academic_unit_id' => $unit->id,
            'name' => 'BS Computer Science',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create',
            'module' => 'academic_programs',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.academic-programs.index'))
            ->assertOk()
            ->assertSee('School of Computing')
            ->assertSee('BS Computer Science')
            ->assertSee('editUnitModal')
            ->assertSee('editProgramModal')
            ->assertSee('catalogStatusModal')
            ->assertSee('Archive Academic Department')
            ->assertSee('Archive Reason')
            ->assertSee('Save Changes')
            ->assertSee('<span class="visually-hidden">Actions</span>', false)
            ->assertDontSee('<i class="bi bi-check-lg"></i> Save Changes', false)
            ->assertDontSee('return confirm', false)
            ->assertDontSee('data-catalog-field', false);
    }

    public function test_catalog_can_filter_programs_by_department_without_limiting_modal_choices(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $selectedUnit = AcademicUnit::query()->firstOrFail();
        $otherUnit = AcademicUnit::create([
            'name' => 'Filter Test Department',
            'is_active' => true,
        ]);
        $selectedProgram = AcademicProgram::create([
            'academic_unit_id' => $selectedUnit->id,
            'name' => 'Selected Department Program',
            'is_active' => true,
        ]);
        $otherProgram = AcademicProgram::create([
            'academic_unit_id' => $otherUnit->id,
            'name' => 'Other Department Program',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.academic-programs.index', ['department' => $selectedUnit->id]))
            ->assertOk()
            ->assertSee($selectedUnit->name)
            ->assertSee($selectedProgram->name)
            ->assertDontSee($otherProgram->name)
            ->assertSee('Filter Test Department')
            ->assertSee('All Departments');
    }

    public function test_super_admin_can_rename_an_academic_unit(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $unit = AcademicUnit::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.academic-units.update', $unit), [
                'name' => 'Renamed Academic Unit',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Renamed Academic Unit', $unit->fresh()->name);
    }

    public function test_renaming_a_program_preserves_existing_student_profile_history(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $program = AcademicProgram::query()->firstOrFail();
        StudentProfile::create([
            'student_number' => '2026-00001',
            'program' => $program->name,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-programs.update', $program), [
                'academic_unit_id' => $program->academic_unit_id,
                'name' => 'Renamed Program',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_profiles', [
            'student_number' => '2026-00001',
            'program' => 'Renamed Program',
        ]);
    }

    public function test_archived_program_is_hidden_from_onboarding_but_history_is_preserved(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $program = AcademicProgram::query()->firstOrFail();
        StudentProfile::create([
            'student_number' => '2026-00001',
            'program' => $program->name,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-programs.toggle-status', $program))
            ->assertSessionHasErrors('archive_reason');

        $this->assertTrue($program->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.academic-programs.toggle-status', $program), [
                'archive_reason' => 'The program is no longer accepting new students.',
            ])
            ->assertSessionHas('success');

        $student = $this->user('student', 'student@students.nu-clark.edu.ph', 'pending_setup');
        $this->actingAs($student)
            ->get(route('onboarding.profile'))
            ->assertOk()
            ->assertDontSee('<option value="'.$program->name.'"', false);

        $this->assertDatabaseHas('student_profiles', [
            'student_number' => '2026-00001',
            'program' => $program->name,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'academic_programs',
            'record_id' => $program->id,
            'reason' => 'The program is no longer accepting new students.',
        ]);
    }

    public function test_archiving_an_empty_department_requires_and_audits_a_reason(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $unit = AcademicUnit::create([
            'name' => 'Legacy Academic Department',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.academic-units.toggle-status', $unit))
            ->assertSessionHasErrors('archive_reason');
        $this->assertTrue($unit->fresh()->is_active);

        $this->actingAs($admin)
            ->patch(route('admin.academic-units.toggle-status', $unit), [
                'archive_reason' => 'The department has been consolidated into another unit.',
            ])
            ->assertSessionHas('success');

        $this->assertFalse($unit->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'academic_units',
            'record_id' => $unit->id,
            'reason' => 'The department has been consolidated into another unit.',
        ]);
    }

    public function test_unit_with_active_programs_cannot_be_archived(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $unit = AcademicUnit::query()->has('programs')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.academic-units.toggle-status', $unit))
            ->assertSessionHasErrors('unit');

        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_catalog_management_is_exclusive_to_super_admins(): void
    {
        $student = $this->user('student', 'student@students.nu-clark.edu.ph');

        $this->actingAs($student)
            ->get(route('admin.academic-programs.index'))
            ->assertForbidden();
        $this->actingAs($student)
            ->post(route('admin.academic-units.store'), ['name' => 'Unauthorized Unit'])
            ->assertForbidden();
    }

    private function user(string $role, string $email, string $status = 'active'): User
    {
        return User::create([
            'last_name' => 'User',
            'first_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => $status,
        ]);
    }
}
