<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('student_status', 30)->nullable();
            $table->date('student_status_on')->nullable();
        });
        Schema::create('student_status_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('academic_period_id')->constrained()->restrictOnDelete();
            $table->string('source_reference');
            $table->date('effective_on');
            $table->json('rows');
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_status_batches');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['student_status', 'student_status_on']));
    }
};
