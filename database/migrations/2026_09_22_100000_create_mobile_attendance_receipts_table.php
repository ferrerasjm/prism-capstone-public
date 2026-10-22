<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_attendance_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('assignment_id');
            $table->string('client_log_id', 64);
            $table->char('payload_hash', 64);
            $table->char('qr_identity', 64);
            $table->bigInteger('totp_step');
            $table->unsignedBigInteger('log_id');
            $table->text('response_json');
            $table->longText('signed_payload');
            $table->text('signature');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'client_log_id']);
            $table->unique(['assignment_id', 'qr_identity', 'totp_step'], 'mobile_attendance_qr_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_attendance_receipts');
    }
};
