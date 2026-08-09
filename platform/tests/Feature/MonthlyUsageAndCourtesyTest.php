<?php

namespace Tests\Feature;

use App\Exceptions\QuotaExceededException;
use App\Models\CourtesyGrant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\EntitlementService;
use App\Services\MonthlyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MonthlyUsageAndCourtesyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_consumption_is_individual_idempotent_and_blocks_over_limit(): void
    {
        [$organization, $teacher] = $this->subscriberWithLimit(MonthlyUsageService::OMR_SCANS, 2);
        $usage = app(MonthlyUsageService::class);

        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'scan:one');
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'scan:one');
        $this->assertSame(1, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS)['consumed']);

        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'scan:two');
        $this->expectException(QuotaExceededException::class);
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'scan:three');
    }

    public function test_manual_reset_preserves_immutable_history(): void
    {
        [, $teacher] = $this->subscriberWithLimit(MonthlyUsageService::QUESTIONS_CREATED, 10);
        $admin = User::factory()->create(['type' => 'global_admin']);
        $usage = app(MonthlyUsageService::class);
        $usage->consume($teacher, MonthlyUsageService::QUESTIONS_CREATED, 3, 'questions:batch');

        $usage->reset($teacher, MonthlyUsageService::QUESTIONS_CREATED, $admin, 'Créditos liberados para demonstração.', 'reset:test');

        $this->assertSame(0, $usage->snapshot($teacher, MonthlyUsageService::QUESTIONS_CREATED)['consumed']);
        $this->assertDatabaseHas('usage_events', ['event_type' => 'consume', 'amount' => 3]);
        $this->assertDatabaseHas('usage_events', ['event_type' => 'reset', 'amount' => -3, 'actor_id' => $admin->id]);
    }

    public function test_courtesies_accumulate_and_organization_scope_only_benefits_teachers(): void
    {
        [$organization, $teacher] = $this->subscriberWithLimit(MonthlyUsageService::EXAM_PUBLICATIONS, 5);
        $institutionAdmin = User::factory()->create(['organization_id' => $organization->id, 'type' => 'institution_admin']);
        $globalAdmin = User::factory()->create(['type' => 'global_admin']);

        $grant = CourtesyGrant::query()->create([
            'target_scope' => 'organization', 'target_id' => $organization->id, 'status' => 'active',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addMonth(),
            'reason' => 'Créditos comerciais de homologação.', 'authorized_by' => $globalAdmin->id,
            'metadata' => ['eligible_roles' => ['teacher']],
        ]);
        $grant->benefits()->create([
            'benefit_type' => 'credit', 'resource_key' => MonthlyUsageService::EXAM_PUBLICATIONS, 'quantity' => 20,
        ]);

        $entitlements = app(EntitlementService::class);
        $this->assertSame(25, $entitlements->monthlyAllowance($teacher, MonthlyUsageService::EXAM_PUBLICATIONS));
        $this->assertSame(5, $entitlements->monthlyAllowance($institutionAdmin, MonthlyUsageService::EXAM_PUBLICATIONS));
    }

    public function test_only_global_admin_can_open_usage_and_courtesy_management(): void
    {
        $organization = Organization::query()->create(['name' => 'Escola']);
        $teacher = User::factory()->create(['organization_id' => $organization->id, 'type' => 'teacher']);
        $teacher->assignRole('teacher');
        $admin = User::factory()->create(['type' => 'global_admin']);
        $admin->assignRole('global_admin');

        $this->actingAs($teacher)->get(route('admin.usage.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.courtesies.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.usage.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.courtesies.index'))->assertOk();
    }

    /** @return array{Organization, User} */
    private function subscriberWithLimit(string $resource, int $limit): array
    {
        $plan = Plan::query()->create(['name' => 'Plano', 'slug' => 'plano-'.uniqid(), 'price' => 10, 'original_price' => 10, 'status' => 'active']);
        PlanLimit::query()->create(['plan_id' => $plan->id, 'resource_key' => $resource, 'limit_value' => $limit]);
        $organization = Organization::query()->create(['name' => 'Escola '.uniqid(), 'active' => true]);
        Subscription::query()->create([
            'organization_id' => $organization->id, 'plan_id' => $plan->id,
            'subscriber_type' => Organization::class, 'subscriber_id' => $organization->id, 'status' => 'active',
        ]);
        $teacher = User::factory()->create(['organization_id' => $organization->id, 'type' => 'teacher', 'status' => 'active']);

        return [$organization, $teacher];
    }
}
