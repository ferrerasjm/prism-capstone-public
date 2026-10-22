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
            $table->text('override_reason')->nullable()->after('client_scanned_at');
        });
    }

    public function down(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->dropColumn('override_reason');
        });
    }
};
