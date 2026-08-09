<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Services\EffectivePlanResolver;
use App\Services\InviteManagerService;
use App\Services\UserLinkerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InvitesAndLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createOrgWithPlan(string $planName = 'Pro', int $tierLevel = 1): array
    {
        $plan = Plan::create([
            'name' => $planName,
            'slug' => strtolower($planName),
            'price' => 49.90,
            'original_price' => 49.90,
            'tier_level' => $tierLevel,
            'status' => 'active',
        ]);

        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_teachers', 'limit_value' => 5]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_students', 'limit_value' => 50]);

        $org = Organization::create(['name' => 'Test Org']);
        Subscription::create([
            'organization_id' => $org->id,
            'plan_id' => $plan->id,
            'subscriber_type' => Organization::class,
            'subscriber_id' => $org->id,
            'status' => 'active',
        ]);

        return [$org, $plan];
    }

    /* ────────────────────────────────────────────
     * 1. Ciclo Completo de Convite
     * ──────────────────────────────────────────── */

    public function test_full_invite_cycle(): void
    {
        [$org, $plan] = $this->createOrgWithPlan();

        $inviter = User::factory()->create(['type' => 'institution_admin', 'organization_id' => $org->id]);
        $inviter->assignRole('institution_admin');

        $invitee = User::factory()->create(['type' => 'teacher', 'email' => 'teacher@test.com']);
        $invitee->assignRole('teacher');

        // Enviar convite
        $service = new InviteManagerService;
        $invite = $service->send($inviter, 'teacher@test.com', 'teacher', $org);

        $this->assertDatabaseHas('invites', [
            'invitee_email' => 'teacher@test.com',
            'status' => 'pending',
            'organization_id' => $org->id,
        ]);
        $this->assertEquals(64, strlen($invite->token));

        // Aceitar convite
        $linker = new UserLinkerService;
        $linker->acceptInvite($invite, $invitee);

        $this->assertDatabaseHas('invites', ['id' => $invite->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $invitee->id,
            'organization_id' => $org->id,
            'role_in_org' => 'teacher',
            'status' => 'active',
        ]);
    }

    public function test_decline_invite(): void
    {
        [$org, $plan] = $this->createOrgWithPlan();

        $inviter = User::factory()->create([
            'type' => 'institution_admin',
            'organization_id' => $org->id,
        ]);
        $inviter->assignRole('institution_admin');
        $invitee = User::factory()->create(['email' => 'decline@test.com']);

        $service = new InviteManagerService;
        $invite = $service->send($inviter, 'decline@test.com', 'teacher', $org);

        $linker = new UserLinkerService;
        $linker->declineInvite($invite, $invitee);

        $this->assertDatabaseHas('invites', ['id' => $invite->id, 'status' => 'declined']);
        $this->assertDatabaseMissing('user_organization', [
            'user_id' => $invitee->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_duplicate_invite_is_blocked(): void
    {
        [$org, $plan] = $this->createOrgWithPlan();
        $inviter = User::factory()->create([
            'type' => 'institution_admin',
            'organization_id' => $org->id,
        ]);
        $inviter->assignRole('institution_admin');

        $service = new InviteManagerService;
        $service->send($inviter, 'dup@test.com', 'teacher', $org);

        $this->expectException(ValidationException::class);
        $service->send($inviter, 'dup@test.com', 'teacher', $org);
    }

    /* ────────────────────────────────────────────
     * 2. Desligamento
     * ──────────────────────────────────────────── */

    public function test_unlink_revokes_access(): void
    {
        [$org, $plan] = $this->createOrgWithPlan();

        $teacher = User::factory()->create(['organization_id' => $org->id]);
        $teacher->organizations()->attach($org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $admin = User::factory()->create([
            'type' => 'institution_admin',
            'organization_id' => $org->id,
        ]);
        $admin->assignRole('institution_admin');

        $linker = new UserLinkerService;
        $linker->unlink($admin, $teacher, $org->id);

        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $org->id,
            'status' => 'inactive',
        ]);
    }

    /* ────────────────────────────────────────────
     * 3. EffectivePlanResolver
     * ──────────────────────────────────────────── */

    public function test_effective_plan_picks_highest_tier(): void
    {
        // Org 1: tier 1
        [$org1, $plan1] = $this->createOrgWithPlan('Basic', 1);
        // Org 2: tier 3
        [$org2, $plan2] = $this->createOrgWithPlan('Enterprise', 3);

        $teacher = User::factory()->create();
        $teacher->organizations()->attach($org1->id, ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()]);
        $teacher->organizations()->attach($org2->id, ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()]);

        $resolver = new EffectivePlanResolver;
        $effective = $resolver->resolve($teacher);

        $this->assertNotNull($effective);
        $this->assertEquals('Enterprise', $effective->name);
        $this->assertEquals(3, $effective->tier_level);
    }

    public function test_effective_plan_includes_individual(): void
    {
        $plan = Plan::create([
            'name' => 'Individual Gold',
            'slug' => 'individual-gold',
            'price' => 99,
            'original_price' => 99,
            'tier_level' => 5,
            'status' => 'active',
        ]);

        $user = User::factory()->create();
        Subscription::create([
            'plan_id' => $plan->id,
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'status' => 'active',
        ]);

        $resolver = new EffectivePlanResolver;
        $effective = $resolver->resolve($user);

        $this->assertEquals('Individual Gold', $effective->name);
    }

    /* ────────────────────────────────────────────
     * 4. Artisan Command invites:expire
     * ──────────────────────────────────────────── */

    public function test_artisan_expires_old_invites(): void
    {
        Invite::create([
            'inviter_id' => User::factory()->create()->id,
            'invitee_email' => 'old@test.com',
            'target_role' => 'teacher',
            'expires_at' => now()->subDay(),
        ]);

        Invite::create([
            'inviter_id' => User::factory()->create()->id,
            'invitee_email' => 'new@test.com',
            'target_role' => 'teacher',
            'expires_at' => now()->addWeek(),
        ]);

        $this->artisan('invites:expire')->assertSuccessful();

        $this->assertDatabaseHas('invites', ['invitee_email' => 'old@test.com', 'status' => 'expired']);
        $this->assertDatabaseHas('invites', ['invitee_email' => 'new@test.com', 'status' => 'pending']);
    }
}
