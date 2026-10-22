<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The create-user modal submits via fetch with Accept: application/json, so a rejected
 * submission can be redrawn in place instead of reloading the whole page. This pins the
 * contract that JS relies on: Laravel's default exception handling turns a thrown
 * ValidationException into a 422 JSON body whenever the request asks for JSON - no
 * controller change was needed, but that's exactly the kind of thing that breaks silently
 * if something upstream ever starts intercepting the response differently.
 */
class CreateUserAjaxValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'last_name' => 'Admin',
            'first_name' => 'System',
            'email' => 'demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    public function test_a_rejected_submission_returns_422_json_instead_of_a_redirect(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'form' => 'create-user',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'institutional_id' => '',
                'email' => 'not-an-official-domain@gmail.com',
                'role' => 'discipline_officer',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors'])
            ->assertJsonValidationErrors(['institutional_id', 'email', 'academic_unit_ids']);

        // The JSON path ignores the named error bag entirely - it isn't a redirect, so
        // there's no session flash for a bag to live in.
        $data = $response->json();
        $this->assertIsArray($data['errors']['email']);
        $this->assertStringContainsString('official', $data['errors']['email'][0]);
    }

    public function test_a_successful_submission_still_redirects_normally(): void
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.users.store'), [
                'form' => 'create-user',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'institutional_id' => '2023-99999',
                'email' => 'jdelacruz@nu-clark.edu.ph',
                'role' => 'discipline_coordinator',
            ]);

        // Success takes the same RedirectResponse path regardless of the Accept header -
        // the JSON negotiation only kicks in for the thrown ValidationException.
        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', ['email' => 'jdelacruz@nu-clark.edu.ph']);
    }

    public function test_the_plain_form_post_still_works_without_javascript(): void
    {
        $response = $this->actingAs($this->admin())
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'form' => 'create-user',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'institutional_id' => '',
                'email' => 'not-an-official-domain@gmail.com',
                'role' => 'discipline_officer',
            ]);

        // No Accept: application/json header this time - the non-JS fallback path.
        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors(['institutional_id', 'email', 'academic_unit_ids'], null, 'createUser');
    }
}
