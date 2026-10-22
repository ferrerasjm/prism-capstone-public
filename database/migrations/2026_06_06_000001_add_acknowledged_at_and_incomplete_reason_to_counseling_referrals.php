<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_referrals', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')
                ->nullable()
                ->after('referred_at');
            $table->text('incomplete_reason')
                ->nullable()
                ->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('counseling_referrals', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'incomplete_reason']);
        });
    }
};
