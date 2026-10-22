<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('incident_report_id')->nullable()->constrained('incident_reports')->nullOnDelete();
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->enum('file_type', ['pdf', 'jpg', 'png']);
            $table->integer('file_size_bytes')->unsigned();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_attachments');
    }
};
