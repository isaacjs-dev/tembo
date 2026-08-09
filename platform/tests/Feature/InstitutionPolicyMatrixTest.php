<?php

namespace Tests\Feature;

use App\Models\InstitutionRole;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\InstitutionInviteNotification;
use App\Services\InviteManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstitutionPolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        Notification::fake();
    }

    public function test_director_can_manage_academic_members_but_not_roles_or_billing(): void
    {
        $organization = $this->organization();
        $director = $this->member($organization, 'director');

        $this->actingAs($director)->get(route('institution.teachers.index'))->assertOk();
        $this->actingAs($director)->get(route('institution.students.create'))->assertOk();
        $this->actingAs($director)->get(route('institution.classes.create'))->assertOk();
        $this->actingAs($director)->get(route('institution.roles.index'))->assertForbidden();
        $this->actingAs($director)->get(route('institution.billing.index'))->assertForbidden();
    }

    public function test_director_and_coordinator_cannot_promote_roles_above_their_matrix(): void
    {
        $organization = $this->organization();
        $director = $this->member($organization, 'director');
        $coordinator = $this->member($organization, 'coordinator');

        $this->actingAs($director)
            ->post(route('institution.invites.store'), [
                'email' => 'new-director@example.test',
                'target_role' => 'director',
            ])
            ->assertSessionHasErrors('target_role');

        $this->actingAs($director)
            ->post(route('institution.invites.store'), [
                'email' => 'new-coordinator@example.test',
                'target_role' => 'coordinator',
            ])
            ->assertRedirect(route('institution.invites.index'));

        $this->actingAs($coordinator)
            ->post(route('institution.invites.store'), [
                'email' => 'new-pedagogue@example.test',
                'target_role' => 'pedagogue',
            ])
            ->assertSessionHasErrors('target_role');

        $this->actingAs($coordinator)
            ->post(route('institution.invites.store'), [
                'email' => 'new-teacher@example.test',
                'target_role' => 'teacher',
            ])
            ->assertRedirect(route('institution.invites.index'));

        $this->assertDatabaseMissing('invites', ['invitee_email' => 'new-director@example.test']);
        $this->assertDatabaseMissing('invites', ['invitee_email' => 'new-pedagogue@example.test']);
    }

    public function test_pedagogue_has_read_access_but_not_member_management(): void
    {
        $organization = $this->organization();
        $pedagogue = $this->member($organization, 'pedagogue');

        $this->actingAs($pedagogue)->get(route('institution.teachers.index'))->assertOk();
        $this->actingAs($pedagogue)->get(route('institution.students.index'))->assertOk();
        $this->actingAs($pedagogue)->get(route('institution.classes.index'))->assertOk();
        $this->actingAs($pedagogue)->get(route('institution.teachers.create'))->assertForbidden();
        $this->actingAs($pedagogue)->get(route('institution.students.create'))->assertForbidden();
        $this->actingAs($pedagogue)->get(route('institution.classes.create'))->assertForbidden();
    }

    public function test_custom_role_permission_is_tenant_aware_and_requires_active_role(): void
    {
        $organization = $this->organization();
        $customRole = InstitutionRole::create([
            'organization_id' => $organization->id,
            'name' => 'Observador acadêmico',
        ]);
        $customRole->syncPermissions(['view_students']);
        $member = $this->member($organization, 'teacher', $customRole->id);

        $this->actingAs($member)->get(route('institution.students.index'))->assertOk();
        $this->actingAs($member)->get(route('institution.students.create'))->assertForbidden();

        $customRole->update(['is_active' => false]);

        $this->actingAs($member)->get(route('institution.students.index'))->assertForbidden();
    }

    public function test_role_assignment_rejects_foreign_user_and_foreign_role(): void
    {
        $organization = $this->organization();
        $otherOrganization = $this->organization();
        $admin = $this->member($organization, 'institution_admin');
        $localUser = $this->member($organization, 'teacher');
        $foreignUser = $this->member($otherOrganization, 'teacher');
        $localRole = InstitutionRole::create([
            'organization_id' => $organization->id,
            'name' => 'Cargo local',
        ]);
        $foreignRole = InstitutionRole::create([
            'organization_id' => $otherOrganization->id,
            'name' => 'Cargo externo',
        ]);

        $this->actingAs($admin)
            ->post(route('institution.roles.assign'), [
                'user_id' => $foreignUser->id,
                'role_id' => $localRole->id,
            ])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($admin)
            ->post(route('institution.roles.assign'), [
                'user_id' => $localUser->id,
                'role_id' => $foreignRole->id,
            ])
            ->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('user_organization', [
            'user_id' => $localUser->id,
            'organization_id' => $organization->id,
            'institution_role_id' => $foreignRole->id,
        ]);
    }

    public function test_new_invitee_sets_own_password_and_activates_membership(): void
    {
        Notification::fake();
        $organization = $this->organization();
        $admin = $this->member($organization, 'institution_admin');
        $invite = app(InviteManagerService::class)->send(
            $admin,
            'Invited-Director@Example.Test',
            'director',
            $organization,
            'org_director',
        );
        Notification::assertSentOnDemand(InstitutionInviteNotification::class);

        $this->get(route('invite.activation.show', $invite->token))
            ->assertOk()
            ->assertSee('invited-director@example.test')
            ->assertSee('Crie sua senha');

        $this->post(route('invite.activation.store', $invite->token), [
            'name' => 'Diretora Convidada',
            'password' => 'secure-password-2026',
            'password_confirmation' => 'secure-password-2026',
        ])->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'invited-director@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('secure-password-2026', $user->password));
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role_in_org' => 'director',
            'status' => 'active',
        ]);
        $this->assertSame('accepted', $invite->fresh()->status);
    }

    public function test_existing_account_is_not_recreated_by_activation_link(): void
    {
        $organization = $this->organization();
        $admin = $this->member($organization, 'institution_admin');
        $existing = User::factory()->create(['email' => 'existing@example.test']);
        $invite = app(InviteManagerService::class)->send(
            $admin,
            $existing->email,
            'teacher',
            $organization,
            'org_teacher',
            $existing->id,
        );

        $this->get(route('invite.activation.show', $invite->token))
            ->assertOk()
            ->assertSee('já possui conta')
            ->assertDontSee('Ativar minha conta');

        $this->post(route('invite.activation.store', $invite->token), [
            'name' => 'Outra pessoa',
            'password' => 'secure-password-2026',
            'password_confirmation' => 'secure-password-2026',
        ])->assertConflict();

        $this->assertSame(1, User::where('email', $existing->email)->count());
        $this->assertSame('pending', $invite->fresh()->status);
    }

    public function test_institutional_correction_is_web_only_for_administrative_roles(): void
    {
        $organization = $this->organization();
        $admin = $this->member($organization, 'institution_admin');

        $this->actingAs($admin)->get(route('institution.omr.index'))->assertOk();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/exams')
            ->assertForbidden()
            ->assertJsonPath(
                'error',
                'A correção institucional está disponível somente no ambiente Web.',
            );
    }

    public function test_invite_cannot_be_canceled_from_another_workspace(): void
    {
        $organization = $this->organization();
        $otherOrganization = $this->organization();
        $admin = $this->member($organization, 'institution_admin');
        $foreignAdmin = $this->member($otherOrganization, 'institution_admin');
        $invite = app(InviteManagerService::class)->send(
            $admin,
            'protected-invite@example.test',
            'teacher',
            $organization,
            'org_teacher',
        );

        $this->actingAs($foreignAdmin)
            ->delete(route('institution.invites.destroy', $invite))
            ->assertNotFound();

        $this->assertSame('pending', $invite->fresh()->status);
    }

    private function organization(): Organization
    {
        $plan = Plan::create([
            'name' => 'Plano '.uniqid(),
            'slug' => 'policy-'.uniqid(),
            'price' => 0,
            'tier_level' => 1,
            'status' => 'active',
        ]);
        foreach ([
            'max_teachers' => 50,
            'max_students' => 200,
            'max_classes' => 20,
            'max_extra_profiles' => 10,
        ] as $resource => $limit) {
            PlanLimit::create([
                'plan_id' => $plan->id,
                'resource_key' => $resource,
                'limit_value' => $limit,
            ]);
        }
        $organization = Organization::create([
            'name' => 'Instituição '.uniqid(),
            'active' => true,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        return $organization;
    }

    private function member(Organization $organization, string $role, ?int $institutionRoleId = null): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => in_array($role, ['teacher', 'student', 'institution_admin'], true) ? $role : 'teacher',
            'status' => 'active',
        ]);
        $user->assignRole(in_array($role, ['student', 'institution_admin'], true) ? $role : 'teacher');
        $user->organizations()->attach($organization->id, [
            'role_in_org' => $role,
            'status' => 'active',
            'joined_at' => now(),
            'institution_role_id' => $institutionRoleId,
        ]);

        return $user;
    }
}
