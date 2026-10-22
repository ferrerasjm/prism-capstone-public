<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            $table->string('sdao_head_name', 255)->nullable()->after('proceedings_type');
            $table->string('dean_or_program_chair_name', 255)->nullable()->after('sdao_head_name');
            $table->string('cross_program_faculty_name', 255)->nullable()->after('dean_or_program_chair_name');
            $table->string('student_representative_name', 255)->nullable()->after('cross_program_faculty_name');
            $table->string('presiding_officer_name', 255)->nullable()->after('student_representative_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            $table->dropColumn([
                'sdao_head_name',
                'dean_or_program_chair_name',
                'cross_program_faculty_name',
                'student_representative_name',
                'presiding_officer_name',
            ]);
        });
    }
};
