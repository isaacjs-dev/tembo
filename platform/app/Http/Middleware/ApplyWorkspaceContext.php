<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyWorkspaceContext
{
    public function __construct(private WorkspaceContextService $workspaces) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $request->attributes->set('original_workspace_id', $user->getRawOriginal('organization_id'));
        $workspace = $this->workspaces->resolve($request, $user);
        if ($request->headers->has('X-Workspace-Id') && ! $workspace) {
            abort(403, 'Workspace não autorizado.');
        }
        $user->setAttribute('organization_id', $workspace?->id);
        $user->setRelation('organization', $workspace);
        $request->attributes->set('workspace', $workspace);
        $request->attributes->set(
            'workspace_role',
            $this->workspaces->roleFor($user, $workspace?->id),
        );

        return $next($request);
    }
}
