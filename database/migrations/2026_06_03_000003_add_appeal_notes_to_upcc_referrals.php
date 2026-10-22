<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            $table->text('appeal_notes')
                ->nullable()
                ->after('appeal_filed');
        });
    }

    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table) {
            $table->dropColumn('appeal_notes');
        });
    }
};
