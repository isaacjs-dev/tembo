<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revisions', function (Blueprint $table): void {
            $table->string('approved_content_hash', 64)->nullable()->after('review_notes');
            $table->timestamp('approved_at')->nullable()->after('approved_content_hash');
        });
        Schema::table('revision_attempts', function (Blueprint $table): void {
            $table->json('content_snapshot')->nullable()->after('organization_id');
            $table->string('snapshot_hash', 64)->nullable()->after('content_snapshot');
            $table->timestamp('rewarded_at')->nullable()->after('xp_earned');
        });
        Schema::table('revision_responses', function (Blueprint $table): void {
            $table->unsignedBigInteger('snapshot_item_key')->nullable()->after('revision_item_id');
            $table->unique(['revision_attempt_id', 'snapshot_item_key'], 'revision_attempt_snapshot_item_unique');
        });
        DB::table('revision_responses')->whereNotNull('revision_item_id')->update([
            'snapshot_item_key' => DB::raw('revision_item_id'),
        ]);
        DB::table('revision_attempts')->where('xp_earned', '>', 0)->update([
            'rewarded_at' => DB::raw('COALESCE(completed_at, updated_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('revision_responses', function (Blueprint $table): void {
            $table->dropUnique('revision_attempt_snapshot_item_unique');
            $table->dropColumn('snapshot_item_key');
        });
        Schema::table('revision_attempts', function (Blueprint $table): void {
            $table->dropColumn(['content_snapshot', 'snapshot_hash', 'rewarded_at']);
        });
        Schema::table('revisions', function (Blueprint $table): void {
            $table->dropColumn(['approved_content_hash', 'approved_at']);
        });
    }
};
