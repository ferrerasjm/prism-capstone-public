<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CsvImportLog;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCsvImportEmptyFileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function upload(User $admin, string $content, string $name = 'students.csv')
    {
        return $this->actingAs($admin)->post(route('admin.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
    }

    public static function emptyFiles(): array
    {
        return [
            'headers only' => [implode(',', StudentImportService::CSV_HEADERS)."\r\n"],
            'completely empty' => [''],
            'headers with blank lines' => [implode(',', StudentImportService::CSV_HEADERS)."\r\n\r\n\r\n"],
        ];
    }

    public function test_an_empty_csv_is_rejected_without_touching_the_history(): void
    {
        $admin = $this->admin();

        foreach (self::emptyFiles() as $label => [$content]) {
            $response = $this->upload($admin, $content);

            // Back to the import page with a toast, not forward to an empty results page.
            $response->assertRedirect(route('admin.import.index'));
            $response->assertSessionHas('error');

            $this->assertSame(
                0,
                CsvImportLog::count(),
                "An empty CSV ({$label}) still created a history entry.",
            );
        }
    }

    public function test_an_empty_csv_leaves_no_audit_trail_either(): void
    {
        $admin = $this->admin();

        $this->upload($admin, implode(',', StudentImportService::CSV_HEADERS)."\r\n");

        $this->assertDatabaseMissing('audit_logs', [
            'module' => 'users',
            'action' => 'import',
        ]);
    }

    public function test_a_file_with_real_rows_still_imports_and_is_recorded(): void
    {
        $admin = $this->admin();

        $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
            ."2021-0000123,Bautista,Andres,bautistaa@students.nu-clark.edu.ph,Reyes\r\n";

        $response = $this->upload($admin, $content);

        // Still goes forward to the results page for that batch.
        $response->assertRedirect();
        $this->assertSame(1, CsvImportLog::count());
        $this->assertDatabaseHas('users', ['email' => 'bautistaa@students.nu-clark.edu.ph']);
    }

    public function test_a_file_whose_rows_all_fail_is_still_recorded(): void
    {
        $admin = $this->admin();

        // Rows that are present but invalid are a real import worth reviewing - only a
        // file with no rows at all is turned away.
        $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
            .",Bautista,Andres,wrong-domain@gmail.com,\r\n";

        $this->upload($admin, $content)->assertRedirect();

        $this->assertSame(1, CsvImportLog::count());
        $this->assertSame(1, CsvImportLog::first()->error_count);
    }
}
