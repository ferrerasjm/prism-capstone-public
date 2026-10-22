<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_administrator_is_created_without_private_credentials(): void
    {
        config(['auth.bootstrap_admin.email' => null, 'auth.bootstrap_admin.password' => null]);

        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_configured_administrator_is_created_without_resetting_an_existing_password(): void
    {
        config([
            'auth.bootstrap_admin.email' => 'bootstrap-admin@example.test',
            'auth.bootstrap_admin.password' => 'Bootstrap-Fixture-Only-123',
        ]);

        $this->seed(AdminUserSeeder::class);
        $admin = User::where('email', 'bootstrap-admin@example.test')->firstOrFail();
        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue(Hash::check('Bootstrap-Fixture-Only-123', $admin->password));

        config(['auth.bootstrap_admin.password' => 'Different-Fixture-Only-456']);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($admin->password, $admin->fresh()->password);
    }

    public function test_short_bootstrap_password_is_rejected(): void
    {
        config([
            'auth.bootstrap_admin.email' => 'bootstrap-admin@example.test',
            'auth.bootstrap_admin.password' => 'short',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->seed(AdminUserSeeder::class);
    }
}
