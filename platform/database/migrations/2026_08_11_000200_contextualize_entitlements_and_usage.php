<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->timestamp('grace_ends_at')->nullable()->after('expires_at');
            $table->timestamp('cancelled_at')->nullable()->after('grace_ends_at');
            $table->index(['status', 'starts_at', 'expires_at', 'grace_ends_at'], 'subscriptions_effective_window_index');
        });

        Schema::table('usage_periods', function (Blueprint $table): void {
            $table->string('scope_key', 100)->nullable()->after('organization_id');
            $table->unsignedBigInteger('membership_id')->nullable()->after('scope_key');
            $table->foreign('membership_id')->references('id')->on('user_organization')->nullOnDelete();
        });

        DB::table('usage_periods')->orderBy('id')->chunkById(200, function ($periods): void {
            foreach ($periods as $period) {
                $membershipId = $period->organization_id
                    ? DB::table('user_organization')->where('user_id', $period->user_id)
                        ->where('organization_id', $period->organization_id)->value('id')
                    : null;
                DB::table('usage_periods')->where('id', $period->id)->update([
                    'scope_key' => $period->organization_id
                        ? 'organization:'.$period->organization_id
                        : 'user:'.$period->user_id,
                    'membership_id' => $membershipId,
                ]);
            }
        });

        Schema::table('usage_periods', function (Blueprint $table): void {
            $table->dropUnique('usage_period_user_resource_month_unique');
            $table->unique(
                ['user_id', 'scope_key', 'resource_key', 'period_start'],
                'usage_period_user_scope_resource_month_unique',
            );
            $table->index(['membership_id', 'resource_key', 'period_start'], 'usage_period_membership_resource_index');
        });

        Schema::table('usage_events', function (Blueprint $table): void {
            $table->string('scope_key', 100)->nullable()->after('organization_id');
            $table->unsignedBigInteger('membership_id')->nullable()->after('scope_key');
            $table->foreign('membership_id')->references('id')->on('user_organization')->nullOnDelete();
            $table->index(['scope_key', 'resource_key', 'occurred_at'], 'usage_event_scope_resource_index');
        });

        DB::table('usage_events')->orderBy('id')->chunkById(200, function ($events): void {
            foreach ($events as $event) {
                $period = DB::table('usage_periods')->where('id', $event->usage_period_id)->first();
                DB::table('usage_events')->where('id', $event->id)->update([
                    'scope_key' => $period?->scope_key ?? ($event->organization_id
                        ? 'organization:'.$event->organization_id
                        : 'user:'.$event->user_id),
                    'membership_id' => $period?->membership_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        $hasMultipleScopes = DB::table('usage_periods')
            ->select('user_id', 'resource_key', 'period_start')
            ->groupBy('user_id', 'resource_key', 'period_start')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($hasMultipleScopes) {
            throw new RuntimeException(
                'Rollback recusado: existem ledgers do mesmo usuário em múltiplos workspaces. '
                .'Recombinar esses períodos removeria atribuição e histórico de consumo.',
            );
        }

        Schema::table('usage_events', function (Blueprint $table): void {
            $table->dropIndex('usage_event_scope_resource_index');
            $table->dropForeign(['membership_id']);
            $table->dropColumn(['scope_key', 'membership_id']);
        });

        Schema::table('usage_periods', function (Blueprint $table): void {
            $table->dropIndex('usage_period_membership_resource_index');
            $table->dropUnique('usage_period_user_scope_resource_month_unique');
            $table->unique(['user_id', 'resource_key', 'period_start'], 'usage_period_user_resource_month_unique');
            $table->dropForeign(['membership_id']);
            $table->dropColumn(['scope_key', 'membership_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_effective_window_index');
            $table->dropColumn(['grace_ends_at', 'cancelled_at']);
        });
    }
};
