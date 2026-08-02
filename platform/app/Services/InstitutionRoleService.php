<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InstitutionRole;
use App\Models\Organization;
use Illuminate\Validation\ValidationException;

class InstitutionRoleService
{
    /**
     * Cria um novo cargo institucional.
     *
     * @throws ValidationException se atingir limite do plano
     */
    public function create(Organization $org, array $data): InstitutionRole
    {
        $this->validateLimit($org);

        $role = InstitutionRole::create([
            'organization_id' => $org->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        AuditLog::log('created', InstitutionRole::class, $role->id, [
            'name' => $role->name,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return $role;
    }

    /**
     * Atualiza um cargo e suas permissões.
     */
    public function update(InstitutionRole $role, array $data): InstitutionRole
    {
        $oldPermissions = $role->getPermissionKeys();

        $role->update([
            'name' => $data['name'] ?? $role->name,
            'description' => $data['description'] ?? $role->description,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);

        if (array_key_exists('permissions', $data)) {
            $role->syncPermissions($data['permissions'] ?? []);
        }

        AuditLog::log('updated', InstitutionRole::class, $role->id, [
            'old_permissions' => $oldPermissions,
            'new_permissions' => $data['permissions'] ?? [],
        ]);

        return $role;
    }

    /**
     * Desativa um cargo (soft-disable).
     */
    public function deactivate(InstitutionRole $role): void
    {
        $role->update(['is_active' => false]);
        AuditLog::log('deactivated', InstitutionRole::class, $role->id);
    }

    /**
     * Deleta um cargo. Remove vínculos dos membros.
     */
    public function delete(InstitutionRole $role): void
    {
        $memberCount = $role->members()->count();

        // Limpar vínculos de membros com este cargo
        $role->members()->update(['institution_role_id' => null]);

        AuditLog::log('deleted', InstitutionRole::class, $role->id, [
            'name' => $role->name,
            'members_unset' => $memberCount,
        ]);

        $role->delete();
    }

    /**
     * Atribui um cargo institucional a um usuário na pivot.
     */
    public function assignToUser(int $userId, int $orgId, int $roleId): void
    {
        \DB::table('user_organization')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->update(['institution_role_id' => $roleId]);
    }

    /**
     * Remove o cargo institucional de um usuário.
     */
    public function removeFromUser(int $userId, int $orgId): void
    {
        \DB::table('user_organization')
            ->where('user_id', $userId)
            ->where('organization_id', $orgId)
            ->update(['institution_role_id' => null]);
    }

    /**
     * Valida limite de cargos do plano (max_extra_profiles).
     */
    private function validateLimit(Organization $org): void
    {
        $limiter = app(PlanLimiterService::class);
        $current = InstitutionRole::where('organization_id', $org->id)->count();

        if (! $limiter->canCreate($org, 'max_extra_profiles', $current)) {
            throw ValidationException::withMessages([
                'limit' => 'Limite de perfis extras do plano foi atingido.',
            ]);
        }
    }
}
