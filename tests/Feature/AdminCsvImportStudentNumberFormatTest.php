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

/**
 * A stale template still carrying its "# ..." example lines used to import as real data
 * once the "#" prefix stopped being treated as a comment: student_number came through as
 * "#2020-0000001" and every other field was well-formed, so nothing rejected it.
 */
class AdminCsvImportStudentNumberFormatTest extends TestCase
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

    private function upload(User $admin, string $content)
    {
        return $this->actingAs($admin)->post(route('admin.import.store'), [
            'csv_file' => UploadedFile::fake()->createWithContent('students.csv', $content),
        ]);
    }

    public function test_a_leading_hash_from_a_stale_template_is_rejected_not_imported(): void
    {
        $admin = $this->admin();

        $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
            .'#2020-0000001,Student,Demo,demo-user-001@students.nu-clark.edu.ph,Santos'."\r\n";

        $this->upload($admin, $content)->assertRedirect();

        $log = CsvImportLog::first();
        $this->assertSame(0, $log->created_count);
        $this->assertSame(1, $log->error_count);
        $this->assertStringContainsString('Student number must look like', $log->results_json[0]['reason']);
        $this->assertDatabaseMissing('users', ['email' => 'demo-user-001@students.nu-clark.edu.ph']);
    }

    public static function malformedStudentNumbers(): array
    {
        // Deliberately nothing here about digit count - the sequence length has already
        // grown once (6 digits to 7) and pinning a width would only break again next time
        // it does. These are shape problems: not a year, not a dash, or not all digits.
        return [
            'leading hash' => ['#2021-0000123'],
            'no dash' => ['20210000123'],
            'letters' => ['2021-ABCDEF'],
            'two-digit year' => ['21-0000123'],
            'trailing junk' => ['2021-0000123x'],
        ];
    }

    public function test_malformed_student_numbers_are_all_rejected(): void
    {
        $admin = $this->admin();

        foreach (self::malformedStudentNumbers() as $label => [$number]) {
            CsvImportLog::query()->delete();
            User::where('role', 'student')->delete();

            $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
                ."{$number},Student,Demo,demo-user-001@students.nu-clark.edu.ph,\r\n";

            $this->upload($admin, $content);

            $this->assertSame(
                0,
                CsvImportLog::first()->created_count,
                "\"{$number}\" ({$label}) was accepted but should not have been.",
            );
        }
    }

    public function test_real_student_number_formats_still_import(): void
    {
        $admin = $this->admin();

        // Both existing widths (represented by the accepted format), plus one nobody has used
        // yet - the rule must not need editing again the next time the sequence grows.
        foreach ([
            '2099-0000002' => 'a',
            '2099-0000006' => 'b',
            '2099-00000061' => 'c',
        ] as $number => $suffix) {
            $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
                ."{$number},Student,Demo{$suffix},demo-user-{$suffix}@students.nu-clark.edu.ph,\r\n";

            $this->upload($admin, $content);

            $this->assertDatabaseHas('student_profiles', ['student_number' => $number]);
        }
    }

    public function test_import_results_display_students_surname_first_with_middle_initial(): void
    {
        $admin = $this->admin();
        $content = implode(',', StudentImportService::CSV_HEADERS)."\r\n"
            .'2099-0000007,Student,Demo,demo-user-003@students.nu-clark.edu.ph,Sample'."\r\n";

        $this->upload($admin, $content)->assertRedirect();

        $log = CsvImportLog::firstOrFail();

        $this->assertSame('Student, Demo S.', $log->results_json[0]['name']);

        $this->actingAs($admin)
            ->get(route('admin.import.show', $log->import_batch_id))
            ->assertOk()
            ->assertSee('Student, Demo S.')
            ->assertDontSee('Demo Sample Student');
    }
}
