<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminCsvImportTemplateTest extends TestCase
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

    public function test_super_admin_can_download_the_blank_student_import_template(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.import.index'))
            ->assertOk()
            ->assertSee('Download Blank CSV')
            ->assertSee(route('admin.import.template'));

        $this->get(route('admin.import.template'))
            ->assertOk()
            ->assertDownload('student-bulk-import-template.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertContent(implode(',', StudentImportService::CSV_HEADERS)."\r\n");
    }

    public function test_the_template_is_headers_only_so_nothing_can_be_imported_by_accident(): void
    {
        $content = $this->actingAs($this->admin())
            ->get(route('admin.import.template'))
            ->getContent();

        $rows = array_filter(explode("\r\n", $content), fn ($line) => trim($line) !== '');

        $this->assertCount(1, $rows, 'The template should carry the header row and nothing else.');
    }

    public function test_the_download_link_is_marked_so_the_progress_bar_does_not_hang(): void
    {
        // A download does not navigate, so pagehide never fires; initPageLoader skips
        // links carrying a download attribute rather than starting a bar nothing stops.
        $this->actingAs($this->admin())
            ->get(route('admin.import.index'))
            ->assertOk()
            ->assertSee('href="'.route('admin.import.template').'" download', false);
    }
}
