<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuestionTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected $teacher;

    protected $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie Role
        Role::firstOrCreate(['name' => 'teacher']);

        $this->organization = Organization::create([
            'name' => 'School Test',
        ]);

        $this->teacher = User::factory()->create([
            'type' => 'teacher',
            'organization_id' => $this->organization->id,
        ]);

        // Define role teacher using Spatie
        $this->teacher->assignRole('teacher');
    }

    public function test_teacher_can_create_knowledge_area_via_ajax()
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('institution.knowledge-areas.store'), [
                'name' => 'Ciências da Natureza',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'name' => 'Ciências da Natureza',
            ]);

        $this->assertDatabaseHas('knowledge_areas', [
            'name' => 'Ciências da Natureza',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_teacher_can_create_discipline_via_ajax()
    {
        $response = $this->actingAs($this->teacher)
            ->postJson(route('institution.disciplines.store'), [
                'name' => 'Física Quântica',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'name' => 'Física Quântica',
            ]);

        $this->assertDatabaseHas('disciplines', [
            'name' => 'Física Quântica',
            'organization_id' => $this->organization->id,
        ]);
    }
}
