<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('us_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('us_assignment_id')
                ->nullable()
                ->constrained('us_assignments')
                ->nullOnDelete();
            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('event_type', ['time_in', 'time_out']);
            $table->dateTime('event_timestamp');
            $table->dateTime('server_timestamp');
            $table->decimal('hours_computed', 5, 2)->nullable();
            $table->boolean('is_manual_entry')->default(false);
            $table->boolean('is_synced')->default(false);
            $table->string('sync_batch_id', 64)->nullable();
            $table->enum('sync_status', ['pending', 'accepted', 'rejected'])
                ->default('pending');
            $table->string('sync_rejection_reason', 255)->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('us_attendance_logs');
    }
};
