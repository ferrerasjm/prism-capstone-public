<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\StudentHandbook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Publishes the bundled handbook edition.
 *
 * The create_student_handbooks_table migration imports this same file, but returns
 * silently when the PDF is absent. On installs whose migration ran before the asset
 * was committed, the table stayed empty and every notice was issued with a null
 * student_handbook_id. This seeder makes the import re-runnable and idempotent.
 */
class StudentHandbookSeeder extends Seeder
{
    private const EDITION = '2025 Edition';

    public function run(): void
    {
        $source = database_path('seeders/assets/NU-Clark-Student-Handbook-2025.pdf');

        if (! is_file($source)) {
            $this->command?->warn('Handbook PDF not found at '.$source.' — nothing seeded.');

            return;
        }

        $path = 'student-handbooks/nu-clark-student-handbook-2025.pdf';

        if (! Storage::disk('local')->exists($path)) {
            $contents = file_get_contents($source);

            if ($contents === false || ! Storage::disk('local')->put($path, $contents)) {
                $this->command?->error('Could not write the handbook to storage — nothing seeded.');

                return;
            }
        }

        $handbook = StudentHandbook::updateOrCreate(
            ['edition' => self::EDITION],
            [
                'title' => 'NU Clark Student Handbook',
                'academic_year' => '2025',
                // Null effective_date means it applies to every incident date, so
                // notices for backdated incidents still resolve to this edition.
                'effective_date' => null,
                'notes' => 'Initial handbook edition bundled with the application.',
                'file_path' => $path,
                'original_filename' => 'NU-Clark-Student-Handbook-2025.pdf',
                'file_size' => filesize($source),
                'checksum_sha256' => hash_file('sha256', $source),
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        $this->command?->info("Handbook published: {$handbook->title} ({$handbook->edition}), {$handbook->formattedFileSize()}.");

        $this->backfillNotices($handbook);
    }

    /**
     * Notices issued while the table was empty carry a null handbook reference.
     * Point each one at the edition that applied on its own incident date.
     */
    private function backfillNotices(StudentHandbook $fallback): void
    {
        $notices = DB::table('case_notices')
            ->whereNull('student_handbook_id')
            ->get(['id', 'incident_date']);

        if ($notices->isEmpty()) {
            $this->command?->line('No notices needed a handbook reference.');

            return;
        }

        foreach ($notices as $notice) {
            $applicable = StudentHandbook::applicableOn($notice->incident_date) ?? $fallback;

            DB::table('case_notices')
                ->where('id', $notice->id)
                ->update(['student_handbook_id' => $applicable->id]);
        }

        $this->command?->info('Backfilled '.$notices->count().' notice(s) with a handbook reference.');
    }
}
