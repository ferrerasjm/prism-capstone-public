<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->string('file_type', 20)->change();
            $table->string('file_hash', 64)->nullable()->after('file_size_bytes');
            $table->string('scan_status', 30)->default('not_configured')->after('file_hash');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
        });

        Schema::table('upcc_appeal_attachments', function (Blueprint $table): void {
            $table->string('file_hash', 64)->nullable()->after('file_size_bytes');
            $table->string('scan_status', 30)->default('not_configured')->after('file_hash');
            $table->timestamp('scanned_at')->nullable()->after('scan_status');
        });
    }

    public function down(): void
    {
        Schema::table('incident_attachments', function (Blueprint $table): void {
            $table->dropColumn(['file_hash', 'scan_status', 'scanned_at']);
        });

        Schema::table('upcc_appeal_attachments', function (Blueprint $table): void {
            $table->dropColumn(['file_hash', 'scan_status', 'scanned_at']);
        });
    }
};
