<?php

namespace App\Http\Middleware;

use App\Services\WorkspaceContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkspaceRole
{
    public function __construct(private WorkspaceContextService $workspaces) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $role = $request->attributes->get('workspace_role')
            ?? ($user ? $this->workspaces->roleFor($user) : null);

        abort_unless($user && $role && in_array($role, $roles, true), 403);

        return $next($request);
    }
}
