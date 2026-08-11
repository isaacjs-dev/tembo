<?php

namespace Tests\Feature;

use App\Models\CourtesyGrant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\UsagePeriod;
use App\Models\User;
use App\Services\EffectivePlanResolver;
use App\Services\EntitlementService;
use App\Services\MonthlyUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContextualEntitlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_entitlements_and_courtesies_are_resolved_only_for_the_selected_membership(): void
    {
        [$first, $firstPlan] = $this->organizationWithPlan('Primeiro', 2, 1);
        [$second, $secondPlan] = $this->organizationWithPlan('Segundo', 50, 3);
        $teacher = $this->member($first, 'teacher');
        $teacher->organizations()->attach($second->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $admin = User::factory()->create(['type' => 'global_admin']);
        $grant = CourtesyGrant::query()->create([
            'target_scope' => 'organization', 'target_id' => $second->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth(),
            'reason' => 'Cortesia contextual.', 'authorized_by' => $admin->id,
            'metadata' => ['eligible_roles' => ['teacher']],
        ]);
        $grant->benefits()->create([
            'benefit_type' => 'credit', 'resource_key' => MonthlyUsageService::OMR_SCANS, 'quantity' => 10,
        ]);

        $entitlements = app(EntitlementService::class);
        $this->assertTrue($entitlements->effectivePlan($teacher, $first)->is($firstPlan));
        $this->assertSame(2, $entitlements->monthlyAllowance($teacher, MonthlyUsageService::OMR_SCANS, $first));
        $this->assertTrue($entitlements->effectivePlan($teacher, $second)->is($secondPlan));
        $this->assertSame(60, $entitlements->monthlyAllowance($teacher, MonthlyUsageService::OMR_SCANS, $second));

        $teacher->organizations()->updateExistingPivot($second->id, ['status' => 'inactive']);
        $this->assertNull($entitlements->effectivePlan($teacher->fresh(), $second));
        $this->assertCount(0, $entitlements->benefitsFor($teacher->fresh(), $second));
        $this->assertSame(
            0,
            $entitlements->monthlyAllowance($teacher->fresh(), MonthlyUsageService::OMR_SCANS, $second),
        );
        $this->assertSame(
            0,
            (new EffectivePlanResolver)->resolveLimit(
                $teacher->fresh(),
                MonthlyUsageService::OMR_SCANS,
                $second,
            ),
        );
    }

    public function test_monthly_ledger_is_separated_and_attributed_to_each_membership(): void
    {
        [$first] = $this->organizationWithPlan('Primeiro', 5, 1);
        [$second] = $this->organizationWithPlan('Segundo', 5, 1);
        $teacher = $this->member($first, 'teacher');
        $teacher->organizations()->attach($second->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $usage = app(MonthlyUsageService::class);

        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'scope:first', organization: $first);
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 2, 'scope:second', organization: $second);

        $this->assertSame(1, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS, $first)['consumed']);
        $this->assertSame(2, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS, $second)['consumed']);
        $this->assertDatabaseCount('usage_periods', 2);
        $this->assertDatabaseHas('usage_periods', [
            'user_id' => $teacher->id, 'organization_id' => $first->id,
            'scope_key' => 'organization:'.$first->id,
        ]);
        $this->assertDatabaseHas('usage_events', [
            'idempotency_key' => 'scope:second', 'organization_id' => $second->id,
            'scope_key' => 'organization:'.$second->id,
        ]);
        $this->assertDatabaseMissing('usage_periods', ['membership_id' => null]);
    }

    public function test_reused_idempotency_key_cannot_silently_skip_another_scope(): void
    {
        [$first] = $this->organizationWithPlan('Primeiro', 5, 1);
        [$second] = $this->organizationWithPlan('Segundo', 5, 1);
        $teacher = $this->member($first, 'teacher');
        $teacher->organizations()->attach($second->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $usage = app(MonthlyUsageService::class);
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'same-operation', organization: $first);

        try {
            $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 1, 'same-operation', organization: $second);
            $this->fail('A colisão contextual deveria ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }
        $this->assertSame(0, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS, $second)['consumed']);
    }

    public function test_scheduled_courtesy_becomes_effective_by_time_without_waiting_for_cron(): void
    {
        [$organization] = $this->organizationWithPlan('Cortesia', 2, 1);
        $teacher = $this->member($organization, 'teacher');
        $admin = User::factory()->create(['type' => 'global_admin']);
        $grant = CourtesyGrant::query()->create([
            'target_scope' => 'organization', 'target_id' => $organization->id, 'status' => 'scheduled',
            'starts_at' => now()->subMinute(), 'ends_at' => now()->addDay(),
            'reason' => 'Ativação por janela temporal.', 'authorized_by' => $admin->id,
            'metadata' => ['eligible_roles' => ['teacher']],
        ]);
        $grant->benefits()->create([
            'benefit_type' => 'credit', 'resource_key' => MonthlyUsageService::OMR_SCANS, 'quantity' => 3,
        ]);
        $grant->benefits()->create([
            'benefit_type' => 'feature', 'feature_key' => 'omr',
        ]);

        $this->assertSame(
            5,
            app(EntitlementService::class)
                ->monthlyAllowance($teacher, MonthlyUsageService::OMR_SCANS, $organization),
        );
        Sanctum::actingAs($teacher);
        $this->getJson('/api/v1/exams')->assertOk();
    }

    public function test_effective_window_honors_grace_and_a_scheduled_downgrade(): void
    {
        [$organization, $currentPlan] = $this->organizationWithPlan('Atual', 20, 3);
        $downgrade = $this->plan('Free futuro', 5, 1);
        $teacher = $this->member($organization, 'teacher');
        $current = Subscription::query()->where('organization_id', $organization->id)->firstOrFail();
        $current->update([
            'status' => 'canceled', 'expires_at' => now()->subDay(),
            'cancelled_at' => now()->subHour(), 'grace_ends_at' => now()->addDay(),
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id, 'subscriber_type' => Organization::class,
            'subscriber_id' => $organization->id, 'plan_id' => $downgrade->id,
            'status' => 'scheduled', 'starts_at' => now()->addDays(2),
        ]);

        $entitlements = app(EntitlementService::class);
        $this->assertTrue($entitlements->effectivePlan($teacher, $organization)->is($currentPlan));
        $this->assertTrue($entitlements->effectivePlan($teacher, $organization, now()->addDays(3))->is($downgrade));
    }

    public function test_month_opening_and_admin_reset_follow_all_active_membership_scopes(): void
    {
        [$first] = $this->organizationWithPlan('Primeiro', 5, 1);
        [$second] = $this->organizationWithPlan('Segundo', 5, 1);
        $teacher = $this->member($first, 'teacher');
        $teacher->organizations()->attach($second->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $usage = app(MonthlyUsageService::class);
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 2, 'admin:first', organization: $first);
        $usage->consume($teacher, MonthlyUsageService::OMR_SCANS, 3, 'admin:second', organization: $second);

        $this->artisan('usage:open-month')->assertSuccessful();
        $this->assertDatabaseCount('usage_periods', count(MonthlyUsageService::RESOURCES) * 2);

        $admin = User::factory()->create(['type' => 'global_admin']);
        $admin->assignRole('global_admin');
        $this->actingAs($admin)->post(route('admin.usage.reset'), [
            'target_scope' => 'organization', 'target_id' => $second->id,
            'resource_keys' => [MonthlyUsageService::OMR_SCANS],
            'reason' => 'Reinício contextual solicitado para homologação.',
            'confirmation' => 'REDEFINIR LIMITES',
        ])->assertRedirect();

        $this->assertSame(2, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS, $first)['consumed']);
        $this->assertSame(0, $usage->snapshot($teacher, MonthlyUsageService::OMR_SCANS, $second)['consumed']);
    }

    public function test_month_opening_uses_contextual_teacher_role_not_global_account_type(): void
    {
        [$organization] = $this->organizationWithPlan('Papel contextual', 5, 1);
        $person = User::factory()->create([
            'organization_id' => $organization->id, 'type' => 'student', 'status' => 'active',
        ]);
        $person->organizations()->attach($organization->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->artisan('usage:open-month')->assertSuccessful();
        $this->assertSame(
            count(MonthlyUsageService::RESOURCES),
            UsagePeriod::query()->where('user_id', $person->id)->count(),
        );
    }

    public function test_month_opening_does_not_fallback_to_a_student_membership_for_global_teacher_type(): void
    {
        [$organization] = $this->organizationWithPlan('Aluno contextual', 5, 1);
        $person = User::factory()->create([
            'organization_id' => $organization->id, 'type' => 'teacher', 'status' => 'active',
        ]);
        $person->organizations()->attach($organization->id, [
            'role_in_org' => 'student', 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->artisan('usage:open-month')->assertSuccessful();
        $this->assertDatabaseMissing('usage_periods', ['user_id' => $person->id]);
    }

    public function test_legacy_adapter_uses_the_same_highest_plan_for_limit_and_feature(): void
    {
        [$first] = $this->organizationWithPlan('Primeiro', 2, 1);
        [$second, $highest] = $this->organizationWithPlan('Segundo', 50, 3);
        PlanFeature::query()->create([
            'plan_id' => $highest->id, 'feature_key' => 'sharing', 'enabled' => true,
        ]);
        $teacher = User::factory()->create(['type' => 'teacher', 'status' => 'active']);
        foreach ([$first, $second] as $organization) {
            $teacher->organizations()->attach($organization->id, [
                'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
            ]);
        }

        $resolver = new EffectivePlanResolver;
        $this->assertTrue($resolver->resolve($teacher)->is($highest));
        $this->assertSame(50, $resolver->resolveLimit($teacher, MonthlyUsageService::OMR_SCANS));
        $this->assertTrue($resolver->hasFeature($teacher, 'sharing'));
    }

    public function test_start_plan_with_zero_allowance_is_not_interpreted_as_unlimited(): void
    {
        $organization = Organization::query()->create(['name' => 'Sem plano', 'active' => true]);
        $teacher = $this->member($organization, 'teacher');
        $start = Plan::query()->create([
            'name' => 'Start', 'slug' => 'start', 'target_audience' => 'both',
            'price' => 0, 'original_price' => 0, 'tier_level' => 0, 'status' => 'active',
        ]);
        PlanLimit::query()->create([
            'plan_id' => $start->id, 'resource_key' => MonthlyUsageService::OMR_SCANS,
            'limit_value' => 0,
        ]);

        $this->assertSame(
            0,
            app(EntitlementService::class)
                ->monthlyAllowance($teacher, MonthlyUsageService::OMR_SCANS, $organization),
        );
    }

    public function test_catalog_start_plan_is_the_default_free_entitlement(): void
    {
        $organization = Organization::query()->create(['name' => 'Plano padrão', 'active' => true]);
        $teacher = $this->member($organization, 'teacher');
        $start = Plan::query()->create([
            'name' => 'Start', 'slug' => 'start', 'target_audience' => 'both',
            'price' => 0, 'original_price' => 0, 'tier_level' => 0, 'status' => 'active',
        ]);
        PlanLimit::query()->create([
            'plan_id' => $start->id, 'resource_key' => MonthlyUsageService::EXAM_PUBLICATIONS,
            'limit_value' => 3,
        ]);

        $this->assertSame(
            3,
            app(EntitlementService::class)
                ->monthlyAllowance($teacher, MonthlyUsageService::EXAM_PUBLICATIONS, $organization),
        );
    }

    public function test_legacy_direct_workspace_subscription_remains_effective(): void
    {
        $organization = Organization::query()->create(['name' => 'Legado', 'active' => true]);
        $plan = Plan::query()->create([
            'name' => 'Legado ilimitado', 'slug' => 'legado-'.uniqid(),
            'price' => 1, 'original_price' => 1, 'tier_level' => 1, 'status' => 'active',
        ]);
        Subscription::query()->create([
            'organization_id' => $organization->id, 'plan_id' => $plan->id,
            'status' => 'active', 'starts_at' => now(),
        ]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id, 'type' => 'teacher', 'status' => 'active',
        ]);

        $entitlements = app(EntitlementService::class);
        $this->assertTrue($entitlements->effectivePlan($teacher)->is($plan));
        $this->assertNull($entitlements->monthlyAllowance($teacher, MonthlyUsageService::OMR_SCANS));
    }

    public function test_billing_schedules_downgrade_and_keeps_cancellation_grace_effective(): void
    {
        [$organization, $currentPlan] = $this->organizationWithPlan('Premium', 20, 3, now()->addDays(10));
        $owner = $this->member($organization, 'admin');
        $organization->update(['owner_user_id' => $owner->id]);
        $downgrade = $this->plan('Básico', 5, 1);

        $this->actingAs($owner)->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.changePlan'), ['plan_id' => $downgrade->id])
            ->assertRedirect(route('institution.billing.index'));

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id, 'plan_id' => $currentPlan->id, 'status' => 'active',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id, 'plan_id' => $downgrade->id, 'status' => 'scheduled',
        ]);

        $this->actingAs($owner)->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.cancelPlan'), ['confirmation' => $organization->name])
            ->assertRedirect(route('institution.billing.index'));

        $canceled = Subscription::query()->where('organization_id', $organization->id)
            ->where('plan_id', $currentPlan->id)->firstOrFail();
        $this->assertSame('canceled', $canceled->status);
        $this->assertNotNull($canceled->grace_ends_at);
        $this->assertTrue(app(EntitlementService::class)->effectivePlan($owner->fresh(), $organization)->is($currentPlan));
        $graceEndsAt = $canceled->grace_ends_at->toISOString();
        $this->actingAs($owner)->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.cancelPlan'), ['confirmation' => $organization->name])
            ->assertSessionHasErrors();
        $this->assertSame($graceEndsAt, $canceled->fresh()->grace_ends_at->toISOString());

        $canceled->update([
            'status' => 'past_due', 'grace_ends_at' => now()->addDay(), 'expires_at' => now()->subDay(),
        ]);
        $pastDueGrace = $canceled->fresh()->grace_ends_at->toISOString();
        $this->actingAs($owner)->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.cancelPlan'), ['confirmation' => $organization->name])
            ->assertSessionHasErrors();
        $this->assertSame('past_due', $canceled->fresh()->status);
        $this->assertSame($pastDueGrace, $canceled->fresh()->grace_ends_at->toISOString());

        $teacherOnly = $this->plan('Somente professor', 99, 5);
        $teacherOnly->update(['target_audience' => 'teacher']);
        $this->actingAs($owner)->withSession(['workspace_id' => $organization->id])
            ->get(route('institution.billing.index'))
            ->assertOk()->assertSee('Trocar Plano')->assertDontSee('Somente professor');
    }

    /** @return array{Organization, Plan} */
    private function organizationWithPlan(
        string $name,
        int $limit,
        int $tier,
        $expiresAt = null,
    ): array {
        $organization = Organization::query()->create(['name' => $name, 'active' => true]);
        $plan = $this->plan($name.' Plan', $limit, $tier);
        Subscription::query()->create([
            'organization_id' => $organization->id, 'subscriber_type' => Organization::class,
            'subscriber_id' => $organization->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now()->subDay(), 'expires_at' => $expiresAt,
        ]);

        return [$organization, $plan];
    }

    private function plan(string $name, int $limit, int $tier): Plan
    {
        $plan = Plan::query()->create([
            'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(),
            'price' => 10, 'original_price' => 10, 'tier_level' => $tier, 'status' => 'active',
        ]);
        PlanLimit::query()->create([
            'plan_id' => $plan->id, 'resource_key' => MonthlyUsageService::OMR_SCANS, 'limit_value' => $limit,
        ]);
        foreach (['max_teachers', 'max_students', 'max_classes'] as $resource) {
            PlanLimit::query()->create([
                'plan_id' => $plan->id, 'resource_key' => $resource, 'limit_value' => 100,
            ]);
        }

        return $plan;
    }

    private function member(Organization $organization, string $role): User
    {
        $type = $role === 'admin' ? 'institution_admin' : 'teacher';
        $user = User::factory()->create([
            'organization_id' => $organization->id, 'type' => $type, 'status' => 'active',
        ]);
        $user->assignRole($type);
        $user->organizations()->attach($organization->id, [
            'role_in_org' => $role, 'status' => 'active', 'joined_at' => now(),
        ]);

        return $user;
    }
}
