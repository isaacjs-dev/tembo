<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe acesso à lixeira.
 *
 * Acesso: global_admin SEMPRE, institution_admin SE org.can_access_trash = true,
 * ou se user_id está em org.trash_access_users.
 */
class RestrictTrashAccess
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
        if ($user->hasWorkspaceRole('admin', 'institution_admin') && $org->can_access_trash) {
            return $next($request);
        }

        // Exceções manuais (IDs em JSON)
        $allowedIds = $org->trash_access_users ?? [];
        if (in_array($user->id, $allowedIds)) {
            return $next($request);
        }

        abort(403, 'Acesso à lixeira restrito. Solicite permissão ao administrador.');
    }
}
