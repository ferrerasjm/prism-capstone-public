<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supervisor's way to void a session they've reviewed and found
     * invalid — the tasks weren't actually performed, or nothing happened
     * during the logged time-in/time-out window. Distinct from deleting the
     * log: the record stays for audit purposes, just excluded from the
     * assignment's rendered-hours total (see
     * USAssignmentService::recomputeAssignmentTotals).
     */
    public function up(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('notes');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                ->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['rejected_at', 'rejected_by', 'rejection_reason']);
        });
    }
};
