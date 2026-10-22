<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purely additive covering indexes for the filter/sort columns the dashboards
 * and listing screens hit on every request. Foreign keys already carry their
 * own indexes; the columns below (status, type, and the date columns used for
 * ordering and overdue checks) had none, so those screens fell back to full
 * table scans as the operational tables grow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table): void {
            $table->index(['status', 'updated_at'], 'cases_status_updated_index');
            $table->index(['case_type', 'status'], 'cases_type_status_index');
            $table->index(['status', 'resolved_at'], 'cases_status_resolved_index');
        });

        Schema::table('sanctions', function (Blueprint $table): void {
            $table->index(['status', 'due_date'], 'sanctions_status_due_index');
            $table->index(['sanction_type', 'created_at'], 'sanctions_type_created_index');
            $table->index(['student_id', 'status'], 'sanctions_student_status_index');
        });

        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->index(['status', 'created_at'], 'incident_reports_status_created_index');
        });

        Schema::table('us_assignments', function (Blueprint $table): void {
            $table->index(['status', 'target_completion_date'], 'us_assignments_status_target_index');
        });

        Schema::table('case_notices', function (Blueprint $table): void {
            $table->index(
                ['notice_type', 'is_voided', 'response_deadline'],
                'case_notices_type_voided_deadline_index'
            );
        });

        // Every authenticated layout renders an unread-notification badge, so
        // this composite backs the count on the hottest query in the app.
        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at'],
                'notifications_notifiable_read_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table): void {
            $table->dropIndex('cases_status_updated_index');
            $table->dropIndex('cases_type_status_index');
            $table->dropIndex('cases_status_resolved_index');
        });

        Schema::table('sanctions', function (Blueprint $table): void {
            $table->dropIndex('sanctions_status_due_index');
            $table->dropIndex('sanctions_type_created_index');
            $table->dropIndex('sanctions_student_status_index');
        });

        Schema::table('incident_reports', function (Blueprint $table): void {
            $table->dropIndex('incident_reports_status_created_index');
        });

        Schema::table('us_assignments', function (Blueprint $table): void {
            $table->dropIndex('us_assignments_status_target_index');
        });

        Schema::table('case_notices', function (Blueprint $table): void {
            $table->dropIndex('case_notices_type_voided_deadline_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_notifiable_read_index');
        });
    }
};
