<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActiveAccountMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('teacher', 'web');
    }

    public function test_an_inactive_account_loses_an_existing_web_session(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $teacher = $this->teacher($organization);
        $teacher->update(['status' => 'inactive']);

        $this->actingAs($teacher)
            ->get(route('questions.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_member_loses_an_existing_session_when_the_tenant_is_inactive(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $teacher = $this->teacher($organization);
        $organization->update(['active' => false]);

        $this->actingAs($teacher)
            ->get(route('questions.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_account_receives_a_machine_readable_api_error(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $teacher = $this->teacher($organization);
        $teacher->update(['status' => 'inactive']);

        $this->actingAs($teacher)
            ->getJson(route('questions.index'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua conta está inativa. Procure a administração.');
    }

    public function test_existing_sanctum_token_is_blocked_when_account_becomes_inactive(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $teacher = $this->teacher($organization);
        $token = $teacher->createToken('scanner')->plainTextToken;
        $teacher->update(['status' => 'inactive']);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Sua conta está inativa. Procure a administração.');
    }

    public function test_existing_sanctum_token_is_blocked_when_tenant_becomes_inactive(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $teacher = $this->teacher($organization);
        $token = $teacher->createToken('scanner')->plainTextToken;
        $organization->update(['active' => false]);

        $this->withToken($token)
            ->getJson('/api/v2/config/effective')
            ->assertForbidden()
            ->assertJsonPath('message', 'O acesso da instituição está inativo. Procure a administração.');
    }

    public function test_inactive_tenant_cannot_issue_a_sanctum_token(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => false]);
        $teacher = $this->teacher($organization);

        $this->postJson('/api/v1/auth/login', [
            'email' => $teacher->email,
            'password' => 'password',
            'device_name' => 'scanner',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $teacher->id,
        ]);
    }

    private function teacher(Organization $organization): User
    {
        $teacher = User::create([
            'organization_id' => $organization->id,
            'name' => 'Professora',
            'email' => uniqid('teacher-').'@example.test',
            'password' => 'password',
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');

        return $teacher;
    }
}
