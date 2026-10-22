<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->boolean('totp_verified')->nullable()->after('log_method');
            $table->timestamp('client_scanned_at')->nullable()->after('totp_verified');
        });
    }

    public function down(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['totp_verified', 'client_scanned_at']);
        });
    }
};
