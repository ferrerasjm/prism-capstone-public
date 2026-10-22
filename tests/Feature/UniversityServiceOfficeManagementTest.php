<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\UniversityServiceOffice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class UniversityServiceOfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_discipline_officer_can_add_archive_and_restore_an_office(): void
    {
        $officer = $this->user('discipline_officer', 'officer@nu-clark.edu.ph');

        $this->actingAs($officer)
            ->post(route('officer.us-offices.store'), [
                'name' => '  Community   Extension Office ',
            ])
            ->assertSessionHas('success');

        $office = UniversityServiceOffice::query()
            ->where('name', 'Community Extension Office')
            ->firstOrFail();

        $creationAudit = AuditLog::query()
            ->where('module', 'university_service_offices')
            ->where('record_id', $office->id)
            ->where('action', 'create')
            ->firstOrFail();

        $this->assertTrue($creationAudit->new_values['is_active']);

        $this->actingAs($officer)
            ->patch(route('officer.us-offices.toggle-status', $office), [
                'status_target_id' => $office->id,
            ])
            ->assertSessionHasErrors('archive_reason');
        $this->assertTrue($office->fresh()->is_active);

        $this->actingAs($officer)
            ->patch(route('officer.us-offices.toggle-status', $office), [
                'status_target_id' => $office->id,
                'archive_reason' => 'The office is no longer accepting University Service assignments.',
            ])
            ->assertSessionHas('success');
        $this->assertFalse($office->fresh()->is_active);

        $this->actingAs($officer)
            ->patch(route('officer.us-offices.toggle-status', $office))
            ->assertSessionHas('success');
        $this->assertTrue($office->fresh()->is_active);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'university_service_offices',
            'record_id' => $office->id,
            'reason' => 'The office is no longer accepting University Service assignments.',
        ]);
    }

    public function test_other_roles_cannot_manage_university_service_offices(): void
    {
        $coordinator = $this->user('discipline_coordinator', 'coordinator@nu-clark.edu.ph');

        $this->actingAs($coordinator)
            ->get(route('officer.us-offices.index'))
            ->assertForbidden();
        $this->actingAs($coordinator)
            ->post(route('officer.us-offices.store'), ['name' => 'Unauthorized Office'])
            ->assertForbidden();
    }

    public function test_university_service_offices_are_accessed_from_sanctions(): void
    {
        $officer = $this->user('discipline_officer', 'officer@nu-clark.edu.ph');
        $this->actingAs($officer);

        $html = view('officer.sanctions.index', [
            'sanctions' => new LengthAwarePaginator([], 0, 20),
            'stats' => ['pending' => 0, 'in_progress' => 0, 'completed' => 0, 'overdue' => 0],
            'type' => null,
            'filters' => [],
            'assignedDepartments' => collect(),
            'typeLabels' => [],
            'guidanceOfficers' => collect(),
            'activeNav' => 'sanctions',
            'errors' => new ViewErrorBag,
        ])->render();

        $this->assertStringContainsString('University Service Offices', $html);
        $this->assertSame(1, substr_count($html, route('officer.us-offices.index')));
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('officer.us-offices.index'), '/').'".*class="prism-btn prism-btn-blue-outline"/',
            $html,
        );

        $this->get(route('officer.us-offices.index'))
            ->assertOk()
            ->assertViewHas('activeNav', 'sanctions')
            ->assertSee('Sanctions')
            ->assertSee('University Service Offices')
            ->assertSee('<span class="visually-hidden">Actions</span>', false)
            ->assertSee('us-office-body')
            ->assertSee('office-action-column')
            ->assertSee('officeEditModal')
            ->assertSee('Save Changes')
            ->assertDontSee('<i class="bi bi-check-lg"></i> Save Changes', false)
            ->assertSee('officeStatusModal')
            ->assertSee('prism-modal')
            ->assertDontSee('modal-content sdo-modal', false)
            ->assertSee('Archive Reason')
            ->assertDontSee('return confirm', false);
    }

    public function test_duplicate_office_names_are_rejected(): void
    {
        $officer = $this->user('discipline_officer', 'officer@nu-clark.edu.ph');
        $existing = UniversityServiceOffice::query()->firstOrFail();

        $this->actingAs($officer)
            ->post(route('officer.us-offices.store'), ['name' => $existing->name])
            ->assertSessionHasErrors('name');

        $errors = (new ViewErrorBag)->put('default', new MessageBag([
            'name' => ['The name has already been taken.'],
        ]));
        $html = view('officer.us-offices.index', [
            'offices' => UniversityServiceOffice::query()->orderBy('name')->get(),
            'activeNav' => 'sanctions',
            'errors' => $errors,
            // The sortable column headers read these; the controller always
            // supplies them, so this direct render must too.
            'sort' => 'name',
            'direction' => 'asc',
        ])->render();

        $this->assertStringContainsString('prism-toast prism-toast-danger', $html);
        $this->assertStringContainsString('data-us-office-validation-toast', $html);
        $this->assertStringNotContainsString(
            '<div class="text-danger small mt-1">The name has already been taken.</div>',
            $html,
        );
        $this->assertSame(1, substr_count($html, 'The name has already been taken.'));
    }

    public function test_discipline_officer_can_edit_an_office_name(): void
    {
        $officer = $this->user('discipline_officer', 'officer@nu-clark.edu.ph');
        $office = UniversityServiceOffice::query()->firstOrFail();
        $oldName = $office->name;

        $this->actingAs($officer)
            ->patch(route('officer.us-offices.update', $office), [
                'edit_target_id' => $office->id,
                'edit_name' => '  Center for   Community Engagement ',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Center for Community Engagement', $office->fresh()->name);
        $this->assertDatabaseHas('audit_logs', [
            'module' => 'university_service_offices',
            'record_id' => $office->id,
            'old_values' => json_encode([
                'name' => $oldName,
                'is_active' => true,
            ]),
        ]);
    }

    public function test_archived_offices_are_hidden_from_new_supervisor_accounts(): void
    {
        $admin = $this->user('super_admin', 'demo-user-001@nu-clark.edu.ph');
        $office = UniversityServiceOffice::query()->firstOrFail();
        $office->update(['is_active' => false]);

        // The create-account form is a modal on the user list, so that is where the options render.
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('<option value="'.$office->name.'"', false);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => 'User',
            'first_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
