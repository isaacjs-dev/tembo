<?php

namespace Tests\Feature;

use App\Http\Middleware\RestrictLogAccess;
use App\Http\Middleware\RestrictTrashAccess;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BillingAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $r) {
            Role::findOrCreate($r, 'web');
        }
    }

    private function createOrg(array $extra = []): Organization
    {
        $plan = Plan::create([
            'name' => 'Plan '.uniqid(),
            'slug' => 'p-'.uniqid(),
            'price' => 99,
            'tier_level' => 1,
            'status' => 'active',
        ]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_teachers', 'limit_value' => 50]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_students', 'limit_value' => 200]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_classes', 'limit_value' => 20]);

        $org = Organization::create(array_merge(['name' => 'Org '.uniqid(), 'active' => true], $extra));
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return $org;
    }

    private function createAdmin(Organization $org): User
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'a-'.uniqid().'@t.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'type' => 'institution_admin',
            'status' => 'active',
        ]);
        $admin->assignRole('institution_admin');

        return $admin;
    }

    // ─── Billing Tests ───

    #[Test]
    public function billing_page_shows_current_plan_and_usage(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->get(route('institution.billing.index'));
        $response->assertOk();
        $response->assertViewHas('activeSubscription');
        $response->assertViewHas('usage');
        $response->assertViewHas('limits');
    }

    #[Test]
    public function admin_can_change_plan(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $newPlan = Plan::create([
            'name' => 'Premium',
            'slug' => 'premium-'.uniqid(),
            'price' => 199,
            'tier_level' => 2,
            'status' => 'active',
        ]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_teachers', 'limit_value' => 100]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_students', 'limit_value' => 500]);
        PlanLimit::create(['plan_id' => $newPlan->id, 'resource_key' => 'max_classes', 'limit_value' => 50]);

        $response = $this->actingAs($admin)->post(route('institution.billing.changePlan'), [
            'plan_id' => $newPlan->id,
        ]);

        $response->assertRedirect(route('institution.billing.index'));

        $activeSub = $org->subscriptions()->where('status', 'active')->first();
        $this->assertEquals($newPlan->id, $activeSub->plan_id);
    }

    #[Test]
    public function cancel_requires_correct_confirmation(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->post(route('institution.billing.cancelPlan'), [
            'confirmation' => 'wrong name',
        ]);

        $response->assertSessionHasErrors('confirmation');
    }

    #[Test]
    public function cancel_with_correct_name_works(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->post(route('institution.billing.cancelPlan'), [
            'confirmation' => $org->name,
        ]);

        $response->assertRedirect(route('institution.billing.index'));

        $sub = $org->subscriptions()->where('status', 'canceled')->first();
        $this->assertNotNull($sub);
        $this->assertNotNull($sub->expires_at);
    }

    #[Test]
    public function current_subscription_uses_the_latest_active_record_not_a_newer_canceled_one(): void
    {
        $org = $this->createOrg();
        $activeSubscription = $org->subscriptions()->where('status', 'active')->firstOrFail();

        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $activeSubscription->plan_id,
            'status' => 'canceled',
            'starts_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        $currentSubscription = $org->fresh()->subscription;

        $this->assertNotNull($currentSubscription);
        $this->assertSame($activeSubscription->id, $currentSubscription->id);
    }

    // ─── Trash Access Middleware Tests ───

    #[Test]
    public function trash_blocked_when_flag_disabled(): void
    {
        $org = $this->createOrg(['can_access_trash' => false]);
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->get(route('institution.trash.index'));
        $response->assertStatus(403);
    }

    #[Test]
    public function trash_allowed_when_flag_enabled(): void
    {
        $org = $this->createOrg(['can_access_trash' => true]);
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->get(route('institution.trash.index'));
        $response->assertOk();
    }

    #[Test]
    public function trash_allowed_for_manual_exception_user(): void
    {
        $org = $this->createOrg(['can_access_trash' => false]);
        $teacher = User::create([
            'name' => 'T',
            'email' => 'trash-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        // Adicionar como exceção manual
        $org->update(['trash_access_users' => [$teacher->id]]);

        $response = $this->actingAs($teacher)->get(route('institution.trash.index'));
        $response->assertOk();
    }

    // ─── Logs Access Middleware Tests ───

    #[Test]
    public function logs_blocked_when_flag_disabled(): void
    {
        $org = $this->createOrg(['can_access_logs' => false]);
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->get(route('institution.logs'));
        $response->assertStatus(403);
    }

    #[Test]
    public function global_admin_always_accesses_trash_and_logs(): void
    {
        $org = $this->createOrg(['can_access_trash' => false, 'can_access_logs' => false]);
        $globalAdmin = User::create([
            'name' => 'GA',
            'email' => 'ga-'.uniqid().'@t.com',
            'password' => bcrypt('p'),
            'organization_id' => $org->id,
            'type' => 'global_admin',
            'status' => 'active',
        ]);
        $globalAdmin->assignRole('global_admin');

        // Trash middleware passes
        $middleware = new RestrictTrashAccess;
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $globalAdmin);
        $response = $middleware->handle($request, fn () => response('ok'));
        $this->assertEquals(200, $response->getStatusCode());

        // Logs middleware passes
        $middleware2 = new RestrictLogAccess;
        $response2 = $middleware2->handle($request, fn () => response('ok'));
        $this->assertEquals(200, $response2->getStatusCode());
    }
}
