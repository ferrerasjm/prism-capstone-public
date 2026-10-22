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
        Schema::create('case_offenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('offense_catalog_id')->constrained('offense_catalog')->restrictOnDelete();
            $table->enum('status', ['confirmed', 'dismissed'])->default('confirmed');
            $table->unsignedTinyInteger('prior_offense_count')->default(0);
            $table->text('classification_reason')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'offense_catalog_id']);
        });

        // Preserve every classification made before multi-charge support.
        $cases = DB::table('cases')->whereNotNull('confirmed_offense_id')->get();
        foreach ($cases as $case) {
            DB::table('case_offenses')->insert([
                'case_id' => $case->id,
                'offense_catalog_id' => $case->confirmed_offense_id,
                'status' => 'confirmed',
                'prior_offense_count' => $case->prior_offense_count ?? 0,
                'classification_reason' => $case->classification_override_reason,
                'confirmed_by' => $case->assigned_to,
                'confirmed_at' => $case->updated_at,
                'created_at' => $case->updated_at,
                'updated_at' => $case->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('case_offenses');
    }
};
