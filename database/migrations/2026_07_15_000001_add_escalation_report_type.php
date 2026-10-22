<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE scheduled_reports MODIFY report_type ENUM('monthly_violation_summary','student_case_report','case_summary','us_compliance','upcc_case_report','registration_hold_report','escalation_report') NOT NULL");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE scheduled_reports MODIFY report_type ENUM('monthly_violation_summary','student_case_report','case_summary','us_compliance','upcc_case_report','registration_hold_report') NOT NULL");
        }
    }
};
