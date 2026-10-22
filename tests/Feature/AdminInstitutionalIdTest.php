<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicUnit;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminInstitutionalIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_id_is_required_stored_and_visible_to_super_admin(): void
    {
        Notification::fake();
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $academicUnitId = AcademicUnit::query()->where('is_active', true)->value('id');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'last_name' => 'Officer',
                'first_name' => 'Casey',
                'institutional_id' => ' nu-sdo-1001 ',
                'email' => 'casey.officer@nu-clark.edu.ph',
                'role' => 'discipline_officer',
                'academic_unit_ids' => [$academicUnitId],
            ])
            ->assertRedirect(route('admin.users.index'));

        $staff = User::query()->where('email', 'casey.officer@nu-clark.edu.ph')->firstOrFail();
        $this->assertSame('NU-SDO-1001', $staff->institutional_id);
        Notification::assertSentTo($staff, AccountSetupLinkNotification::class);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'NU-SDO-1001']))
            ->assertOk()
            ->assertSee('NU-SDO-1001');

        $this->actingAs($admin)
            ->get(route('admin.users.show', $staff))
            ->assertOk()
            ->assertSee('University ID')
            ->assertSee('NU-SDO-1001');

        $this->actingAs($admin)
            ->patch(route('admin.users.administrative-details.update', $staff), [
                'institutional_id' => 'NU-SDO-1002',
                'email' => $staff->email,
                'academic_unit_ids' => [$academicUnitId],
            ])
            ->assertSessionHas('success');

        $this->assertSame('NU-SDO-1002', $staff->fresh()->institutional_id);
    }

    public function test_staff_ids_must_be_unique(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $this->user('discipline_officer', 'existing@nu-clark.edu.ph', 'NU-SDO-1001');

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'last_name' => 'Officer',
                'first_name' => 'Duplicate',
                'institutional_id' => 'NU-SDO-1001',
                'email' => 'duplicate@nu-clark.edu.ph',
                'role' => 'discipline_officer',
            ])
            ->assertSessionHasErrors('institutional_id', null, 'createUser');
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
