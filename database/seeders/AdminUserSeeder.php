<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) config('auth.bootstrap_admin.email'));
        $password = (string) config('auth.bootstrap_admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('Administrator not seeded: set PRISM_ADMIN_EMAIL and PRISM_ADMIN_PASSWORD privately.');

            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new \InvalidArgumentException('Bootstrap administrator requires a valid email and a password of at least 12 characters.');
        }

        User::firstOrCreate(
            ['email' => $email],
            [
                'last_name' => 'Administrator',
                'first_name' => 'System',
                'middle_name' => null,
                'institutional_id' => null,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'office_name' => null,
                'status' => 'active',
                'created_by' => null,
            ],
        );
    }
}
