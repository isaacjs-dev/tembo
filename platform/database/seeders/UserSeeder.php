<?php

namespace Database\Seeders;

use App\Models\GuardianStudentLink;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the PRO plan to assign a subscription
        $plan = Plan::where('slug', 'pro')->first();

        // Create an Organization
        $org = Organization::updateOrCreate(
            ['subdomain' => 'escola-modelo'],
            [
                'name' => 'Escola Horizonte — Demonstração',
                'active' => true,
                'allow_class_copy' => true,
                'can_access_trash' => true,
                'can_access_logs' => true,
                'settings' => [
                    'demo' => true,
                    'school_year' => 2026,
                    'requires_email_verification' => false,
                ],
            ]
        );

        if ($plan && $org->subscriptions()->count() === 0) {
            Subscription::create([
                'organization_id' => $org->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'starts_at' => now(),
                'expires_at' => now()->addYear(),
            ]);
        }

        // Global Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador Global',
                'password' => Hash::make('password'),
                'type' => 'global_admin',
                'email_verified_at' => now(),
            ]
        );
        if (! $admin->hasRole('global_admin')) {
            $admin->assignRole('global_admin');
        }

        // Institution Admin
        $institution = User::firstOrCreate(
            ['email' => 'institution@email.com'],
            [
                'name' => 'Diretor / Owner da Instituição',
                'password' => Hash::make('password'),
                'type' => 'institution_admin',
                'organization_id' => $org->id,
                'email_verified_at' => now(),
            ]
        );
        if (! $institution->hasRole('institution_admin')) {
            $institution->assignRole('institution_admin');
        }
        // Vínculo N:N na pivot user_organization (fonte de verdade do multi-tenant).
        // Idempotente: syncWithoutDetaching não duplica e faz backfill de dados existentes.
        $institution->organizations()->syncWithoutDetaching([
            $org->id => ['role_in_org' => 'admin', 'status' => 'active', 'joined_at' => now()],
        ]);

        // Teacher
        $teacher = User::firstOrCreate(
            ['email' => 'teacher@email.com'],
            [
                'name' => 'Professor Silva',
                'password' => Hash::make('password'),
                'type' => 'teacher',
                'organization_id' => $org->id,
                'email_verified_at' => now(),
            ]
        );
        if (! $teacher->hasRole('teacher')) {
            $teacher->assignRole('teacher');
        }
        $teacher->organizations()->syncWithoutDetaching([
            $org->id => ['role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now()],
        ]);

        // Student
        $student = User::firstOrCreate(
            ['email' => 'student@email.com'],
            [
                'name' => 'Aluno João',
                'password' => Hash::make('password'),
                'type' => 'student',
                'organization_id' => $org->id,
                'email_verified_at' => now(),
            ]
        );
        if (! $student->hasRole('student')) {
            $student->assignRole('student');
        }
        $student->organizations()->syncWithoutDetaching([
            $org->id => ['role_in_org' => 'student', 'status' => 'active', 'joined_at' => now()],
        ]);

        // Guardian / responsável
        $guardian = User::firstOrCreate(
            ['email' => 'guardian@email.com'],
            [
                'name' => 'Responsável de João',
                'password' => Hash::make('password'),
                'type' => 'guardian',
                'organization_id' => $org->id,
                'email_verified_at' => now(),
            ]
        );
        if (! $guardian->hasRole('guardian')) {
            $guardian->assignRole('guardian');
        }

        GuardianStudentLink::withTrashed()->updateOrCreate(
            [
                'organization_id' => $org->id,
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
            ],
            [
                'relationship' => 'Responsável',
                'created_by' => $institution->id,
                'deleted_at' => null,
            ]
        );

        $org->update(['owner_user_id' => $institution->id]);
    }
}
