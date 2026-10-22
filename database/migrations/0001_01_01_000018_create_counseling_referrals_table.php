<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counseling_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanction_id')->unique()->nullable()->constrained('sanctions')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('guidance_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('referred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('referred_at');
            $table->text('referral_reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'incomplete']);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reassigned_from')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reassigned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counseling_referrals');
    }
};
