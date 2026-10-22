<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_notices', function (Blueprint $table): void {
            $table->timestamp('deadline_reminder_sent_at')->nullable()->after('response_deadline');
            $table->timestamp('overdue_flagged_at')->nullable()->after('deadline_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('case_notices', function (Blueprint $table): void {
            $table->dropColumn(['deadline_reminder_sent_at', 'overdue_flagged_at']);
        });
    }
};
