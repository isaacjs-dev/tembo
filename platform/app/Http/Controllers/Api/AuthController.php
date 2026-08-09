<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, WorkspaceContextService $workspaces)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (
            ! $user
            || ! Hash::check($request->password, $user->password)
            || $user->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $availableWorkspaces = $workspaces->availableFor($user);
        if (! $request->headers->has('X-Workspace-Id') && $availableWorkspaces->count() > 1) {
            return response()->json([
                'error' => 'Selecione um workspace ativo para continuar.',
                'code' => 'WORKSPACE_REQUIRED',
                'workspaces' => $this->workspacePayload($workspaces, $user),
            ], 409);
        }

        $workspace = $workspaces->resolve($request, $user);
        if ($request->headers->has('X-Workspace-Id') && ! $workspace) {
            return response()->json(['error' => 'Workspace nÃ£o autorizado.'], 403);
        }
        if ($user->type !== 'global_admin' && ! $workspace) {
            $inactiveLegacyWorkspace = $user->organization_id
                && Organization::withTrashed()->whereKey($user->organization_id)
                    ->where(fn ($query) => $query->where('active', false)->orWhereNotNull('deleted_at'))
                    ->exists();
            $inactiveMembership = $user->organizations()
                ->wherePivot('status', 'active')
                ->where(fn ($query) => $query->where('organizations.active', false)->orWhereNotNull('organizations.deleted_at'))
                ->exists();

            if ($inactiveLegacyWorkspace || $inactiveMembership) {
                throw ValidationException::withMessages([
                    'email' => ['O acesso da instituiÃ§Ã£o estÃ¡ inativo. Procure a administraÃ§Ã£o.'],
                ]);
            }

            return response()->json([
                'error' => 'Selecione um workspace ativo para continuar.',
                'code' => 'WORKSPACE_REQUIRED',
                'workspaces' => $this->workspacePayload($workspaces, $user),
            ], 409);
        }

        $user->setAttribute('organization_id', $workspace?->id);
        $user->setRelation('organization', $workspace);
        $workspaceRole = $workspaces->roleFor($user, $workspace?->id);

        // Only teachers and admins can use the scanner
        if (! in_array($workspaceRole, ['teacher', 'admin', 'global_admin', 'institution_admin'], true)) {
            return response()->json(['error' => 'Apenas professores e administradores podem usar o scanner.'], 403);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
                'workspace_role' => $workspaceRole,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                ] : null,
                'workspaces' => $this->workspacePayload($workspaces, $user),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout realizado com sucesso.']);
    }

    public function me(Request $request, WorkspaceContextService $workspaces)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
                'workspace_role' => $workspaces->roleFor($user),
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                ] : null,
                'workspaces' => $this->workspacePayload($workspaces, $user),
            ],
        ]);
    }

    private function workspacePayload(WorkspaceContextService $workspaces, User $user): array
    {
        return $workspaces->availableFor($user)
            ->map(fn ($workspace): array => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'workspace_type' => $workspace->workspace_type,
                'role' => $workspaces->roleFor($user, (int) $workspace->id),
            ])
            ->values()
            ->all();
    }
}
