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
        Schema::table('academic_units', function (Blueprint $table): void {
            $table->string('scope_year_level', 30)->nullable()->after('name');
        });

        Schema::create('discipline_officer_academic_unit', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'academic_unit_id'], 'officer_academic_unit_unique');
        });

        DB::table('users')
            ->where('role', 'discipline_officer')
            ->whereNotNull('academic_unit_id')
            ->orderBy('id')
            ->each(function ($user): void {
                DB::table('discipline_officer_academic_unit')->insertOrIgnore([
                    'user_id' => $user->id,
                    'academic_unit_id' => $user->academic_unit_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        foreach ([
            ['name' => 'Grade 11 (Senior High School)', 'scope_year_level' => 'Grade 11'],
            ['name' => 'Grade 12 (Senior High School)', 'scope_year_level' => 'Grade 12'],
            ['name' => 'School of Dentistry and Allied Health', 'scope_year_level' => null],
        ] as $unit) {
            DB::table('academic_units')->updateOrInsert(
                ['name' => $unit['name']],
                [
                    'scope_year_level' => $unit['scope_year_level'],
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_officer_academic_unit');

        Schema::table('academic_units', function (Blueprint $table): void {
            $table->dropColumn('scope_year_level');
        });
    }
};
