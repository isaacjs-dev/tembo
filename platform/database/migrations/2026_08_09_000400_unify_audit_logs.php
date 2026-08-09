<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('request_id', 100)->nullable()->after('user_id')->index();
            $table->string('origin', 20)->default('system')->after('request_id')->index();
            $table->string('severity', 20)->default('info')->after('origin')->index();
            $table->json('context_json')->nullable()->after('payload');
            $table->json('before_json')->nullable()->after('context_json');
            $table->json('after_json')->nullable()->after('before_json');
            $table->unsignedBigInteger('legacy_event_log_id')->nullable()->after('after_json')->unique();
            $table->index(['organization_id', 'created_at'], 'audit_logs_org_created_index');
        });

        DB::table('audit_logs')->orderBy('id')->chunkById(250, function ($logs): void {
            foreach ($logs as $log) {
                $payload = $this->decode($log->payload);
                $organizationId = $payload['organization_id'] ?? null;
                if (! $organizationId && $log->user_id) {
                    $organizationId = DB::table('users')->where('id', $log->user_id)->value('organization_id');
                }

                DB::table('audit_logs')->where('id', $log->id)->update([
                    'organization_id' => $organizationId,
                    'context_json' => $this->encode(array_diff_key($payload, array_flip(['old', 'new', 'before', 'after']))),
                    'before_json' => $this->encode($payload['before'] ?? $payload['old'] ?? null),
                    'after_json' => $this->encode($payload['after'] ?? $payload['new'] ?? null),
                ]);
            }
        });

        if (Schema::hasTable('event_logs')) {
            DB::table('event_logs')->orderBy('id')->chunkById(250, function ($events): void {
                foreach ($events as $event) {
                    $context = $this->decode($event->context_json);
                    if ($event->message) {
                        $context['message'] = $event->message;
                    }

                    DB::table('audit_logs')->insertOrIgnore([
                        'organization_id' => $event->organization_id,
                        'user_id' => $event->actor_user_id,
                        'request_id' => 'legacy-event-'.$event->id,
                        'origin' => 'system',
                        'severity' => $event->severity ?: 'info',
                        'action' => $event->event_code,
                        'model_type' => $event->entity_type,
                        'model_id' => $event->entity_id,
                        'payload' => $this->encode($context),
                        'context_json' => $this->encode($context),
                        'before_json' => $event->before_json,
                        'after_json' => $event->after_json,
                        'legacy_event_log_id' => $event->id,
                        'ip_address' => $event->ip,
                        'user_agent' => $event->user_agent,
                        'created_at' => $event->created_at ?? now(),
                    ]);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex('audit_logs_org_created_index');
            $table->dropIndex(['request_id']);
            $table->dropIndex(['origin']);
            $table->dropIndex(['severity']);
            $table->dropUnique(['legacy_event_log_id']);
            $table->dropForeign(['organization_id']);
            $table->dropColumn([
                'organization_id', 'request_id', 'origin', 'severity', 'context_json',
                'before_json', 'after_json', 'legacy_event_log_id',
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};
