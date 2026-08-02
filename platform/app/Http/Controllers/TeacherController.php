<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\InviteManagerService;
use App\Services\UserFinderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeacherController extends Controller
{
    public function index()
    {
        $orgId = auth()->user()->organization_id;

        $teachers = User::where('type', 'teacher')
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $orgId)->where('user_organization.status', 'active'));
            })
            ->paginate(10);

        return view('institution.teachers.index', compact('teachers'));
    }

    public function create()
    {
        return view('institution.teachers.create');
    }

    /**
     * Busca AJAX por email ou código de vínculo.
     */
    public function search(Request $request)
    {
        $request->validate(['search' => 'required|string|min:3']);

        $finder = app(UserFinderService::class);
        $result = $finder->search($request->input('search'));

        if ($result['found']) {
            $user = $result['user'];
            $orgId = auth()->user()->organization_id;
            $alreadyLinked = $finder->isAlreadyLinked($user, $orgId);

            return response()->json([
                'found' => true,
                'already_linked' => $alreadyLinked,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'type' => $user->type,
                    'link_code' => $user->link_code,
                ],
                'message' => $alreadyLinked
                    ? 'Este usuário já está vinculado à sua instituição.'
                    : 'Usuário encontrado! Envie um convite para vinculá-lo.',
            ]);
        }

        return response()->json([
            'found' => false,
            'suggestion' => 'create',
            'message' => 'Nenhum usuário encontrado. Você pode cadastrá-lo como novo professor.',
        ]);
    }

    /**
     * Cria professor novo OU envia convite para existente.
     */
    public function store(Request $request)
    {
        $org = auth()->user()->organization;

        // Modo: enviar convite para usuário existente
        if ($request->filled('invite_user_id')) {
            $existingUser = User::findOrFail($request->input('invite_user_id'));

            $finder = app(UserFinderService::class);
            if ($finder->isAlreadyLinked($existingUser, $org->id)) {
                return back()->withErrors(['Este usuário já está vinculado à sua instituição.']);
            }

            $inviteManager = app(InviteManagerService::class);
            $inviteManager->send(
                inviter: auth()->user(),
                email: $existingUser->email,
                targetRole: 'teacher',
                organization: $org,
                inviteType: 'org_teacher',
                inviteeUserId: $existingUser->id,
            );

            return redirect()->route('institution.teachers.index')
                ->with('status', "Convite enviado para {$existingUser->name} ({$existingUser->email})!");
        }

        // Modo: criar professor novo
        if (! $org->canAddTeacher()) {
            return back()->withInput()->withErrors(['Limite de professores atingido no plano atual da sua instituição.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $teacher = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'organization_id' => $org->id,
            'type' => 'teacher',
            'status' => $validated['status'],
        ]);

        $teacher->assignRole('teacher');

        // Vínculo na pivot N:N
        $teacher->organizations()->syncWithoutDetaching([
            $org->id => [
                'role_in_org' => 'teacher',
                'status' => 'active',
                'joined_at' => now(),
            ],
        ]);

        AuditLog::log('created', User::class, $teacher->id, [
            'new' => ['name' => $teacher->name, 'email' => $teacher->email, 'type' => 'teacher'],
        ]);

        return redirect()->route('institution.teachers.index')->with('status', 'Professor cadastrado com sucesso!');
    }

    public function show(string $id) {}

    public function permissions(string $id)
    {
        $teacher = $this->findTeacher($id);

        return view('institution.teachers.permissions', compact('teacher'));
    }

    public function updatePermissions(Request $request, string $id)
    {
        $teacher = $this->findTeacher($id);
        $permissions = $request->input('permissions', []);
        $teacher->syncPermissions($permissions);

        AuditLog::log('updated', User::class, $teacher->id, [
            'action' => 'permissions_updated',
            'permissions' => $permissions,
        ]);

        return redirect()->route('institution.teachers.index')->with('status', 'Permissões atualizadas!');
    }

    public function edit(string $id)
    {
        $teacher = $this->findTeacher($id);

        return view('institution.teachers.edit', compact('teacher'));
    }

    public function update(Request $request, string $id)
    {
        $teacher = $this->findTeacher($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$teacher->id],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $teacher->name = $validated['name'];
        $teacher->email = $validated['email'];
        $teacher->status = $validated['status'];

        if (! empty($validated['password'])) {
            $teacher->password = Hash::make($validated['password']);
        }

        $teacher->save();

        AuditLog::log('updated', User::class, $teacher->id);

        return redirect()->route('institution.teachers.index')->with('status', 'Professor atualizado com sucesso!');
    }

    public function destroy(string $id)
    {
        $teacher = $this->findTeacher($id);

        $teacher->status = $teacher->status === 'active' ? 'inactive' : 'active';
        $teacher->save();

        AuditLog::log('updated', User::class, $teacher->id, [
            'action' => $teacher->status === 'active' ? 'reactivated' : 'deactivated',
        ]);

        return redirect()->route('institution.teachers.index')
            ->with('status', $teacher->status === 'active' ? 'Professor reativado!' : 'Professor desativado!');
    }

    /**
     * Encontra professor no escopo da instituição (legado + pivot).
     */
    private function findTeacher(string $id): User
    {
        $orgId = auth()->user()->organization_id;

        return User::where('type', 'teacher')
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $orgId)->where('user_organization.status', 'active'));
            })
            ->findOrFail($id);
    }
}
