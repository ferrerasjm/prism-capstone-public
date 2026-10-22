<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('us_assignments', function (Blueprint $table) {
            $table->dropForeign(['sanction_id']);
            $table->dropUnique(['sanction_id']);
            $table->dropColumn('sanction_id');

            $table->dropUnique(['totp_secret']);
        });

        Schema::table('us_assignments', function (Blueprint $table) {
            $table->foreignId('case_resolution_id')
                ->nullable()
                ->after('id')
                ->constrained('case_resolutions')
                ->nullOnDelete();
            $table->foreignId('assigned_by')
                ->nullable()
                ->after('supervisor_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->renameColumn('total_hours_rendered', 'hours_rendered');
            $table->renameColumn('scheduled_start_date', 'start_date');
            $table->renameColumn('scheduled_end_date', 'target_completion_date');
        });

        Schema::table('us_assignments', function (Blueprint $table) {
            // Use Laravel's schema grammar so this migration remains portable
            // between the MySQL application database and SQLite test database.
            $table->text('totp_secret')->nullable()->change();
            $table->enum('status', [
                'pending',
                'in_progress',
                'completed',
                'non_compliant',
            ])->default('pending')->change();
            $table->timestamp('totp_secret_generated_at')->nullable()->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('us_assignments', function (Blueprint $table) {
            $table->dropForeign(['case_resolution_id']);
            $table->dropColumn(['case_resolution_id', 'assigned_by', 'totp_secret_generated_at']);
            $table->renameColumn('hours_rendered', 'total_hours_rendered');
            $table->renameColumn('start_date', 'scheduled_start_date');
            $table->renameColumn('target_completion_date', 'scheduled_end_date');
        });

        Schema::table('us_assignments', function (Blueprint $table) {
            $table->string('totp_secret', 64)->nullable(false)->change();
            $table->enum('status', [
                'pending',
                'active',
                'completed',
                'overdue',
                'cancelled',
            ])->change();
            $table->unique('totp_secret');
            $table->foreignId('sanction_id')->nullable()->unique()->constrained('sanctions')->nullOnDelete();
        });
    }
};
