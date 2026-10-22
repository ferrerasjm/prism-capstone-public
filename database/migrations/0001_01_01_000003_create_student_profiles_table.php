<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->nullable()->constrained()->nullOnDelete();
            $table->string('student_number', 50)->unique();
            $table->string('program', 100)->nullable();
            $table->string('year_level', 20)->nullable();
            $table->string('contact_number', 20)->nullable();
            $table->string('guardian_name', 255)->nullable();
            $table->string('guardian_contact', 20)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->timestamp('data_accuracy_acknowledged_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
