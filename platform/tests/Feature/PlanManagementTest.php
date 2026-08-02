<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimiterService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /* ────────────────────────────────────────────
     * 1. CRUD Admin de Planos
     * ──────────────────────────────────────────── */

    public function test_global_admin_can_view_plans_listing(): void
    {
        $admin = User::factory()->create(['type' => 'global_admin']);
        $admin->assignRole('global_admin');

        Plan::create(['name' => 'Test Plan', 'slug' => 'test-plan', 'price' => 10, 'original_price' => 10]);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));
        $response->assertStatus(200);
        $response->assertSee('Test Plan');
    }

    public function test_global_admin_can_create_plan(): void
    {
        $admin = User::factory()->create(['type' => 'global_admin']);
        $admin->assignRole('global_admin');

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), [
            'name' => 'New Plan',
            'target_audience' => 'both',
            'original_price' => 29.90,
            'sort_order' => 5,
            'tier_level' => 1,
            'is_visible' => 1,
            'is_most_popular' => 0,
            'limits' => [
                'max_students' => 100,
                'max_teachers' => 5,
                'max_classes' => 10,
                'max_exams' => 20,
            ],
            'features' => [
                'export_pdf' => 1,
                'omr' => 0,
                'sharing' => 1,
                'certificates' => 0,
            ],
        ]);

        $response->assertRedirect(route('admin.plans.index'));
        $this->assertDatabaseHas('plans', ['name' => 'New Plan', 'slug' => 'new-plan']);
        $this->assertDatabaseHas('plan_limits', [
            'resource_key' => 'max_students',
            'limit_value' => 100,
        ]);
        $this->assertDatabaseHas('plan_features', [
            'feature_key' => 'export_pdf',
            'enabled' => true,
        ]);
    }

    public function test_teacher_cannot_access_admin_plans(): void
    {
        $org = Organization::create(['name' => 'Test Org']);
        $teacher = User::factory()->create(['type' => 'teacher', 'organization_id' => $org->id]);
        $teacher->assignRole('teacher');

        $response = $this->actingAs($teacher)->get(route('admin.plans.index'));
        $response->assertStatus(403);
    }

    /* ────────────────────────────────────────────
     * 2. PlanLimiterService
     * ──────────────────────────────────────────── */

    public function test_plan_limiter_blocks_at_limit(): void
    {
        $plan = Plan::create([
            'name' => 'Limited',
            'slug' => 'limited',
            'price' => 10,
            'original_price' => 10,
        ]);

        PlanLimit::create([
            'plan_id' => $plan->id,
            'resource_key' => 'max_students',
            'limit_value' => 5,
        ]);

        $org = Organization::create(['name' => 'Test Org']);
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'subscriber_type' => Organization::class,
            'subscriber_id' => $org->id,
            'status' => 'active',
        ]);

        $service = new PlanLimiterService;

        // Abaixo do limite
        $this->assertTrue($service->canCreate($org, 'max_students', 3));
        $this->assertEquals(2, $service->remaining($org, 'max_students', 3));

        // No limite
        $this->assertFalse($service->canCreate($org, 'max_students', 5));
        $this->assertEquals(0, $service->remaining($org, 'max_students', 5));

        // Acima do limite
        $this->assertFalse($service->canCreate($org, 'max_students', 7));
    }

    public function test_plan_limiter_allows_unlimited(): void
    {
        $plan = Plan::create([
            'name' => 'Unlimited',
            'slug' => 'unlimited',
            'price' => 99,
            'original_price' => 99,
        ]);

        PlanLimit::create([
            'plan_id' => $plan->id,
            'resource_key' => 'max_students',
            'limit_value' => null, // ilimitado
        ]);

        $org = Organization::create(['name' => 'Unlimited Org']);
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'subscriber_type' => Organization::class,
            'subscriber_id' => $org->id,
            'status' => 'active',
        ]);

        $service = new PlanLimiterService;
        $this->assertTrue($service->canCreate($org, 'max_students', 99999));
        $this->assertNull($service->remaining($org, 'max_students', 99999));
    }

    /* ────────────────────────────────────────────
     * 3. Visibilidade de Planos na Home
     * ──────────────────────────────────────────── */

    public function test_only_visible_active_plans_appear_on_home(): void
    {
        Plan::create([
            'name' => 'Visible',
            'slug' => 'visible',
            'price' => 10,
            'original_price' => 10,
            'is_visible' => true,
            'status' => 'active',
        ]);

        Plan::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'price' => 10,
            'original_price' => 10,
            'is_visible' => false,
            'status' => 'active',
        ]);

        Plan::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'price' => 10,
            'original_price' => 10,
            'is_visible' => true,
            'status' => 'inactive',
        ]);

        $visiblePlans = Plan::visibleOnHome()->get();
        $this->assertCount(1, $visiblePlans);
        $this->assertEquals('Visible', $visiblePlans->first()->name);
    }

    /* ────────────────────────────────────────────
     * 4. Plan Model Helpers
     * ──────────────────────────────────────────── */

    public function test_effective_price_returns_promo_when_active(): void
    {
        $plan = Plan::create([
            'name' => 'Promo Plan',
            'slug' => 'promo-plan',
            'price' => 50,
            'original_price' => 50,
            'promotional_price' => 30,
            'promo_starts_at' => now()->subDay(),
            'promo_ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals('30.00', $plan->effective_price);
    }

    public function test_effective_price_returns_original_when_promo_expired(): void
    {
        $plan = Plan::create([
            'name' => 'Expired Promo',
            'slug' => 'expired-promo',
            'price' => 50,
            'original_price' => 50,
            'promotional_price' => 30,
            'promo_starts_at' => now()->subMonth(),
            'promo_ends_at' => now()->subDay(),
        ]);

        $this->assertEquals('50.00', $plan->effective_price);
    }

    public function test_plan_has_feature_via_normalized_table(): void
    {
        $plan = Plan::create([
            'name' => 'Feature Plan',
            'slug' => 'feature-plan',
            'price' => 10,
            'original_price' => 10,
        ]);

        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'export_pdf',
            'enabled' => true,
        ]);

        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'certificates',
            'enabled' => false,
        ]);

        $this->assertTrue($plan->hasFeature('export_pdf'));
        $this->assertFalse($plan->hasFeature('certificates'));
    }
}
