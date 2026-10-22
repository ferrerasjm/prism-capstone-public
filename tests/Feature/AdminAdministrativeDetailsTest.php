<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicProgram;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\UniversityServiceOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAdministrativeDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_only_institution_controlled_student_details(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@nu-clark.edu.ph', '2026-000001');
        $profile = StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000001',
            'program' => AcademicProgram::query()->firstOrFail()->name,
            'year_level' => '1st Year',
            'contact_number' => '09171234567',
            'guardian_name' => 'Original Guardian',
        ]);
        $newProgram = AcademicProgram::query()
            ->where('name', '!=', $profile->program)
            ->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('admin.users.administrative-details.update', $student), [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'student_number' => '2026-000099',
                'program' => $newProgram->name,
                'year_level' => '2nd Year',
                'email' => 'changed@students.nu-clark.edu.ph',
                'contact_number' => '09999999999',
                'role' => 'discipline_officer',
            ])
            ->assertSessionHas('success');

        $student->refresh();
        $profile->refresh();

        $this->assertSame('Juan', $student->first_name);
        $this->assertSame('Dela Cruz', $student->last_name);
        $this->assertSame('2026-000099', $student->institutional_id);
        // An administrator is the only one who can correct a user's official email.
        $this->assertSame('changed@students.nu-clark.edu.ph', $student->email);
        $this->assertSame('student', $student->role);
        $this->assertSame('2026-000099', $profile->student_number);
        $this->assertSame($newProgram->name, $profile->program);
        $this->assertSame('2nd Year', $profile->year_level);
        $this->assertSame('09171234567', $profile->contact_number);

        $audit = AuditLog::query()
            ->where('module', 'users')
            ->where('record_id', $student->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('2026-000001', $audit->old_values['student_number']);
        $this->assertSame('2026-000099', $audit->new_values['student_number']);
        $this->assertSame('changed@students.nu-clark.edu.ph', $audit->new_values['email']);
        $this->assertArrayNotHasKey('contact_number', $audit->new_values);
    }

    public function test_admin_cannot_reuse_another_users_email_for_a_student(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $other = $this->user('student', 'taken@students.nu-clark.edu.ph', '2026-000005');
        StudentProfile::create(['user_id' => $other->id, 'student_number' => '2026-000005']);
        $student = $this->user('student', 'student@nu-clark.edu.ph', '2026-000001');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000001',
            'program' => AcademicProgram::query()->firstOrFail()->name,
            'year_level' => '1st Year',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->patch(route('admin.users.administrative-details.update', $student), [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->institutional_id,
                'program' => $student->studentProfile->program,
                'year_level' => '1st Year',
                'email' => 'taken@students.nu-clark.edu.ph',
            ])
            ->assertSessionHasErrors(['email']);

        $this->assertSame('student@nu-clark.edu.ph', $student->fresh()->email);
    }

    public function test_student_administrative_details_require_catalog_values_and_unique_number(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $existing = $this->user('student', 'existing@nu-clark.edu.ph', '2026-000001');
        StudentProfile::create([
            'user_id' => $existing->id,
            'student_number' => '2026-000001',
        ]);
        $student = $this->user('student', 'student@nu-clark.edu.ph', '2026-000002');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000002',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->patch(route('admin.users.administrative-details.update', $student), [
                'administrative_details_form' => '1',
                'first_name' => 'Test',
                'last_name' => 'Student',
                'student_number' => '2026-000001',
                'program' => 'Unconfigured Program',
                'year_level' => '7th Year',
            ])
            ->assertRedirect(route('admin.users.show', $student))
            ->assertSessionHasErrors(['student_number', 'program', 'year_level']);
    }

    public function test_student_administrative_details_use_shs_year_levels_for_shs_programs(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@students.nu-clark.edu.ph', '2026-000002');
        $shsProgram = AcademicProgram::query()
            ->whereHas('academicUnit', fn ($query) => $query->where('name', 'Senior High School'))
            ->firstOrFail();

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000002',
            'program' => AcademicProgram::query()->firstOrFail()->name,
            'year_level' => '1st Year',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.administrative-details.update', $student), [
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->institutional_id,
                'program' => $shsProgram->name,
                'year_level' => 'Grade 11',
                'email' => $student->email,
            ])
            ->assertSessionHas('success');

        $student->refresh();
        $this->assertSame($shsProgram->name, $student->studentProfile->program);
        $this->assertSame('Grade 11', $student->studentProfile->year_level);
    }

    public function test_student_administrative_details_reject_college_years_for_shs_programs(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@students.nu-clark.edu.ph', '2026-000002');
        $shsProgram = AcademicProgram::query()
            ->whereHas('academicUnit', fn ($query) => $query->where('name', 'Senior High School'))
            ->firstOrFail();

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000002',
            'program' => $shsProgram->name,
            'year_level' => 'Grade 11',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.show', $student))
            ->patch(route('admin.users.administrative-details.update', $student), [
                'administrative_details_form' => '1',
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'student_number' => $student->institutional_id,
                'program' => $shsProgram->name,
                'year_level' => '4th Year',
                'email' => $student->email,
            ])
            ->assertRedirect(route('admin.users.show', $student))
            ->assertSessionHasErrors(['year_level']);
    }

    public function test_admin_can_update_a_supervisor_id_and_active_office(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $oldOffice = UniversityServiceOffice::query()->where('name', 'Learning Resource Center')->firstOrFail();
        $newOffice = UniversityServiceOffice::create(['name' => 'Community Extension Office', 'is_active' => true]);
        $supervisor = $this->user('us_supervisor', 'supervisor@nu-clark.edu.ph', 'NU-US-001');
        $supervisor->update(['office_name' => $oldOffice->name]);

        $this->actingAs($admin)
            ->patch(route('admin.users.administrative-details.update', $supervisor), [
                'institutional_id' => 'nu-us-002',
                'email' => $supervisor->email,
                'office_name' => $newOffice->name,
            ])
            ->assertSessionHas('success');

        $supervisor->refresh();
        $this->assertSame('NU-US-002', $supervisor->institutional_id);
        $this->assertSame($newOffice->name, $supervisor->office_name);
    }

    public function test_super_admin_administrative_details_are_protected(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph', 'NU-ADMIN-001');

        $this->actingAs($admin)
            ->patch(route('admin.users.administrative-details.update', $admin), [
                'institutional_id' => 'NU-ADMIN-999',
            ])
            ->assertSessionHas('error');

        $this->assertSame('NU-ADMIN-001', $admin->fresh()->institutional_id);
    }

    public function test_detail_page_shows_role_aware_administrative_editor(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@nu-clark.edu.ph', '2026-000001');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000001',
            'program' => AcademicProgram::query()->firstOrFail()->name,
            'year_level' => '1st Year',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Edit Details')
            ->assertSee('Student Number')
            ->assertSee('Academic Program')
            ->assertSee('Department')
            ->assertSee('Year Level')
            ->assertDontSee('<i class="bi bi-check-lg"></i> Save Changes', false)
            ->assertDontSee('name="contact_number"', false);
    }

    public function test_student_profile_card_shows_full_program_name_and_department(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $student = $this->user('student', 'student@nu-clark.edu.ph', '2026-000001');
        $program = AcademicProgram::query()
            ->with('academicUnit')
            ->where('name', 'BS Information Technology')
            ->firstOrFail();

        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-000001',
            'program' => 'BSIT',
            'year_level' => '1st Year',
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'create',
            'module' => 'users',
            'record_id' => $student->id,
            'new_values' => [
                'name' => $student->full_name,
                'role' => 'student',
                'institutional_id' => '2026-000001',
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('account-more-actions-danger')
            ->assertSee($student->created_at->format('F d, Y \a\t g:i A'))
            ->assertSeeInOrder([
                'Student Profile',
                'Program',
                'BS Information Technology',
                'Department',
                $program->academicUnit->name,
                'Year Level',
                'Recent Account Activity',
                'An account has been created.',
                'Performed by '.$admin->list_name,
                'View details',
                'Student ID',
            ]);
    }

    private function user(string $role, string $email, ?string $institutionalId = null): User
    {
        return User::create([
            'last_name' => 'User',
            'first_name' => 'Test',
            'institutional_id' => $institutionalId,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
