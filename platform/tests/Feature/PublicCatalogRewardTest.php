<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\PublicCatalogRewardAward;
use App\Models\PublicCatalogRewardRule;
use App\Models\Question;
use App\Models\UsageEvent;
use App\Models\User;
use App\Services\MonthlyUsageService;
use App\Services\PublicCatalogRewardService;
use App\Services\PublicCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicCatalogRewardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $author;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['teacher', 'global_admin'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->organization = Organization::query()->create(['name' => 'Escola autora', 'active' => true]);
        $this->author = User::factory()->create([
            'organization_id' => $this->organization->id, 'type' => 'teacher', 'status' => 'active',
        ]);
        $this->author->assignRole('teacher');
        $this->author->organizations()->attach($this->organization->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $this->moderator = User::factory()->create([
            'organization_id' => $this->organization->id, 'type' => 'global_admin', 'status' => 'active',
        ]);
        $this->moderator->assignRole('global_admin');

        $plan = Plan::query()->create([
            'name' => 'Start', 'slug' => 'start', 'status' => 'active', 'target_audience' => 'both',
            'price' => 0, 'original_price' => 0, 'tier_level' => 0,
        ]);
        $plan->planLimits()->create([
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'limit_value' => 2,
        ]);
    }

    public function test_approval_grants_one_versioned_contextual_credit_and_retry_is_idempotent(): void
    {
        $rule = $this->rule(amount: 3, userCap: 10);
        $submission = $this->approve($this->question('Questão premiada'));

        $award = PublicCatalogRewardAward::query()->sole();
        $this->assertSame($rule->id, $award->public_catalog_reward_rule_id);
        $this->assertSame('reward-v1', $award->rule_version);
        $this->assertSame('organization:'.$this->organization->id, $award->scope_key);
        $this->assertSame(3, $award->awarded_amount);
        $this->assertSame('granted', $award->status);
        $this->assertNotNull($award->membership_id);
        $this->assertDatabaseHas('usage_events', [
            'id' => $award->usage_event_id,
            'event_type' => 'credit',
            'amount' => 3,
            'idempotency_key' => "public-approval:{$submission->id}:reward-v1",
        ]);
        $snapshot = app(MonthlyUsageService::class)->snapshot(
            $this->author, MonthlyUsageService::QUESTIONS_CREATED, $this->organization,
        );
        $this->assertSame(5, $snapshot['limit']);

        $retry = app(PublicCatalogRewardService::class)->grantForApproval($submission, $this->moderator);
        $this->assertTrue($award->is($retry));
        $this->assertDatabaseCount('public_catalog_reward_awards', 1);
        $this->assertSame(1, UsageEvent::query()->where('event_type', 'credit')->count());
    }

    public function test_monthly_user_and_global_caps_grant_partial_then_record_capped_decision(): void
    {
        $this->rule(amount: 3, userCap: 5, globalCap: 5);

        $this->approve($this->question('Primeira questão'));
        $this->approve($this->question('Segunda questão'));
        $this->approve($this->question('Terceira questão'));

        $this->assertSame([3, 2, 0], PublicCatalogRewardAward::query()->orderBy('id')->pluck('awarded_amount')->all());
        $this->assertSame(['granted', 'partial', 'capped'], PublicCatalogRewardAward::query()->orderBy('id')->pluck('status')->all());
        $this->assertSame(5, UsageEvent::query()->where('event_type', 'credit')->sum('amount'));
        $this->assertDatabaseCount('public_catalog_reward_awards', 3);
    }

    public function test_no_active_rule_has_no_financial_effect_and_inactive_membership_is_fail_closed(): void
    {
        $this->approve($this->question('Sem regra'));
        $this->assertDatabaseCount('public_catalog_reward_awards', 0);
        $this->assertDatabaseCount('usage_events', 0);

        $this->rule(amount: 2, userCap: 10);
        $this->author->organizations()->updateExistingPivot($this->organization->id, ['status' => 'inactive']);
        $this->approve($this->question('Membership revogada'));

        $this->assertDatabaseHas('public_catalog_reward_awards', [
            'user_id' => $this->author->id, 'status' => 'ineligible', 'awarded_amount' => 0,
        ]);
        $this->assertDatabaseCount('usage_events', 0);
    }

    public function test_scheduled_rule_keeps_current_reward_and_caps_continue_across_versions(): void
    {
        $first = $this->rule(amount: 3, userCap: 5);
        $future = PublicCatalogRewardRule::query()->create([
            'name' => 'Regra futura', 'rule_version' => 'reward-v2', 'subject_kind' => 'question',
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'credit_amount' => 4,
            'per_user_monthly_cap' => 5, 'global_monthly_cap' => 100,
            'status' => 'draft', 'starts_at' => now()->addDay(), 'created_by' => $this->moderator->id,
        ]);

        $this->actingAs($this->moderator)
            ->post(route('admin.public-catalog.reward-rules.activate', $future))->assertRedirect();
        $this->assertSame('active', $first->fresh()->status);
        $this->assertSame('scheduled', $future->fresh()->status);
        $this->approve($this->question('Antes da regra futura'));
        $this->assertDatabaseHas('public_catalog_reward_awards', [
            'rule_version' => 'reward-v1', 'awarded_amount' => 3,
        ]);

        $replacement = PublicCatalogRewardRule::query()->create([
            'name' => 'Correção imediata', 'rule_version' => 'reward-v3', 'subject_kind' => 'question',
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'credit_amount' => 4,
            'per_user_monthly_cap' => 5, 'global_monthly_cap' => 100,
            'status' => 'draft', 'created_by' => $this->moderator->id,
        ]);
        $this->post(route('admin.public-catalog.reward-rules.activate', $replacement))->assertRedirect();
        $this->approve($this->question('Depois da troca de versão'));

        $this->assertSame([3, 2], PublicCatalogRewardAward::query()->orderBy('id')->pluck('awarded_amount')->all());
        $this->assertSame(5, UsageEvent::query()->where('event_type', 'credit')->sum('amount'));

        $this->travel(2)->days();
        $this->approve($this->question('Após início da agendada'));
        $this->assertSame('retired', $replacement->fresh()->status);
        $this->assertSame('active', $future->fresh()->status);

        $immediate = PublicCatalogRewardRule::query()->create([
            'name' => 'Substituta pós-agendamento', 'rule_version' => 'reward-v4', 'subject_kind' => 'question',
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'credit_amount' => 4,
            'per_user_monthly_cap' => 5, 'global_monthly_cap' => 100,
            'status' => 'draft', 'created_by' => $this->moderator->id,
        ]);
        $this->post(route('admin.public-catalog.reward-rules.activate', $immediate))->assertRedirect();
        $this->approve($this->question('Após substituição da agendada'));

        $this->assertSame('retired', $future->fresh()->status);
        $this->assertSame('active', $immediate->fresh()->status);
        $this->assertSame(
            ['reward-v1', 'reward-v3', 'reward-v2', 'reward-v4'],
            PublicCatalogRewardAward::query()->orderBy('id')->pluck('rule_version')->all(),
        );
        $this->assertSame([3, 2, 0, 0], PublicCatalogRewardAward::query()->orderBy('id')->pluck('awarded_amount')->all());
    }

    public function test_legacy_direct_workspace_without_any_membership_remains_eligible(): void
    {
        $this->rule(amount: 2, userCap: 10);
        $legacy = User::factory()->create([
            'organization_id' => $this->organization->id, 'type' => 'teacher', 'status' => 'active',
        ]);
        $legacy->assignRole('teacher');
        $question = Question::query()->create([
            'organization_id' => $this->organization->id, 'owner_id' => $legacy->id,
            'type' => 'multiple_choice', 'visibility_scope' => 'private',
            'content' => ['statement' => 'Questão legada', 'options' => ['A', 'B'], 'correct_option' => 0],
            'level' => 'medium', 'stage' => 'ef_finais', 'grade' => '6',
        ]);

        $this->approve($question, $legacy);

        $this->assertDatabaseHas('public_catalog_reward_awards', [
            'user_id' => $legacy->id, 'membership_id' => null, 'status' => 'granted', 'awarded_amount' => 2,
        ]);
    }

    public function test_global_admin_creates_and_activates_immutable_versions_while_regular_user_is_forbidden(): void
    {
        $payload = [
            'name' => 'Bônus de questão', 'rule_version' => '2026.08-v2', 'subject_kind' => 'question',
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'credit_amount' => 2,
            'per_user_monthly_cap' => 8, 'global_monthly_cap' => 100,
        ];
        $this->actingAs($this->author)->post(route('admin.public-catalog.reward-rules.store'), $payload)->assertForbidden();
        $this->actingAs($this->moderator)->post(route('admin.public-catalog.reward-rules.store'), $payload)->assertRedirect();
        $rule = PublicCatalogRewardRule::query()->sole();
        $this->assertSame('draft', $rule->status);
        $this->post(route('admin.public-catalog.reward-rules.activate', $rule))->assertRedirect();
        $this->assertSame('active', $rule->fresh()->status);

        $second = PublicCatalogRewardRule::query()->create([
            ...$payload, 'name' => 'Nova versão', 'rule_version' => '2026.09-v1',
            'status' => 'draft', 'created_by' => $this->moderator->id,
        ]);
        $this->post(route('admin.public-catalog.reward-rules.activate', $second))->assertRedirect();
        $this->assertSame('retired', $rule->fresh()->status);
        $this->assertSame('active', $second->fresh()->status);
        $this->get(route('admin.public-catalog.index'))
            ->assertOk()->assertSee('Recompensas por colaboração')->assertSee('2026.09-v1');
    }

    private function rule(int $amount, int $userCap, ?int $globalCap = null): PublicCatalogRewardRule
    {
        $rule = PublicCatalogRewardRule::query()->create([
            'name' => 'Regra de teste', 'rule_version' => 'reward-v1', 'subject_kind' => 'question',
            'resource_key' => MonthlyUsageService::QUESTIONS_CREATED, 'credit_amount' => $amount,
            'per_user_monthly_cap' => $userCap, 'global_monthly_cap' => $globalCap,
            'status' => 'active', 'starts_at' => now()->subMinute(),
            'created_by' => $this->moderator->id, 'activated_by' => $this->moderator->id, 'activated_at' => now(),
        ]);
        DB::table('public_catalog_reward_rule_slots')->where('subject_kind', 'question')->update([
            'active_rule_id' => $rule->id, 'updated_at' => now(),
        ]);

        return $rule;
    }

    private function approve(Question $question, ?User $author = null)
    {
        $author ??= $this->author;
        $submission = app(PublicCatalogService::class)->submit($question, $author, [
            'rights_basis' => 'own_work', 'rights_notes' => null, 'attribution' => null,
            'evidence_url' => null, 'idempotency_key' => (string) Str::uuid(),
        ]);

        return app(PublicCatalogService::class)->decide(
            $submission, $this->moderator, 'approved', 'Conteúdo aprovado por revisão independente.',
        );
    }

    private function question(string $statement): Question
    {
        return Question::query()->create([
            'organization_id' => $this->organization->id, 'owner_id' => $this->author->id,
            'type' => 'multiple_choice', 'visibility_scope' => 'private',
            'content' => ['statement' => $statement, 'options' => ['A', 'B'], 'correct_option' => 0],
            'level' => 'medium', 'stage' => 'ef_finais', 'grade' => '6',
        ]);
    }
}
