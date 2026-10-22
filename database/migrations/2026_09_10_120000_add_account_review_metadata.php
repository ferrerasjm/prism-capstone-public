<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('deactivation_category', 40)->nullable();
            $table->date('deactivation_effective_on')->nullable();
            $table->string('status_before_deactivation', 30)->nullable();
        });
        Schema::create('roster_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->string('source_reference');
            $table->date('roster_date');
            $table->string('file_hash', 64);
            $table->json('comparison');
            $table->text('completion_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_reviews');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'deactivation_category', 'deactivation_effective_on', 'status_before_deactivation',
        ]));
    }
};
