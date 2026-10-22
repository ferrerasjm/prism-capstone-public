<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_import_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('import_batch_id', 64)->unique();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name', 255);
            $table->integer('total_rows');
            $table->integer('created_count');
            $table->integer('skipped_count');
            $table->integer('error_count');
            $table->json('results_json');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_import_logs');
    }
};
