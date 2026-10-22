<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offense_catalog', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        Schema::table('sanction_matrix', function (Blueprint $table): void {
            $table->unique(['offense_catalog_id', 'offense_count'], 'sanction_matrix_offense_count_unique');
        });

        Schema::create('discipline_policy_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedTinyInteger('same_type_minor_threshold');
            $table->unsignedTinyInteger('different_type_minor_threshold');
            $table->string('policy_reference', 255)->nullable();
            $table->timestamps();
        });

        DB::table('discipline_policy_settings')->insert([
            'id' => 1,
            'same_type_minor_threshold' => 3,
            'different_type_minor_threshold' => 4,
            'policy_reference' => 'Student Handbook: habitual disregard',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_policy_settings');
        Schema::table('sanction_matrix', fn (Blueprint $table) => $table->dropUnique('sanction_matrix_offense_count_unique'));
        Schema::table('offense_catalog', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
