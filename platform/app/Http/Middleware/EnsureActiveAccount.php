<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $inactiveAccount = $user->status !== 'active';
        $originalWorkspaceId = (int) $request->attributes->get('original_workspace_id');
        $inactiveOriginalWorkspace = $user->organization_id === null
            && $originalWorkspaceId > 0
            && Organization::withTrashed()->whereKey($originalWorkspaceId)
                ->where(fn ($query) => $query->where('active', false)->orWhereNotNull('deleted_at'))
                ->exists();
        $inactiveOrganization = $user->type !== 'global_admin'
            && (($user->organization_id !== null && (! $user->organization || ! $user->organization->active))
                || $inactiveOriginalWorkspace);
        $inactiveOriginalMembership = $user->organization_id === null
            && $originalWorkspaceId > 0
            && $user->organizations()->exists()
            && ! $user->belongsToActiveOrganization($originalWorkspaceId);
        $invalidOrganizationContext = $user->type !== 'global_admin'
            && (($user->organization_id !== null
                && ! $user->canUseOrganizationContext((int) $user->organization_id))
                || $inactiveOriginalMembership);

        if (! $inactiveAccount && ! $inactiveOrganization && ! $invalidOrganizationContext) {
            return $next($request);
        }

        if ($invalidOrganizationContext && ! $inactiveAccount && ! $inactiveOrganization) {
            $message = 'Seu vínculo com a instituição selecionada está inativo. Selecione outro contexto ou procure a administração.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 403);
            }

            abort(403, $message);
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = $inactiveOrganization
            ? 'O acesso da instituição está inativo. Procure a administração.'
            : 'Sua conta está inativa. Procure a administração.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
