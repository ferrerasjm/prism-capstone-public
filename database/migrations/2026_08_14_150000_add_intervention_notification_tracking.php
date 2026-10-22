<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discipline_education_attendances', function (Blueprint $table): void {
            $table->timestamp('notification_sent_at')->nullable()->after('recorded_by');
            $table->timestamp('reminder_sent_at')->nullable()->after('notification_sent_at');
        });

        Schema::table('sanctions', function (Blueprint $table): void {
            $table->timestamp('intervention_notification_sent_at')->nullable()->after('notes');
            $table->timestamp('intervention_reminder_sent_at')->nullable()->after('intervention_notification_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('discipline_education_attendances', function (Blueprint $table): void {
            $table->dropColumn(['notification_sent_at', 'reminder_sent_at']);
        });

        Schema::table('sanctions', function (Blueprint $table): void {
            $table->dropColumn(['intervention_notification_sent_at', 'intervention_reminder_sent_at']);
        });
    }
};
