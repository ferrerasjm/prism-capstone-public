<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CsvImportLog;
use App\Models\User;
use App\Notifications\AccountSetupLinkNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StudentBulkActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_requires_official_emails_and_queues_activation_links(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $csv = implode("\n", [
            'student_number,last_name,first_name,email,middle_name',
            '2026-000001,Dela Cruz,Juan,delacruzj@students.nu-clark.edu.ph,',
            '2026-000002,Santos,Maria,santosm@students.nu-clark.edu.ph,Reyes',
            '2026-000003,Invalid,Email,invalid@example.com,',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $log = CsvImportLog::query()->firstOrFail();
        $response->assertRedirect(route('admin.import.show', $log->import_batch_id));

        $first = User::where('email', 'delacruzj@students.nu-clark.edu.ph')->firstOrFail();
        $second = User::where('email', 'santosm@students.nu-clark.edu.ph')->firstOrFail();

        $this->assertSame(2, $log->created_count);
        $this->assertSame(1, $log->error_count);
        $this->assertSame('queued', $log->results_json[0]['activation_email']);
        $this->assertSame('queued', $log->results_json[1]['activation_email']);
        $this->assertSame('not_sent', $log->results_json[2]['activation_email']);
        $this->assertFalse(Hash::check('2026-000001', $first->password));
        $this->assertFalse(Hash::check('2026-000002', $second->password));
        $this->assertNotNull($first->accountSetupToken);
        $this->assertNotNull($second->accountSetupToken);

        Notification::assertSentTo($first, AccountSetupLinkNotification::class);
        Notification::assertSentTo($second, AccountSetupLinkNotification::class);
        Notification::assertCount(2);
    }

    public function test_bulk_import_rejects_rows_without_an_email_column_value(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $csv = implode("\n", [
            'student_number,last_name,first_name,middle_name',
            '2026-000004,Missing,Email,',
        ]);

        $this->actingAs($admin)->post(route('admin.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ]);

        $log = CsvImportLog::query()->firstOrFail();
        $this->assertSame(0, $log->created_count);
        $this->assertSame(1, $log->error_count);
        $this->assertSame(
            'Row 2: Official student email is required.',
            $log->results_json[0]['reason'],
        );
        Notification::assertNothingSent();
    }

    private function admin(): User
    {
        return User::create([
            'last_name' => 'Admin',
            'first_name' => 'Super',
            'email' => 'bulk.demo-user-001@nu-clark.edu.ph',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
