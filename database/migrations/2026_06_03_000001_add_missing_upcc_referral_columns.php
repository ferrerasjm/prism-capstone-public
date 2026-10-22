<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            if (! Schema::hasColumn('upcc_referrals', 'decision_distributed_at')) {
                $table->timestamp('decision_distributed_at')
                    ->nullable()
                    ->after('decision_issued_at');
            }

            if (! Schema::hasColumn('upcc_referrals', 'proceedings_type')) {
                $table->enum('proceedings_type', ['full_hearing', 'summary'])
                    ->default('full_hearing')
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            if (Schema::hasColumn('upcc_referrals', 'decision_distributed_at')) {
                $table->dropColumn('decision_distributed_at');
            }
            if (Schema::hasColumn('upcc_referrals', 'proceedings_type')) {
                $table->dropColumn('proceedings_type');
            }
        });
    }
};
