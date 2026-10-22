<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_education_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sanction_id')->constrained('sanctions')->cascadeOnDelete();
            $table->dateTime('session_at');
            $table->enum('status', ['scheduled', 'present', 'absent']);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['sanction_id', 'session_at'], 'de_attendance_sanction_session_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_education_attendances');
    }
};
