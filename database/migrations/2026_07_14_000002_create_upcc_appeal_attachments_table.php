<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upcc_appeal_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('upcc_referral_id')
                ->constrained('upcc_referrals')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('file_type', 20);
            $table->unsignedInteger('file_size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upcc_appeal_attachments');
    }
};
