<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\IncidentReport;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReporterIncidentSourceTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_reporting_user_submits_a_faculty_report(): void
    {
        $reporter = $this->reportingUser('Faculty / Academic Department');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($reporter)
            ->post(route('reporter.reports.store'), $this->validReportData())
            ->assertRedirect(route('reporter.reports.index'));

        $this->assertDatabaseHas('incident_reports', [
            'reported_by_user_id' => $reporter->id,
            'reported_by_name' => $reporter->full_name,
            'source_type' => 'faculty_report',
        ]);
    }

    public function test_security_reporting_user_submits_a_security_report(): void
    {
        $reporter = $this->reportingUser('Security Office');
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($reporter)
            ->post(route('reporter.reports.store'), $this->validReportData())
            ->assertRedirect(route('reporter.reports.index'));

        $this->assertDatabaseHas('incident_reports', [
            'reported_by_user_id' => $reporter->id,
            'reported_by_name' => $reporter->full_name,
            'source_type' => 'security_report',
        ]);

        $report = IncidentReport::firstOrFail();

        $this->actingAs($reporter)
            ->get(route('reporter.reports.show', $report))
            ->assertOk()
            ->assertSee('Security Report');
    }

    public function test_admin_can_create_a_security_reporting_user(): void
    {
        $admin = User::create([
            'last_name' => 'Admin',
            'first_name' => 'System',
            'email' => 'demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'last_name' => 'Dela Cruz',
                'first_name' => 'Ramon',
                'middle_name' => null,
                'institutional_id' => 'NU-SEC-001',
                'email' => 'security.reporter@nu-clark.edu.ph',
                'role' => 'reporting_user',
                'office_name' => 'Security Office',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'security.reporter@nu-clark.edu.ph',
            'role' => 'reporting_user',
            'office_name' => 'Security Office',
            'status' => 'pending_setup',
        ]);
    }

    public function test_reporting_user_create_page_displays_their_source_type(): void
    {
        $reporter = $this->reportingUser('Security Office');

        $this->actingAs($reporter)
            ->get(route('reporter.reports.create'))
            ->assertOk()
            ->assertSee('Security Report')
            ->assertSee('Security Office');
    }

    private function validReportData(): array
    {
        return [
            'date_of_incident' => now()->toDateString(),
            'time_of_incident' => '10:30',
            'location' => 'Main Lobby',
            'description' => 'A detailed incident report with enough information for SDO review.',
            'disclaimer' => '1',
        ];
    }

    private function reportingUser(string $officeName): User
    {
        return User::create([
            'last_name' => 'Reporter',
            'first_name' => 'Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'reporting_user',
            'office_name' => $officeName,
            'status' => 'active',
        ]);
    }
}
