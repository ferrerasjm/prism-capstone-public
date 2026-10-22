<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_out_confirmation_uses_standard_iconless_modal_buttons(): void
    {
        $source = file_get_contents(resource_path('views/partials/sign-out-confirmation.blade.php'));

        $this->assertStringContainsString('class="prism-btn prism-btn-outline"', $source);
        $this->assertStringContainsString('class="prism-btn prism-btn-danger"', $source);
        $this->assertStringNotContainsString('<i ', $source);
    }

    public function test_every_supported_role_can_access_the_avatar_upload(): void
    {
        $roles = [
            'super_admin',
            'discipline_coordinator',
            'discipline_officer',
            'reporting_user',
            'guidance_officer',
            'us_supervisor',
            'student',
        ];

        foreach ($roles as $index => $role) {
            $user = $this->user($role, "profile-photo-{$index}@nu-clark.edu.ph");

            $this->actingAs($user)
                ->get(route('dashboard.profile'))
                ->assertOk()
                ->assertSee('enctype="multipart/form-data"', false)
                ->assertSee('name="profile_photo"', false)
                ->assertSee('Crop Profile Photo')
                ->assertSee('id="profileCropCanvas"', false)
                ->assertSee('id="profileCropZoom"', false)
                ->assertSee('Apply Crop')
                ->assertSee('National University Clark')
                ->assertSee('Maximum 2 MB')
                ->assertSeeInOrder(['View Profile', 'Change Password', 'Sign Out'])
                ->assertSee(route('account.password'), false)
                ->assertSee('id="signOutConfirmationModal"', false)
                ->assertSee('Are you sure you want to sign out of your PRISM account?')
                ->assertSee('id="confirmSignOutButton"', false)
                ->assertDontSee('name="current_password"', false)
                ->assertDontSee('account-password-card', false)
                ->assertSee('max-width: 1080px', false)
                ->assertSee('profile-signout-action', false);
        }
    }

    public function test_discipline_officer_profile_displays_the_assigned_department(): void
    {
        $department = AcademicUnit::query()->firstOrCreate(
            ['name' => 'School of Engineering'],
            ['is_active' => true],
        );
        $officer = $this->user('discipline_officer', 'department.officer@nu-clark.edu.ph');

        // Officers can hold several departments, so assignments live in the pivot;
        // academic_unit_id is the legacy single-department column kept alongside it.
        $officer->update(['academic_unit_id' => $department->id]);
        $officer->academicUnits()->sync([$department->id]);

        $this->actingAs($officer)
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('Assigned Academic Department')
            // The profile shows the abbreviated unit name, not the full one.
            ->assertSee(AcademicUnit::abbreviatedName($department->name))
            ->assertSee('Your student and case access is limited to these assignments.');
    }

    public function test_user_can_upload_and_replace_a_profile_photo(): void
    {
        Storage::fake('public');
        $officer = $this->user('discipline_officer', 'avatar.officer@nu-clark.edu.ph');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->patch(route('dashboard.profile.update'), [
            'first_name' => $officer->first_name,
            'last_name' => $officer->last_name,
            'middle_name' => null,
            'email' => $officer->email,
            'profile_photo' => UploadedFile::fake()->image('first-avatar.jpg', 400, 400)->size(512),
        ])->assertSessionHasNoErrors();

        $firstPhoto = $officer->fresh()->profile_photo;
        $this->assertNotNull($firstPhoto);
        Storage::disk('public')->assertExists($firstPhoto);

        $this->actingAs($officer)->patch(route('dashboard.profile.update'), [
            'first_name' => $officer->first_name,
            'last_name' => $officer->last_name,
            'middle_name' => null,
            'email' => $officer->email,
            'profile_photo' => UploadedFile::fake()->image('replacement-avatar.png', 500, 500)->size(700),
        ])->assertSessionHasNoErrors();

        $replacementPhoto = $officer->fresh()->profile_photo;
        $this->assertNotSame($firstPhoto, $replacementPhoto);
        Storage::disk('public')->assertExists($replacementPhoto);
        Storage::disk('public')->assertMissing($firstPhoto);
    }

    public function test_profile_displays_an_existing_photo_and_falls_back_when_it_is_missing(): void
    {
        Storage::fake('public');
        $officer = $this->user('discipline_officer', 'avatar.display@nu-clark.edu.ph');
        $photoPath = 'profile-photos/'.$officer->id.'/avatar.jpg';
        Storage::disk('public')->put($photoPath, 'image contents');
        $officer->update(['profile_photo' => $photoPath]);

        $this->actingAs($officer)
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee(asset('storage/'.$photoPath), false)
            ->assertSee('hidden >PU</span>', false);

        Storage::disk('public')->delete($photoPath);

        $this->actingAs($officer)
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('id="profilePhotoPreview"', false)
            ->assertSee('id="profilePhotoInitials"', false)
            ->assertDontSee('hidden >PU</span>', false);
    }

    public function test_profile_success_message_uses_the_shared_toast_only(): void
    {
        $officer = $this->user('discipline_officer', 'profile.toast@nu-clark.edu.ph');

        $this->actingAs($officer)
            ->withSession(['success' => 'Profile updated successfully.'])
            ->get(route('dashboard.profile'))
            ->assertOk()
            ->assertSee('prism-toast prism-toast-success', false)
            ->assertSeeInOrder(['toast-body', 'Profile updated successfully.'])
            ->assertDontSee('prism-alert prism-alert-success', false);
    }

    public function test_profile_photo_must_be_a_supported_image_within_two_megabytes(): void
    {
        Storage::fake('public');
        $officer = $this->user('discipline_officer', 'avatar.validation@nu-clark.edu.ph');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($officer)->patch(route('dashboard.profile.update'), [
            'first_name' => $officer->first_name,
            'last_name' => $officer->last_name,
            'middle_name' => null,
            'email' => $officer->email,
            'profile_photo' => UploadedFile::fake()->image('oversized.jpg')->size(2049),
        ])->assertSessionHasErrors('profile_photo');

        $this->actingAs($officer)->patch(route('dashboard.profile.update'), [
            'first_name' => $officer->first_name,
            'last_name' => $officer->last_name,
            'middle_name' => null,
            'email' => $officer->email,
            'profile_photo' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ])->assertSessionHasErrors('profile_photo');

        $this->assertNull($officer->fresh()->profile_photo);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_student_can_update_contact_fields_but_not_institutional_identity(): void
    {
        $student = $this->user('student', 'student@nu-clark.edu.ph');
        StudentProfile::create([
            'user_id' => $student->id,
            'student_number' => '2026-00001',
            'program' => 'BSIT',
            'year_level' => '4',
            'contact_number' => '09170000000',
            'guardian_name' => 'Old Guardian',
            'guardian_contact' => '09171111111',
            'guardian_relationship' => 'Guardian',
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($student)->patch(route('dashboard.profile.update'), [
            'middle_name' => 'Updated',
            'email' => 'updated.student@nu-clark.edu.ph',
            'contact_number' => '09172222222',
            'guardian_name' => 'New Guardian',
            'guardian_contact' => '09173333333',
            'guardian_relationship' => 'Mother',
            'role' => 'super_admin',
            'status' => 'inactive',
            'student_number' => 'HACKED',
            'program' => 'Changed Program',
        ])->assertSessionHasNoErrors();

        $fresh = $student->fresh();
        $profile = $fresh->studentProfile;
        $this->assertSame('student', $fresh->role);
        $this->assertSame('active', $fresh->status);
        $this->assertSame('2026-00001', $profile->student_number);
        $this->assertSame('BSIT', $profile->program);
        $this->assertSame('09172222222', $profile->contact_number);
        // A student's institutional address is part of their identity, so
        // AccountProfileController::update() never applies email for students.
        $this->assertSame('student@nu-clark.edu.ph', $fresh->email);
        $this->assertDatabaseHas('audit_logs', ['module' => 'account_profiles', 'record_id' => $student->id]);
    }

    public function test_staff_can_update_name_but_not_email_role_or_office_assignment(): void
    {
        $supervisor = $this->user('us_supervisor', 'supervisor@nu-clark.edu.ph', 'Library');
        $supervisor->update(['institutional_id' => 'NU-US-001']);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($supervisor)->patch(route('dashboard.profile.update'), [
            'first_name' => 'Updated',
            'last_name' => 'Supervisor',
            'middle_name' => null,
            'email' => 'updated.supervisor@nu-clark.edu.ph',
            'role' => 'discipline_coordinator',
            'office_name' => 'Student Discipline Office',
            'institutional_id' => 'HACKED-ID',
        ])->assertSessionHasNoErrors();

        $fresh = $supervisor->fresh();
        $this->assertSame('Updated', $fresh->first_name);
        $this->assertSame('us_supervisor', $fresh->role);
        $this->assertSame('Library', $fresh->office_name);
        $this->assertSame('NU-US-001', $fresh->institutional_id);
        // Only an administrator can change a user's official email; self-service ignores it.
        $this->assertSame('supervisor@nu-clark.edu.ph', $fresh->email);
    }

    private function user(string $role, string $email, ?string $office = null): User
    {
        return User::create([
            'last_name' => 'User',
            'first_name' => 'Profile',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'office_name' => $office,
            'status' => 'active',
        ]);
    }
}
