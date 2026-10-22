<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('us_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanction_id')->unique()->nullable()->constrained('sanctions')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('office_name', 255);
            $table->decimal('total_hours_required', 6, 2);
            $table->decimal('total_hours_rendered', 6, 2)->default(0);
            $table->date('scheduled_start_date');
            $table->date('scheduled_end_date');
            $table->enum('status', ['pending', 'active', 'completed', 'overdue', 'cancelled']);
            $table->string('totp_secret', 64)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('us_assignments');
    }
};
