<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe acesso aos logs de auditoria.
 *
 * Acesso: global_admin SEMPRE, institution_admin SE org.can_access_logs = true,
 * ou se user_id está em org.logs_access_users.
 */
class RestrictLogAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        // Global admin sempre pode
        if ($user->type === 'global_admin') {
            return $next($request);
        }

        $org = $user->organization;
        if (! $org) {
            abort(403, 'Sem vínculo institucional.');
        }

        // Institution admin pode se flag ativa
        if ($user->hasWorkspaceRole('admin', 'institution_admin') && $org->can_access_logs) {
            return $next($request);
        }

        // Exceções manuais (IDs em JSON)
        $allowedIds = $org->logs_access_users ?? [];
        if (in_array($user->id, $allowedIds)) {
            return $next($request);
        }

        abort(403, 'Acesso aos logs restrito. Solicite permissão ao administrador.');
    }
}
