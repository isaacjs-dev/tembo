<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_catalog_reward_rule_slots', function (Blueprint $table): void {
            $table->string('subject_kind', 30)->primary();
            $table->foreignId('active_rule_id')->nullable()->constrained('public_catalog_reward_rules')->nullOnDelete();
            $table->foreignId('scheduled_rule_id')->nullable()->constrained('public_catalog_reward_rules')->nullOnDelete();
            $table->timestamps();
        });

        DB::table('public_catalog_reward_rule_slots')->insert([
            ['subject_kind' => 'question', 'created_at' => now(), 'updated_at' => now()],
            ['subject_kind' => 'resource', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_catalog_reward_rule_slots');
    }
};
