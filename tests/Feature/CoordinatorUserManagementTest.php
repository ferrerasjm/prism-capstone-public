<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CoordinatorUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_is_exclusive_to_super_admins(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@nu-clark.edu.ph');
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');

        $this->assertFalse(Route::has('coordinator.users.index'));

        $this->actingAs($coordinator)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Account',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
