<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_update_contact_and_profile_photo(): void
    {
        Storage::fake('public');

        $supervisor = User::create([
            'last_name' => 'Bautista',
            'first_name' => 'Juan Paolo',
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'institutional_id' => 'US-SUP-001',
            'password' => Hash::make('FixtureOnly@2026'),
            'role' => 'us_supervisor',
            'status' => 'active',
            'office_name' => 'Library',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'demo-user-006@nu-clark.edu.ph',
            'password' => 'FixtureOnly@2026',
        ]);

        $token = $login->json('access_token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('contact_number', null)
            ->assertJsonPath('avatar_url', null);

        $photo = UploadedFile::fake()->image('profile.jpg');

        $this->withToken($token)
            ->patch('/api/auth/me', [
                'contact_number' => '09171234567',
                'profile_photo' => $photo,
            ])
            ->assertOk()
            ->assertJsonPath('contact_number', '09171234567')
            ->assertJsonStructure(['avatar_url']);

        $supervisor->refresh();

        $this->assertSame('09171234567', $supervisor->contact_number);
        $this->assertNotNull($supervisor->profile_photo);
        Storage::disk('public')->assertExists($supervisor->profile_photo);
    }
}
