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
            $table->enum('event_type', ['time_in', 'time_out'])->nullable()->change();
            $table->dateTime('event_timestamp')->nullable()->change();
            $table->dateTime('server_timestamp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('us_attendance_logs', function (Blueprint $table) {
            $table->enum('event_type', ['time_in', 'time_out'])->nullable(false)->change();
            $table->dateTime('event_timestamp')->nullable(false)->change();
            $table->dateTime('server_timestamp')->nullable(false)->change();
        });
    }
};
