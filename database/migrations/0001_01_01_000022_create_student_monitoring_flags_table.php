<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_monitoring_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('flagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('flag_reason');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at');
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_monitoring_flags');
    }
};
