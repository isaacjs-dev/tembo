<?php

namespace App\Http\Middleware;

use App\Services\InstitutionPermissionService;
use Closure;
use Illuminate\Http\Request;

class CheckOmrApiAccess
{
    public function handle(Request $request, Closure $next, string $mode = 'full')
    {
        $user = $request->user();

        if (! $user
            || $user->status !== 'active'
            || ! $user->organization
            || ! $user->organization->active
            || ! $user->canUseOrganizationContext((int) $user->organization_id)) {
            return response()->json(['error' => 'Usuário sem organização vinculada.'], 403);
        }

        // Check plan feature (chave 'omr' é a semeada em PlanSeeder para pro/enterprise)
        if (! app(InstitutionPermissionService::class)->allows(
            $user,
            'manage_omr',
            (int) $user->organization_id,
        )) {
            return response()->json(['error' => 'Acesso não autorizado ao OMR.'], 403);
        }

        if ($mode !== 'permission-only' && $user->roleInOrganization((int) $user->organization_id) !== 'teacher') {
            return response()->json([
                'error' => 'A correção institucional está disponível somente no ambiente Web.',
            ], 403);
        }

        if ($mode !== 'permission-only' && ! $user->hasFeature('omr')) {
            return response()->json(['error' => 'Recurso OMR mobile não disponível no plano atual.'], 403);
        }

        return $next($request);
    }
}
