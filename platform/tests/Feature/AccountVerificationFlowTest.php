<?php

namespace Tests\Feature;

use App\Models\GuardianStudentLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['institution_admin', 'student', 'guardian'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_self_service_registration_sends_verification_and_blocks_direct_routes(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Gestora',
            'organization_name' => 'Escola Verificada',
            'email' => 'gestora@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'gestora@example.test')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get(route('institution.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $token = $user->createToken('scanner')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Confirme seu endereço de e-mail antes de continuar.');
    }

    public function test_guardian_must_verify_email_and_change_provisional_password(): void
    {
        $organization = Organization::create(['name' => 'Escola', 'active' => true]);
        $guardian = $this->user($organization, 'guardian', [
            'email_verified_at' => null,
            'settings' => [
                'requires_email_verification' => true,
                'must_change_password' => true,
            ],
        ]);
        $student = $this->user($organization, 'student', [
            'email_verified_at' => now(),
        ]);
        GuardianStudentLink::create([
            'organization_id' => $organization->id,
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'Responsável',
        ]);

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $guardian->markEmailAsVerified();

        $token = $guardian->createToken('family-app')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Altere sua senha provisória antes de continuar.');

        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('warning');

        $this->actingAs($guardian)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) ($guardian->fresh()->settings['must_change_password'] ?? false));
        $this->actingAs($guardian)
            ->get(route('guardian.dashboard'))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(Organization $organization, string $type, array $attributes = []): User
    {
        $user = User::create(array_merge([
            'organization_id' => $organization->id,
            'name' => ucfirst($type),
            'email' => $type.'-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ], $attributes));
        $user->assignRole($type);

        return $user;
    }
}
