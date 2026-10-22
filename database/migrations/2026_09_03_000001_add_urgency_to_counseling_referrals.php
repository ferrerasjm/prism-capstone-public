<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counseling_referrals', function (Blueprint $table) {
            // The Discipline Officer's read on how soon the Guidance Office needs to see this
            // student — a scheduling signal, not a clinical severity rating. 'routine' matches
            // every referral recorded before this column existed (see the migration default).
            $table->enum('urgency', ['routine', 'priority', 'immediate'])
                ->default('routine')
                ->after('referral_reason');
        });
    }

    public function down(): void
    {
        Schema::table('counseling_referrals', function (Blueprint $table) {
            $table->dropColumn('urgency');
        });
    }
};
