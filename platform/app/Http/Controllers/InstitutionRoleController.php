<?php

namespace App\Http\Controllers;

use App\Models\InstitutionRole;
use App\Services\InstitutionRoleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InstitutionRoleController extends Controller
{
    public function index()
    {
        $orgId = auth()->user()->organization_id;

        $roles = InstitutionRole::where('organization_id', $orgId)
            ->withCount('members')
            ->get();

        return view('institution.roles.index', compact('roles'));
    }

    public function create()
    {
        $availablePermissions = InstitutionRole::availablePermissions();

        return view('institution.roles.create', compact('availablePermissions'));
    }

    public function store(Request $request)
    {
        abort_unless(in_array(auth()->user()->type, ['institution_admin', 'global_admin']), 403);
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:'.implode(',', array_keys(InstitutionRole::availablePermissions())),
        ]);

        $service = app(InstitutionRoleService::class);

        try {
            $service->create(auth()->user()->organization, $validated);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('institution.roles.index')
            ->with('status', 'Cargo criado com sucesso!');
    }

    public function edit(string $id)
    {
        $role = $this->findRole($id);
        $availablePermissions = InstitutionRole::availablePermissions();
        $currentPermissions = $role->getPermissionKeys();

        return view('institution.roles.edit', compact('role', 'availablePermissions', 'currentPermissions'));
    }

    public function update(Request $request, string $id)
    {
        $role = $this->findRole($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:'.implode(',', array_keys(InstitutionRole::availablePermissions())),
        ]);

        $service = app(InstitutionRoleService::class);
        $service->update($role, $validated);

        return redirect()->route('institution.roles.index')
            ->with('status', 'Cargo atualizado!');
    }

    public function destroy(string $id)
    {
        $role = $this->findRole($id);

        $service = app(InstitutionRoleService::class);
        $service->delete($role);

        return redirect()->route('institution.roles.index')
            ->with('status', 'Cargo excluído.');
    }

    /**
     * Atribui cargo a um membro da instituição.
     */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'nullable|exists:institution_roles,id',
        ]);

        $service = app(InstitutionRoleService::class);
        $orgId = auth()->user()->organization_id;

        if ($validated['role_id']) {
            $service->assignToUser($validated['user_id'], $orgId, $validated['role_id']);
        } else {
            $service->removeFromUser($validated['user_id'], $orgId);
        }

        return back()->with('status', 'Cargo atualizado para o membro.');
    }

    private function findRole(string $id): InstitutionRole
    {
        return InstitutionRole::where('organization_id', auth()->user()->organization_id)
            ->findOrFail($id);
    }
}
