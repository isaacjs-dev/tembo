<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_scan_pages', function (Blueprint $table): void {
            $table->string('idempotency_key', 190)->nullable()->after('uploaded_by');
            $table->string('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique(
                ['organization_id', 'uploaded_by', 'idempotency_key'],
                'omr_pages_uploader_idempotency_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('omr_scan_pages', function (Blueprint $table): void {
            $table->dropUnique('omr_pages_uploader_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};
