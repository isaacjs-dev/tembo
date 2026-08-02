<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // ── Institutional Management ──
            'institution.dashboard.view',
            'institution.settings.manage',
            'institution.billing.view',
            'institution.billing.manage',
            'institution.logs.view',
            'institution.trash.view',
            'institution.trash.restore',
            'institution.trash.force_delete',

            // ── Teacher CRUD ──
            'teacher.view',
            'teacher.create',
            'teacher.edit',
            'teacher.disable',
            'teacher.permissions.manage',

            // ── Classes ──
            'class.view_all',
            'class.create',
            'class.edit',

            // ── Students ──
            'student.view_all',
            'student.create',
            'student.edit',
            'student.import',

            // ── Reporting ──
            'reporting.institution.view',
            'reporting.export',

            // ── Questions & Sharing ──
            'question.share_public',
            'question.view_public',

            // ── Plan Management (Admin) ──
            'plan.view',
            'plan.create',
            'plan.edit',
            'plan.delete',

            // ── Invite & Linking ──
            'invite.send',
            'invite.cancel',
            'invite.accept',
            'member.unlink',

            // ── Audit Logs ──
            'audit.view',
            'audit.export',

            // ── Dashboard Admin ──
            'admin.dashboard.view',
            'admin.users.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ── ROLES ──

        // Global Admin — todas as permissions (bypass via Gate::before)
        $globalAdmin = Role::firstOrCreate(['name' => 'global_admin']);
        $globalAdmin->syncPermissions(Permission::all());

        // Institution Admin — gestão completa da instituição
        $institutionAdmin = Role::firstOrCreate(['name' => 'institution_admin']);
        $institutionAdmin->syncPermissions([
            'institution.dashboard.view',
            'institution.settings.manage',
            'institution.billing.view',
            'institution.billing.manage',
            'institution.logs.view',
            'institution.trash.view',
            'institution.trash.restore',
            'institution.trash.force_delete',
            'teacher.view',
            'teacher.create',
            'teacher.edit',
            'teacher.disable',
            'teacher.permissions.manage',
            'class.view_all',
            'class.create',
            'class.edit',
            'student.view_all',
            'student.create',
            'student.edit',
            'student.import',
            'reporting.institution.view',
            'reporting.export',
            'question.share_public',
            'question.view_public',
            'invite.send',
            'invite.cancel',
            'member.unlink',
        ]);

        // Teacher — CRUD de turmas/alunos/questões, sem gestão de professores
        $teacher = Role::firstOrCreate(['name' => 'teacher']);
        $teacher->syncPermissions([
            'class.view_all',
            'class.create',
            'class.edit',
            'student.view_all',
            'student.create',
            'student.edit',
            'student.import',
            'question.share_public',
            'question.view_public',
            'invite.send',
            'invite.accept',
        ]);

        // Student — sem permissions explícitas, acesso apenas via rotas
        Role::firstOrCreate(['name' => 'student']);

        // Responsável — acesso somente ao portal e aos estudantes vinculados
        Role::firstOrCreate(['name' => 'guardian']);
    }
}
