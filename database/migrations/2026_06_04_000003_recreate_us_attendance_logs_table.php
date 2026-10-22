<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep legacy scan records in place while adding the columns used by
        // the current session-based attendance workflow.
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->foreignId('student_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->date('date_of_service');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_rendered', 5, 2)->nullable();
            $table->text('tasks_performed')->nullable();
            $table->enum('log_method', [
                'qr_scan',
                'manual_web',
                'no_show',
            ])->default('manual_web');
            $table->foreignId('logged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['logged_by']);
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'student_id',
                'date_of_service',
                'time_in',
                'time_out',
                'hours_rendered',
                'tasks_performed',
                'log_method',
                'logged_by',
                'verified_by',
                'verified_at',
                'notes',
                'updated_at',
            ]);
        });
    }
};
