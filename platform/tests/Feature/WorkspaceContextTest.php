<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student', 'guardian'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_switching_workspace_is_session_scoped_and_does_not_mutate_global_account(): void
    {
        $teacherWorkspace = $this->workspace('Espaço docente', 'personal');
        $studentWorkspace = $this->workspace('Escola B');
        $user = $this->user($teacherWorkspace, 'teacher');
        $this->link($user, $teacherWorkspace, 'teacher');
        $this->link($user, $studentWorkspace, 'student');

        $this->actingAs($user)
            ->post(route('workspaces.switch', $studentWorkspace))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('workspace_id', $studentWorkspace->id);

        $this->assertSame($teacherWorkspace->id, $user->fresh()->organization_id);

        $this->get(route('dashboard'))
            ->assertRedirect(route('student.dashboard'));
        $this->get(route('student.dashboard'))->assertOk();
        $this->get(route('institution.dashboard'))->assertForbidden();
    }

    public function test_user_without_a_selected_context_sees_selector_instead_of_unscoped_data(): void
    {
        $first = $this->workspace('Primeiro');
        $second = $this->workspace('Segundo');
        $user = $this->user(null, 'teacher');
        $this->link($user, $first, 'teacher');
        $this->link($user, $second, 'teacher');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('workspaces.index'));

        $this->get(route('workspaces.index'))
            ->assertOk()
            ->assertSee('Primeiro')
            ->assertSee('Segundo');
    }

    public function test_user_cannot_switch_to_a_workspace_without_membership(): void
    {
        $authorized = $this->workspace('Autorizado');
        $foreign = $this->workspace('Externo');
        $user = $this->user($authorized, 'teacher');
        $this->link($user, $authorized, 'teacher');

        $this->actingAs($user)
            ->post(route('workspaces.switch', $foreign))
            ->assertForbidden();
    }

    public function test_inactive_membership_never_reappears_through_the_legacy_workspace_id(): void
    {
        $workspace = $this->workspace('Inativo para o usuario');
        $user = $this->user($workspace, 'teacher');
        $this->link($user, $workspace, 'teacher');
        $user->organizations()->updateExistingPivot($workspace->id, ['status' => 'inactive']);

        $this->actingAs($user)
            ->get(route('workspaces.index'))
            ->assertForbidden();

        $this->assertTrue(app(WorkspaceContextService::class)->availableFor($user)->isEmpty());
    }

    public function test_api_header_selects_only_an_authorized_workspace_and_contextual_role(): void
    {
        $teacherWorkspace = $this->workspace('Docente');
        $studentWorkspace = $this->workspace('Aluno');
        $foreign = $this->workspace('Externo');
        $user = $this->user($teacherWorkspace, 'teacher');
        $this->link($user, $teacherWorkspace, 'teacher');
        $this->link($user, $studentWorkspace, 'student');
        Sanctum::actingAs($user);

        $this->withHeader('X-Workspace-Id', (string) $studentWorkspace->id)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.organization.id', $studentWorkspace->id)
            ->assertJsonPath('user.workspace_role', 'student');

        $this->withHeader('X-Workspace-Id', (string) $foreign->id)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_mobile_login_requires_and_accepts_an_explicit_workspace_for_ambiguous_accounts(): void
    {
        $first = $this->workspace('Primeiro');
        $second = $this->workspace('Segundo');
        $user = $this->user($first, 'teacher');
        $this->link($user, $first, 'teacher');
        $this->link($user, $second, 'teacher');
        $credentials = [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'scanner-test',
        ];

        $this->postJson('/api/v1/auth/login', $credentials)
            ->assertConflict()
            ->assertJsonPath('code', 'WORKSPACE_REQUIRED')
            ->assertJsonCount(2, 'workspaces');

        $this->withHeader('X-Workspace-Id', (string) $second->id)
            ->postJson('/api/v1/auth/login', $credentials)
            ->assertOk()
            ->assertJsonPath('user.organization.id', $second->id)
            ->assertJsonPath('user.workspace_role', 'teacher')
            ->assertJsonStructure(['token']);
    }

    public function test_authoring_routes_and_questions_follow_the_contextual_membership(): void
    {
        $first = $this->workspace('Escola A');
        $second = $this->workspace('Escola B');
        $user = $this->user($first, 'student');
        $this->link($user, $first, 'student');
        $this->link($user, $second, 'teacher');

        Question::create([
            'organization_id' => $first->id,
            'owner_id' => $user->id,
            'type' => 'essay',
            'content' => ['statement' => 'Questao exclusiva da Escola A'],
            'visibility_scope' => 'private',
        ]);
        Question::create([
            'organization_id' => $second->id,
            'owner_id' => $user->id,
            'type' => 'essay',
            'content' => ['statement' => 'Questao exclusiva da Escola B'],
            'visibility_scope' => 'private',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $second->id])
            ->get(route('questions.index'))
            ->assertOk()
            ->assertSee('Questao exclusiva da Escola B')
            ->assertDontSee('Questao exclusiva da Escola A');
    }

    public function test_omr_templates_are_visible_only_in_the_selected_workspace(): void
    {
        $current = $this->workspace('Atual');
        $foreign = $this->workspace('Externo');
        $user = $this->user($current, 'teacher');
        $other = $this->user($current, 'teacher');
        $this->link($user, $current, 'teacher');
        $this->link($user, $foreign, 'teacher');
        $this->link($other, $current, 'teacher');

        $owned = $this->template('Privado proprio', $current, $user, 'private');
        $public = $this->template('Publico atual', $current, $other, 'org_public');
        $privateOther = $this->template('Privado alheio', $current, $other, 'private');
        $foreignTemplate = $this->template('Publico externo', $foreign, $user, 'org_public');
        $system = $this->template('Template sistema', null, null, 'system', true);

        $user->setAttribute('organization_id', $current->id);
        $visibleIds = OmrTemplate::visible($user)->pluck('id');

        $this->assertTrue($visibleIds->contains($owned->id));
        $this->assertTrue($visibleIds->contains($public->id));
        $this->assertTrue($visibleIds->contains($system->id));
        $this->assertFalse($visibleIds->contains($privateOther->id));
        $this->assertFalse($visibleIds->contains($foreignTemplate->id));

        $this->actingAs($user)
            ->withSession(['workspace_id' => $current->id])
            ->get(route('institution.omr.templates.export', $foreignTemplate))
            ->assertForbidden();

        $globalAdmin = $this->user(null, 'global_admin');
        $this->actingAs($globalAdmin)
            ->withSession(['workspace_id' => $current->id])
            ->get(route('institution.omr.templates.edit', $system))
            ->assertForbidden();
    }

    public function test_omr_template_used_by_historical_copy_cannot_be_hard_deleted(): void
    {
        $workspace = $this->workspace('Histórico OMR');
        $teacher = $this->user($workspace, 'teacher');
        $this->link($teacher, $workspace, 'teacher');
        $template = $this->template('Template histórico', $workspace, $teacher, 'private');
        $exam = Exam::create([
            'organization_id' => $workspace->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação histórica',
            'status' => 'published',
        ]);
        ExamCopy::create([
            'exam_id' => $exam->id,
            'copy_number' => 1,
            'card_template_id' => $template->id,
            'card_template_version' => 1,
            'template_snapshot' => ['id' => $template->id, 'version' => 1, 'layout_config' => []],
            'questions_map' => [],
            'options_map' => [],
            'validation_hash' => str()->random(40),
        ]);

        $this->actingAs($teacher)
            ->withSession(['workspace_id' => $workspace->id])
            ->from(route('institution.omr.templates.index'))
            ->delete(route('institution.omr.templates.destroy', $template))
            ->assertRedirect(route('institution.omr.templates.index'))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('omr_templates', ['id' => $template->id]);
    }

    public function test_independent_teacher_can_open_personal_class_creation_but_institution_teacher_cannot(): void
    {
        $personal = $this->workspace('Pessoal', 'personal');
        $institution = $this->workspace('Institucional');
        $user = $this->user($personal, 'teacher');
        $this->link($user, $personal, 'teacher');
        $this->link($user, $institution, 'teacher');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $personal->id])
            ->get(route('institution.classes.create'))
            ->assertOk();

        $this->withSession(['workspace_id' => $institution->id])
            ->get(route('institution.classes.create'))
            ->assertForbidden();
    }

    public function test_independent_teacher_creates_a_user_owned_class_with_the_individual_plan(): void
    {
        $personal = $this->workspace('Pessoal', 'personal');
        $user = $this->user($personal, 'teacher');
        $this->link($user, $personal, 'teacher');
        $plan = Plan::create([
            'name' => 'Free professor',
            'slug' => 'free-professor',
            'price' => 0,
            'tier_level' => 0,
            'status' => 'active',
        ]);
        PlanLimit::create([
            'plan_id' => $plan->id,
            'resource_key' => 'max_classes',
            'limit_value' => 2,
        ]);
        Subscription::create([
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $personal->id])
            ->post(route('institution.classes.store'), ['name' => 'Minha turma', 'year' => '2026'])
            ->assertRedirect(route('institution.classes.index'));

        $class = SchoolClass::where('organization_id', $personal->id)->sole();
        $this->assertSame('user', $class->owner_type);
        $this->assertSame($user->id, (int) $class->owner_id);
    }

    public function test_only_the_workspace_owner_can_open_institution_billing(): void
    {
        $workspace = $this->workspace('Instituicao');
        $owner = $this->user($workspace, 'institution_admin');
        $delegate = $this->user($workspace, 'teacher');
        $this->link($owner, $workspace, 'admin');
        $this->link($delegate, $workspace, 'admin');
        $workspace->update(['owner_user_id' => $owner->id]);

        $this->actingAs($owner)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('institution.billing.index'))
            ->assertOk();

        $this->actingAs($delegate)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('institution.billing.index'))
            ->assertForbidden();
    }

    public function test_existing_teacher_can_create_one_personal_workspace_without_changing_the_legacy_default(): void
    {
        $institution = $this->workspace('Instituicao');
        $user = $this->user($institution, 'teacher');
        $this->link($user, $institution, 'teacher');

        $this->actingAs($user)
            ->post(route('workspaces.personal.store'))
            ->assertRedirect(route('dashboard'));

        $personal = Organization::where('workspace_type', 'personal')->sole();
        $this->assertSame($user->id, (int) $personal->owner_user_id);
        $this->assertTrue($user->belongsToActiveOrganization($personal->id, 'teacher'));
        $this->assertSame($institution->id, (int) $user->fresh()->organization_id);

        $this->post(route('workspaces.personal.store'))->assertRedirect(route('dashboard'));
        $this->assertSame(1, Organization::where('workspace_type', 'personal')->count());
    }

    private function workspace(string $name, string $type = 'institutional'): Organization
    {
        return Organization::create([
            'name' => $name,
            'workspace_type' => $type,
            'active' => true,
        ]);
    }

    private function user(?Organization $organization, string $type): User
    {
        $user = User::factory()->create([
            'organization_id' => $organization?->id,
            'type' => $type,
            'status' => 'active',
        ]);
        $user->assignRole($type);

        return $user;
    }

    private function link(User $user, Organization $organization, string $role): void
    {
        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role_in_org' => $role,
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);
    }

    private function template(
        string $name,
        ?Organization $organization,
        ?User $owner,
        string $visibility,
        bool $system = false,
    ): OmrTemplate {
        return OmrTemplate::create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(5),
            'organization_id' => $organization?->id,
            'created_by' => $owner?->id,
            'owner_type' => $owner ? User::class : null,
            'owner_id' => $owner?->id,
            'visibility_scope' => $visibility,
            'is_system' => $system,
            'is_default' => false,
            'width' => 1000,
            'height' => 1414,
            'corner_points_json' => [],
            'thresholds_json' => [],
        ]);
    }
}
