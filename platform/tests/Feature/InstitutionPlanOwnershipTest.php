<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionPlanOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_only_owner_can_read_change_and_cancel_institution_plan(): void
    {
        [$organization, $currentPlan] = $this->organizationWithPlan();
        $owner = $this->member($organization, 'admin');
        $organization->update(['owner_user_id' => $owner->id]);
        $newPlan = $this->plan('Plano superior', 2);

        foreach (['director', 'coordinator', 'pedagogue', 'admin', 'global_admin'] as $role) {
            $delegate = $this->member($organization, $role);

            $this->actingAs($delegate)
                ->withSession(['workspace_id' => $organization->id])
                ->get(route('institution.billing.index'))
                ->assertForbidden();

            $this->actingAs($delegate)
                ->withSession(['workspace_id' => $organization->id])
                ->post(route('institution.billing.changePlan'), ['plan_id' => $newPlan->id])
                ->assertForbidden();

            $this->actingAs($delegate)
                ->withSession(['workspace_id' => $organization->id])
                ->post(route('institution.billing.cancelPlan'), ['confirmation' => $organization->name])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $currentPlan->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.changePlan'), ['plan_id' => $newPlan->id])
            ->assertRedirect(route('institution.billing.index'));

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $organization->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $organization->id])
            ->post(route('institution.billing.cancelPlan'), ['confirmation' => $organization->name])
            ->assertRedirect(route('institution.billing.index'));

        $this->assertDatabaseMissing('subscriptions', [
            'organization_id' => $organization->id,
            'status' => 'active',
        ]);
    }

    public function test_billing_navigation_is_visible_only_to_the_owner(): void
    {
        [$organization] = $this->organizationWithPlan();
        $owner = $this->member($organization, 'admin');
        $delegate = $this->member($organization, 'admin');
        $organization->update(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $organization->id])
            ->get(route('institution.dashboard'))
            ->assertOk()
            ->assertSee('Assinatura');

        $this->actingAs($delegate)
            ->withSession(['workspace_id' => $organization->id])
            ->get(route('institution.dashboard'))
            ->assertOk()
            ->assertDontSee('Assinatura');
    }

    public function test_inactive_owner_membership_cannot_manage_plan(): void
    {
        [$organization] = $this->organizationWithPlan();
        $owner = $this->member($organization, 'admin');
        $organization->update(['owner_user_id' => $owner->id]);
        $owner->organizations()->updateExistingPivot($organization->id, ['status' => 'inactive']);

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $organization->id])
            ->get(route('institution.billing.index'))
            ->assertForbidden();
    }

    /** @return array{Organization, Plan} */
    private function organizationWithPlan(): array
    {
        $organization = Organization::create([
            'name' => 'Instituição '.uniqid(),
            'active' => true,
        ]);
        $plan = $this->plan('Plano atual', 1);
        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return [$organization, $plan];
    }

    private function plan(string $name, int $tier): Plan
    {
        $plan = Plan::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.uniqid(),
            'price' => $tier * 100,
            'tier_level' => $tier,
            'status' => 'active',
        ]);
        foreach (['max_teachers' => 100, 'max_students' => 500, 'max_classes' => 50] as $resource => $limit) {
            PlanLimit::create([
                'plan_id' => $plan->id,
                'resource_key' => $resource,
                'limit_value' => $limit,
            ]);
        }

        return $plan;
    }

    private function member(Organization $organization, string $role): User
    {
        $globalType = $role === 'global_admin'
            ? 'global_admin'
            : ($role === 'admin' ? 'institution_admin' : 'teacher');
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => $globalType,
            'status' => 'active',
        ]);
        $user->assignRole($globalType);
        $user->organizations()->attach($organization->id, [
            'role_in_org' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return $user;
    }
}
