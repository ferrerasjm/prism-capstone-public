<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_handbooks', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('edition', 100)->unique();
            $table->string('academic_year', 20)->nullable();
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'effective_date']);
        });

        Schema::table('case_notices', function (Blueprint $table): void {
            $table->foreignId('student_handbook_id')
                ->nullable()
                ->after('issued_at')
                ->constrained('student_handbooks')
                ->nullOnDelete();
        });

        $this->importLegacyHandbook();
    }

    public function down(): void
    {
        Schema::table('case_notices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('student_handbook_id');
        });

        Schema::dropIfExists('student_handbooks');
    }

    private function importLegacyHandbook(): void
    {
        $source = database_path('seeders/assets/NU-Clark-Student-Handbook-2025.pdf');

        if (! is_file($source)) {
            return;
        }

        $path = 'student-handbooks/nu-clark-student-handbook-2025.pdf';
        $contents = file_get_contents($source);

        if ($contents === false || ! Storage::disk('local')->put($path, $contents)) {
            return;
        }

        $timestamp = now();

        DB::table('student_handbooks')->insert([
            'title' => 'NU Clark Student Handbook',
            'edition' => '2025 Edition',
            'academic_year' => '2025',
            'effective_date' => null,
            'notes' => 'Initial handbook edition migrated from the student dashboard.',
            'file_path' => $path,
            'original_filename' => 'NU-Clark-Student-Handbook-2025.pdf',
            'file_size' => filesize($source),
            'checksum_sha256' => hash_file('sha256', $source),
            'status' => 'published',
            'uploaded_by' => null,
            'published_by' => null,
            'published_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
};
