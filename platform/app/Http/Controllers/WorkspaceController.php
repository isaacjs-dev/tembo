<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, WorkspaceContextService $workspaces): View
    {
        return view('workspaces.index', [
            'workspaces' => $workspaces->availableFor($request->user()),
            'currentWorkspaceId' => $request->user()->organization_id,
        ]);
    }

    public function select(
        Request $request,
        Organization $organization,
    ): RedirectResponse {
        abort_unless($request->user()->canUseOrganizationContext((int) $organization->id), 403);
        abort_unless($organization->active, 403);

        $request->session()->put('workspace_id', $organization->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with(
            'status',
            "Espaço alterado para {$organization->name}.",
        );
    }

    public function storePersonal(Request $request, WorkspaceContextService $workspaces): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->type, ['teacher', 'institution_admin'], true), 403);

        $workspace = $workspaces->availableFor($user)
            ->first(fn (Organization $candidate) => $candidate->isPersonalWorkspace());

        if (! $workspace) {
            $workspace = DB::transaction(function () use ($user): Organization {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                $existing = $lockedUser->activeOrganizations()
                    ->where('organizations.workspace_type', 'personal')
                    ->where('organizations.active', true)
                    ->first();
                if ($existing) {
                    return $existing;
                }

                $workspace = Organization::create([
                    'name' => 'EspaÃ§o de '.$lockedUser->name,
                    'workspace_type' => 'personal',
                    'active' => true,
                    'owner_user_id' => $lockedUser->id,
                ]);
                $lockedUser->organizations()->attach($workspace->id, [
                    'role_in_org' => 'teacher',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                if (! $lockedUser->subscription()->exists()) {
                    $plan = Plan::query()->where('slug', 'start')->where('status', 'active')->first();
                    if ($plan) {
                        Subscription::create([
                            'subscriber_type' => User::class,
                            'subscriber_id' => $lockedUser->id,
                            'plan_id' => $plan->id,
                            'status' => 'active',
                            'starts_at' => now(),
                        ]);
                    }
                }

                return $workspace;
            });
        }

        $request->session()->put('workspace_id', $workspace->id);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'EspaÃ§o pessoal pronto para uso.');
    }
}
