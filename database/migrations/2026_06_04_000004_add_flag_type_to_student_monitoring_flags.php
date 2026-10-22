<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_monitoring_flags', 'flag_type')) {
            Schema::table('student_monitoring_flags', function (Blueprint $table) {
                $table->enum('flag_type', [
                    'registration_hold',
                    'monitoring',
                    'clearance_hold',
                ])->default('monitoring')->after('student_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('student_monitoring_flags', function (Blueprint $table) {
            $table->dropColumn('flag_type');
        });
    }
};
