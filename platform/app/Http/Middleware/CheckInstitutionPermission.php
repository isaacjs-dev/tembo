<?php

namespace App\Http\Middleware;

use App\Services\InstitutionPermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que verifica se o usuário tem uma permissão institucional.
 *
 * Uso: ->middleware('inst_perm:view_teachers')
 *
 * A matriz central cobre gestores, papéis institucionais nativos, professor
 * independente e cargos customizados ativos no workspace atual.
 */
class CheckInstitutionPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Não autenticado.');
        }

        if (! app(InstitutionPermissionService::class)->allows(
            $user,
            $permission,
            (int) $user->organization_id,
        )) {
            abort(403, 'Seu cargo institucional não possui esta permissão.');
        }

        return $next($request);
    }
}
