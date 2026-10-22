<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CoordinatorAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_filter_audit_records_and_secrets_are_redacted(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        AuditLog::create([
            'user_id' => $coordinator->id,
            'action' => 'update',
            'module' => 'cases',
            'record_id' => 42,
            'new_values' => ['status' => 'resolved', 'password' => 'never-display-this'],
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index', ['module' => 'cases', 'action' => 'update']))
            ->assertOk()
            ->assertSee('System Audit Log')
            ->assertSee('A case has been updated.')
            ->assertSee('Resolved')
            ->assertSee('[REDACTED]')
            ->assertSee('No previous values')
            ->assertDontSee('{&quot;status&quot;')
            ->assertDontSee('never-display-this');
    }

    public function test_non_coordinator_cannot_view_the_audit_log(): void
    {
        $this->actingAs($this->user('discipline_officer'))
            ->get(route('coordinator.audit-logs.index'))
            ->assertForbidden();
    }

    public function test_user_audit_summary_and_snapshot_use_readable_values(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        AuditLog::create([
            'user_id' => $coordinator->id,
            'action' => 'create',
            'module' => 'users',
            'record_id' => 42,
            'new_values' => [
                'name' => 'Farrah Andaya',
                'institutional_id' => '2026-000001',
                'email' => 'demo-user-018@nu-clark.edu.ph',
                'role' => 'student',
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index', ['module' => 'users']))
            ->assertOk()
            ->assertSee('An account has been created.')
            ->assertSee('Student ID')
            ->assertSee('Student')
            ->assertDontSee('Institutional ID')
            ->assertSee('No previous values')
            ->assertDontSee('changed from')
            ->assertDontSee('by '.$coordinator->full_name.'.');

        $this->assertSame('University ID', AuditLog::formatField('institutional_id', 'discipline_officer'));
    }

    public function test_university_service_office_actions_have_sentence_descriptions(): void
    {
        $added = new AuditLog([
            'action' => 'create',
            'module' => 'university_service_offices',
            'new_values' => ['name' => 'LRC', 'is_active' => true],
        ]);
        $updated = new AuditLog([
            'action' => 'update',
            'module' => 'university_service_offices',
            'old_values' => ['name' => 'LRC', 'is_active' => true],
            'new_values' => ['name' => 'Learning Resource Center', 'is_active' => true],
        ]);
        $removed = new AuditLog([
            'action' => 'update',
            'module' => 'university_service_offices',
            'old_values' => ['name' => 'LRC', 'is_active' => true],
            'new_values' => ['name' => 'LRC', 'is_active' => false],
        ]);

        $this->assertSame('A University Service Office has been added.', $added->summary());
        $this->assertSame('A University Service Office has been updated.', $updated->summary());
        $this->assertSame('A University Service Office has been removed.', $removed->summary());
    }

    public function test_events_without_a_related_record_do_not_show_not_linked_metadata(): void
    {
        $coordinator = $this->user('discipline_coordinator');
        AuditLog::create([
            'user_id' => $coordinator->id,
            'action' => 'login',
            'module' => 'auth',
            'record_id' => null,
            'created_at' => now(),
        ]);

        $this->actingAs($coordinator)
            ->get(route('coordinator.audit-logs.index', ['module' => 'auth']))
            ->assertOk()
            ->assertSee('A user has signed in.')
            ->assertDontSee('Record Not linked');
    }

    private function user(string $role): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Audit',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
