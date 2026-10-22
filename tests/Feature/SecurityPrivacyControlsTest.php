<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IncidentReport;
use App\Models\User;
use App\Services\IncidentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityPrivacyControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_attachments_store_hash_and_scan_metadata(): void
    {
        Storage::fake('local');
        Config::set('prism_security.attachments.scan_uploads', false);

        $uploader = $this->user('reporting_user', 'reporter@example.test');
        $report = IncidentReport::create([
            'report_number' => 'IR-2026-SECURITY',
            'source_type' => 'faculty_report',
            'reported_by_user_id' => $uploader->id,
            'date_of_incident' => today(),
            'description' => 'Security metadata attachment test report.',
            'status' => 'pending_review',
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'evidence.pdf',
            'controlled security metadata test file'
        );

        $attachment = (new IncidentReportService)->storeAttachment($file, $report, $uploader->id);

        Storage::disk('local')->assertExists($attachment->file_path);
        $this->assertSame(64, strlen((string) $attachment->file_hash));
        $this->assertSame('not_configured', $attachment->scan_status);
        $this->assertNull($attachment->scanned_at);
    }

    public function test_privacy_check_reports_retention_and_access_review_warnings(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        Config::set('app.key', 'base64:testing-key');
        Config::set('prism_security.retention.audit_log_years', 5);
        Config::set('prism_security.access_review.inactive_days', 90);

        $officer = $this->user('discipline_officer', 'inactive-officer@example.test');

        AuditLog::create([
            'user_id' => $officer->id,
            'action' => 'login',
            'module' => 'auth',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subYears(6),
        ]);

        $this->artisan('prism:privacy-check --fail-on-warning')
            ->expectsOutput('PRISM security and privacy check')
            ->expectsOutput('Audit logs older than retention window: 1')
            ->expectsOutput('Active staff accounts without login in 90 day(s): 1')
            ->expectsOutput('Warnings found:')
            ->assertExitCode(1);

        Carbon::setTestNow();
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'last_name' => ucfirst($role),
            'first_name' => 'Test',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
