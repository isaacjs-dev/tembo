<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage_periods', function (Blueprint $table): void {
            $table->unsignedInteger('bonus_credits')->default(0)->after('allowance');
        });

        Schema::create('public_catalog_reward_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('rule_version', 50)->unique();
            $table->string('subject_kind', 30);
            $table->string('resource_key', 80);
            $table->unsignedInteger('credit_amount');
            $table->unsignedInteger('per_user_monthly_cap');
            $table->unsignedInteger('global_monthly_cap')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'subject_kind', 'starts_at', 'ends_at'], 'public_reward_rules_effective_index');
        });

        Schema::create('public_catalog_reward_awards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_catalog_reward_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('public_catalog_submission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('user_organization')->nullOnDelete();
            $table->foreignId('usage_event_id')->nullable()->unique()->constrained('usage_events')->nullOnDelete();
            $table->string('scope_key', 100);
            $table->string('resource_key', 80);
            $table->string('rule_version', 50);
            $table->unsignedInteger('requested_amount');
            $table->unsignedInteger('awarded_amount');
            $table->string('status', 30);
            $table->string('idempotency_key', 190)->unique();
            $table->date('period_start');
            $table->json('metadata')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope_key', 'resource_key', 'period_start'], 'public_reward_awards_user_cap_index');
            $table->index(['public_catalog_reward_rule_id', 'period_start'], 'public_reward_awards_global_cap_index');
        });
    }

    public function down(): void
    {
        if (DB::table('public_catalog_reward_awards')->exists()
            || DB::table('usage_events')->where('event_type', 'credit')->exists()) {
            throw new RuntimeException(
                'Rollback recusado: existem créditos de colaboração no ledger. '
                .'Removê-los apagaria a atribuição e alteraria saldos históricos.',
            );
        }

        Schema::dropIfExists('public_catalog_reward_awards');
        Schema::dropIfExists('public_catalog_reward_rules');

        Schema::table('usage_periods', function (Blueprint $table): void {
            $table->dropColumn('bonus_credits');
        });
    }
};
