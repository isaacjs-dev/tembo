<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EventLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnifiedAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_sensitive_web_action_records_tenant_actor_origin_request_and_changes(): void
    {
        $organization = $this->organization();
        $admin = $this->member($organization, 'admin');

        Route::middleware(['web', 'auth', 'workspace_context'])->post('/_audit-contract', function (Request $request) {
            AuditLog::log('sensitive_changed', User::class, $request->user()->id, [
                'organization_id' => $request->user()->organization_id,
                'reason' => 'test',
                'api_token' => 'never-store-me',
                'before' => ['name' => 'Antes', 'password' => 'old-secret'],
                'after' => ['name' => 'Depois', 'password' => 'new-secret'],
            ]);

            return response()->noContent();
        });

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $organization->id])
            ->withHeader('X-Request-ID', 'web-request-123')
            ->post('/_audit-contract')
            ->assertNoContent();

        $log = AuditLog::where('action', 'sensitive_changed')->sole();
        $this->assertSame($organization->id, (int) $log->organization_id);
        $this->assertSame($admin->id, (int) $log->user_id);
        $this->assertSame('web-request-123', $log->request_id);
        $this->assertSame('web', $log->origin);
        $this->assertSame('Antes', $log->before_json['name']);
        $this->assertSame('Depois', $log->after_json['name']);
        $this->assertSame('[REDACTED]', $log->before_json['password']);
        $this->assertSame('[REDACTED]', $log->after_json['password']);
        $this->assertSame('[REDACTED]', $log->context_json['api_token']);
    }

    public function test_operational_event_is_mirrored_once_into_unified_audit(): void
    {
        $organization = $this->organization();
        $actor = $this->member($organization, 'admin');

        $event = EventLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => $actor->id,
            'event_code' => 'institution.settings.updated',
            'severity' => 'warning',
            'entity_type' => Organization::class,
            'entity_id' => $organization->id,
            'message' => 'Configuração alterada',
            'before_json' => ['name' => 'Antes'],
            'after_json' => ['name' => 'Depois'],
        ]);

        AuditLog::fromEvent($event);

        $log = AuditLog::where('legacy_event_log_id', $event->id)->sole();
        $this->assertSame($organization->id, (int) $log->organization_id);
        $this->assertSame('institution.settings.updated', $log->action);
        $this->assertSame('warning', $log->severity);
        $this->assertSame('Configuração alterada', $log->context_json['message']);
        $this->assertSame('Antes', $log->before_json['name']);
        $this->assertSame('Depois', $log->after_json['name']);
    }

    public function test_api_origin_and_workspace_are_recorded_with_safe_generated_request_id(): void
    {
        $organization = $this->organization();
        $admin = $this->member($organization, 'admin');

        Route::middleware(['api', 'auth:sanctum', 'workspace_context'])->post('/api/_audit-contract', function (Request $request) {
            AuditLog::log('api_sensitive_changed', User::class, $request->user()->id, [
                'organization_id' => $request->user()->organization_id,
            ]);

            return response()->noContent();
        });
        Sanctum::actingAs($admin);

        $this->withHeader('X-Workspace-Id', (string) $organization->id)
            ->withHeader('X-Request-ID', 'invalid request id with spaces')
            ->postJson('/api/_audit-contract')
            ->assertNoContent();

        $log = AuditLog::where('action', 'api_sensitive_changed')->sole();
        $this->assertSame($organization->id, (int) $log->organization_id);
        $this->assertSame('api', $log->origin);
        $this->assertNotSame('invalid request id with spaces', $log->request_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $log->request_id);
    }

    public function test_institution_filters_never_escape_current_tenant(): void
    {
        $organization = $this->organization(['can_access_logs' => true]);
        $other = $this->organization(['can_access_logs' => true]);
        $admin = $this->member($organization, 'admin');
        $foreignActor = $this->member($other, 'admin');

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $admin->id,
            'action' => 'own.action',
            'origin' => 'web',
            'severity' => 'info',
            'created_at' => now(),
        ]);
        AuditLog::create([
            'organization_id' => $other->id,
            'user_id' => $foreignActor->id,
            'action' => 'foreign.action',
            'origin' => 'api',
            'severity' => 'critical',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['workspace_id' => $organization->id])
            ->get(route('institution.logs', [
                'actor_id' => $foreignActor->id,
                'search' => 'foreign.action',
            ]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->isEmpty());
    }

    private function organization(array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Instituição '.uniqid(),
            'active' => true,
        ], $attributes));
    }

    private function member(Organization $organization, string $role): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'institution_admin',
            'status' => 'active',
        ]);
        $user->assignRole('institution_admin');
        $user->organizations()->attach($organization->id, [
            'role_in_org' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
