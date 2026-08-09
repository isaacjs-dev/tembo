<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UserFinderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LinkCodeAndInviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles needed
        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $r) {
            Role::findOrCreate($r, 'web');
        }
        Notification::fake();
    }

    private function createOrg(): Organization
    {
        $plan = Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan-'.uniqid(),
            'price' => 99,
            'tier_level' => 1,
            'status' => 'active',
        ]);

        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_teachers', 'limit_value' => 50]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_students', 'limit_value' => 200]);
        PlanLimit::create(['plan_id' => $plan->id, 'resource_key' => 'max_classes', 'limit_value' => 20]);

        $org = Organization::create([
            'name' => 'Test Org '.uniqid(),
            'subdomain' => 'test-'.uniqid(),
            'active' => true,
        ]);

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
            'name' => 'Admin Test',
            'email' => 'admin-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'organization_id' => $org->id,
            'type' => 'institution_admin',
            'status' => 'active',
        ]);
        $admin->assignRole('institution_admin');

        return $admin;
    }

    // ─── Link Code Tests ───

    #[Test]
    public function user_gets_link_code_on_creation(): void
    {
        $user = User::create([
            'name' => 'Test',
            'email' => 'lc-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'teacher',
        ]);

        $this->assertNotNull($user->link_code);
        $this->assertEquals(8, strlen($user->link_code));
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $user->link_code);
    }

    #[Test]
    public function link_code_is_unique(): void
    {
        $user1 = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@t.com', 'password' => bcrypt('p'), 'type' => 'teacher']);
        $user2 = User::create(['name' => 'B', 'email' => 'b-'.uniqid().'@t.com', 'password' => bcrypt('p'), 'type' => 'teacher']);

        $this->assertNotEquals($user1->link_code, $user2->link_code);
    }

    #[Test]
    public function link_code_generation_is_correct_format(): void
    {
        $code = UserFinderService::generateLinkCode();
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{8}$/', $code);
    }

    // ─── UserFinderService Tests ───

    #[Test]
    public function finder_finds_user_by_email(): void
    {
        User::create(['name' => 'Prof', 'email' => 'findme@test.com', 'password' => bcrypt('p'), 'type' => 'teacher']);
        $finder = new UserFinderService;
        $result = $finder->search('findme@test.com');

        $this->assertTrue($result['found']);
        $this->assertEquals('invite', $result['suggestion']);
    }

    #[Test]
    public function finder_finds_user_by_link_code(): void
    {
        $user = User::create(['name' => 'Prof', 'email' => 'code-'.uniqid().'@t.com', 'password' => bcrypt('p'), 'type' => 'teacher']);
        $finder = new UserFinderService;
        $result = $finder->search($user->link_code);

        $this->assertTrue($result['found']);
        $this->assertEquals($user->id, $result['user']->id);
    }

    #[Test]
    public function finder_returns_not_found_for_unknown_email(): void
    {
        $finder = new UserFinderService;
        $result = $finder->search('nobody@test.com');

        $this->assertFalse($result['found']);
        $this->assertEquals('create', $result['suggestion']);
    }

    #[Test]
    public function finder_detects_already_linked_user(): void
    {
        $org = $this->createOrg();
        $teacher = User::create(['name' => 'T', 'email' => 'linked-'.uniqid().'@t.com', 'password' => bcrypt('p'), 'type' => 'teacher']);
        $teacher->assignRole('teacher');
        $teacher->organizations()->attach($org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $finder = new UserFinderService;
        $this->assertTrue($finder->isAlreadyLinked($teacher, $org->id));
    }

    // ─── Teacher Create vs Invite Tests ───

    #[Test]
    public function institution_can_create_new_teacher(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->post(route('institution.teachers.store'), [
            'name' => 'Novo Professor',
            'email' => 'novoprof-'.uniqid().'@test.com',
            'password' => 'senha12345',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('institution.teachers.index'));
        $teacher = User::where('name', 'Novo Professor')->first();
        $this->assertNotNull($teacher);
        $this->assertEquals('teacher', $teacher->type);
        $this->assertNotNull($teacher->link_code);

        $this->assertDatabaseHas('user_organization', [
            'user_id' => $teacher->id,
            'organization_id' => $org->id,
            'role_in_org' => 'teacher',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function institution_created_teacher_must_verify_and_replace_the_provisional_password(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $email = 'login-prof-'.uniqid().'@test.com';

        $this->actingAs($admin)->post(route('institution.teachers.store'), [
            'name' => 'Professor Login',
            'email' => $email,
            'password' => 'senha12345',
            'status' => 'active',
        ])->assertRedirect(route('institution.teachers.index'));

        $this->post('/logout')->assertRedirect('/');

        $teacher = User::where('email', $email)->firstOrFail();
        $this->post('/login', [
            'email' => $email,
            'password' => 'senha12345',
        ])->assertRedirect(route('institution.dashboard', absolute: false));

        $this->assertAuthenticatedAs($teacher);
        $this->get(route('institution.dashboard'))
            ->assertRedirect(route('verification.notice'));
        $this->assertTrue((bool) ($teacher->settings['must_change_password'] ?? false));
    }

    #[Test]
    public function institution_can_invite_existing_teacher(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $existingTeacher = User::create([
            'name' => 'Existing T',
            'email' => 'existing-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'teacher',
        ]);
        $existingTeacher->assignRole('teacher');

        $response = $this->actingAs($admin)->post(route('institution.teachers.store'), [
            'invite_user_id' => $existingTeacher->id,
        ]);

        $response->assertRedirect(route('institution.teachers.index'));

        $this->assertDatabaseHas('invites', [
            'invitee_email' => $existingTeacher->email,
            'invitee_user_id' => $existingTeacher->id,
            'invite_type' => 'org_teacher',
            'target_role' => 'teacher',
            'organization_id' => $org->id,
            'status' => 'pending',
        ]);

        // No direct link yet
        $this->assertDatabaseMissing('user_organization', [
            'user_id' => $existingTeacher->id,
            'organization_id' => $org->id,
        ]);
    }

    #[Test]
    public function invite_blocks_if_already_linked(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        $teacher = User::create([
            'name' => 'Linked T',
            'email' => 'linked2-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'type' => 'teacher',
        ]);
        $teacher->assignRole('teacher');
        $teacher->organizations()->attach($org->id, [
            'role_in_org' => 'teacher',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('institution.teachers.store'), [
            'invite_user_id' => $teacher->id,
        ]);

        $response->assertSessionHasErrors();
    }

    // ─── Search AJAX Tests ───

    #[Test]
    public function search_returns_found_user_by_email(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);
        User::create(['name' => 'S', 'email' => 'searchable@test.com', 'password' => bcrypt('p'), 'type' => 'teacher']);

        $response = $this->actingAs($admin)->postJson(route('institution.teachers.search'), [
            'search' => 'searchable@test.com',
        ]);

        $response->assertOk()->assertJson(['found' => true, 'user' => ['email' => 'searchable@test.com']]);
    }

    #[Test]
    public function search_returns_not_found_for_new_email(): void
    {
        $org = $this->createOrg();
        $admin = $this->createAdmin($org);

        $response = $this->actingAs($admin)->postJson(route('institution.teachers.search'), [
            'search' => 'inexistente@test.com',
        ]);

        $response->assertOk()->assertJson(['found' => false, 'suggestion' => 'create']);
    }
}
