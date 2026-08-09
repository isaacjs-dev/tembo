<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resource_key', 80);
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedInteger('allowance')->nullable();
            $table->unsignedInteger('consumed')->default(0);
            $table->unsignedInteger('manual_resets')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'resource_key', 'period_start'], 'usage_period_user_resource_month_unique');
            $table->index(['organization_id', 'resource_key', 'period_start']);
        });

        Schema::create('usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usage_period_id')->constrained('usage_periods')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resource_key', 80);
            $table->string('event_type', 40);
            $table->integer('amount');
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->nullableMorphs('context');
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'resource_key', 'occurred_at']);
        });

        Schema::create('courtesy_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('target_scope', 30);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_role', 40)->nullable();
            $table->string('status', 30)->default('scheduled');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['target_scope', 'target_id']);
            $table->index(['target_scope', 'target_role']);
        });

        Schema::create('courtesy_benefits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('courtesy_grant_id')->constrained()->cascadeOnDelete();
            $table->string('benefit_type', 30);
            $table->string('resource_key', 80)->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature_key', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['resource_key', 'benefit_type']);
        });

        Schema::create('admin_batch_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type', 50);
            $table->string('target_scope', 30);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('target_role', 40)->nullable();
            $table->json('resource_keys')->nullable();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('result')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_batch_operations');
        Schema::dropIfExists('courtesy_benefits');
        Schema::dropIfExists('courtesy_grants');
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('usage_periods');
    }
};
