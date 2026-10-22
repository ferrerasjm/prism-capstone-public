<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table): void {
            $table->foreignId('appeal_waived_by')
                ->nullable()
                ->after('appeal_source')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('appeal_waived_at')
                ->nullable()
                ->after('appeal_waived_by');
        });
    }

    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('appeal_waived_by');
            $table->dropColumn('appeal_waived_at');
        });
    }
};
