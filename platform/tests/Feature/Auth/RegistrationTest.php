<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_independent_teacher_can_register_with_a_personal_workspace(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account_type' => 'personal',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'type' => 'teacher',
        ]);
        $this->assertDatabaseHas('organizations', [
            'name' => 'Espaço de Test User',
            'workspace_type' => 'personal',
            'active' => true,
        ]);
        $this->assertTrue(auth()->user()->hasRole('teacher'));
        $this->assertDatabaseHas('user_organization', [
            'user_id' => auth()->id(),
            'organization_id' => auth()->user()->organization_id,
            'role_in_org' => 'teacher',
            'status' => 'active',
        ]);
    }

    public function test_institution_can_register_with_an_institutional_workspace(): void
    {
        $this->post('/register', [
            'name' => 'Gestora',
            'account_type' => 'institution',
            'organization_name' => 'Escola Horizonte',
            'email' => 'gestora@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'gestora@example.com',
            'type' => 'institution_admin',
        ]);
        $this->assertDatabaseHas('organizations', [
            'name' => 'Escola Horizonte',
            'workspace_type' => 'institutional',
        ]);
        $this->assertTrue(auth()->user()->hasRole('institution_admin'));
    }
}
