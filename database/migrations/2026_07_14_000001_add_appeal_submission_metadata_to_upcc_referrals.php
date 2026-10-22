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
            $table->foreignId('appeal_filed_by')
                ->nullable()
                ->after('appeal_filed')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('appeal_filed_at')
                ->nullable()
                ->after('appeal_filed_by');
            $table->string('appeal_source', 50)
                ->nullable()
                ->after('appeal_filed_at');
        });
    }

    public function down(): void
    {
        Schema::table('upcc_referrals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('appeal_filed_by');
            $table->dropColumn(['appeal_filed_at', 'appeal_source']);
        });
    }
};
