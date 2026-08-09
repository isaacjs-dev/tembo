<?php

namespace App\Http\Middleware;

use App\Models\InstitutionRolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que verifica se o usuário tem uma permissão institucional.
 *
 * Uso: ->middleware('inst_perm:view_teachers')
 *
 * Lógica:
 * 1. institution_admin SEMPRE passa (acesso total)
 * 2. global_admin SEMPRE passa
 * 3. Usuário com perfil extra: verifica se o cargo tem a permissão
 * 4. Caso contrário: bloqueia
 */
class CheckInstitutionPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Não autenticado.');
        }

        // Global admin e Institution admin têm acesso total
        if ($user->hasWorkspaceRole('global_admin', 'admin', 'institution_admin')) {
            return $next($request);
        }

        // Buscar o vínculo ativo do usuário com a organização atual
        $orgId = $user->organization_id;
        if (! $orgId) {
            abort(403, 'Sem vínculo institucional ativo.');
        }

        $pivot = \DB::table('user_organization')
            ->where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->where('status', 'active')
            ->first();

        if (! $pivot || ! $pivot->institution_role_id) {
            abort(403, 'Você não tem permissão para acessar este recurso.');
        }

        // Verificar se o cargo tem a permissão
        $hasPermission = InstitutionRolePermission::where('institution_role_id', $pivot->institution_role_id)
            ->where('permission', $permission)
            ->exists();

        if (! $hasPermission) {
            abort(403, 'Seu cargo institucional não possui esta permissão.');
        }

        return $next($request);
    }
}
