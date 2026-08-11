<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('omr_scan_pages', function (Blueprint $table): void {
            $table->json('processing_evidence')->nullable()->after('overall_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('omr_scan_pages', function (Blueprint $table): void {
            $table->dropColumn('processing_evidence');
        });
    }
};
