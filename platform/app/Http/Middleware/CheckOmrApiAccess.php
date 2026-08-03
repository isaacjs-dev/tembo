<?php

namespace App\Http\Middleware;

use App\Models\UserOrganization;
use Closure;
use Illuminate\Http\Request;

class CheckOmrApiAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active' || ! $user->organization || ! $user->organization->active) {
            return response()->json(['error' => 'Usuário sem organização vinculada.'], 403);
        }

        // Check plan feature (chave 'omr' é a semeada em PlanSeeder para pro/enterprise)
        if (! in_array($user->type, ['teacher', 'admin', 'institution_admin', 'global_admin'], true)) {
            return response()->json(['error' => 'Acesso não autorizado ao OMR.'], 403);
        }

        if (! $user->organization->hasFeature('omr')) {
            return response()->json(['error' => 'Recurso OMR mobile não disponível no plano atual.'], 403);
        }

        // Check institution permission (manage_omr)
        $orgId = $user->organization_id;
        $pivot = UserOrganization::where('user_id', $user->id)
            ->where('organization_id', $orgId)
            ->where('status', 'active')
            ->first();

        if (! $pivot) {
            // Legacy users may only have the direct organization link.
            if ((int) $user->organization_id !== (int) $orgId) {
                return response()->json(['error' => 'Acesso não autorizado ao OMR.'], 403);
            }
        } elseif ($pivot->institution_role_id) {
            $institutionRole = $pivot->role;
            if (! $institutionRole || ! $institutionRole->is_active || ! $institutionRole->hasPermission('manage_omr')) {
                return response()->json(['error' => 'Sem permissão para gerenciar leituras OMR.'], 403);
            }
        }

        return $next($request);
    }
}
