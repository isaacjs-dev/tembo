<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\InviteManagerService;
use App\Services\UserFinderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index()
    {
        $orgId = auth()->user()->organization_id;

        $students = User::where('type', 'student')
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $orgId)->where('user_organization.status', 'active'));
            })
            ->with(['studentProfile', 'schoolClasses'])
            ->paginate(10);

        return view('institution.students.index', compact('students'));
    }

    public function create()
    {
        return view('institution.students.create');
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
                    ? 'Este aluno já está vinculado à sua instituição.'
                    : 'Aluno encontrado! Envie um convite para vinculá-lo.',
            ]);
        }

        return response()->json([
            'found' => false,
            'suggestion' => 'create',
            'message' => 'Nenhum aluno encontrado. Você pode cadastrá-lo como novo.',
        ]);
    }

    /**
     * Cria aluno novo OU envia convite para existente.
     */
    public function store(Request $request)
    {
        $org = auth()->user()->organization;

        // Modo: enviar convite para aluno existente
        if ($request->filled('invite_user_id')) {
            $existingUser = User::findOrFail($request->input('invite_user_id'));

            $finder = app(UserFinderService::class);
            if ($finder->isAlreadyLinked($existingUser, $org->id)) {
                return back()->withErrors(['Este aluno já está vinculado à sua instituição.']);
            }

            $inviteManager = app(InviteManagerService::class);
            $inviteManager->send(
                inviter: auth()->user(),
                email: $existingUser->email,
                targetRole: 'student',
                organization: $org,
                inviteType: 'org_student',
                inviteeUserId: $existingUser->id,
            );

            return redirect()->route('institution.students.index')
                ->with('status', "Convite enviado para {$existingUser->name} ({$existingUser->email})!");
        }

        // Modo: criar aluno novo
        if (! $org->canAddStudent()) {
            return back()->withInput()->withErrors(['Limite de alunos atingido no plano atual.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8'],
            'registration_number' => 'nullable|string|max:255',
            'school_classes' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated, $request, $org) {
            $student = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'organization_id' => $org->id,
                'type' => 'student',
            ]);

            $student->assignRole('student');

            // Vínculo na pivot N:N
            $student->organizations()->syncWithoutDetaching([
                $org->id => [
                    'role_in_org' => 'student',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            StudentProfile::create([
                'user_id' => $student->id,
                'organization_id' => $org->id,
                'registration_number' => $validated['registration_number'] ?? null,
            ]);

            if ($request->has('school_classes')) {
                $student->schoolClasses()->sync($validated['school_classes']);
            }

            AuditLog::log('created', User::class, $student->id, [
                'new' => ['name' => $student->name, 'email' => $student->email, 'type' => 'student'],
            ]);
        });

        return redirect()->route('institution.students.index')->with('status', 'Aluno cadastrado com sucesso!');
    }

    public function edit(string $id)
    {
        $student = $this->findStudent($id);

        return view('institution.students.edit', compact('student'));
    }

    public function update(Request $request, string $id)
    {
        $student = $this->findStudent($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$student->id],
            'password' => ['nullable', 'string', 'min:8'],
            'status' => ['required', 'in:active,inactive'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'school_classes' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated, $request, $student) {
            $student->name = $validated['name'];
            $student->email = $validated['email'];
            $student->status = $validated['status'];

            if (! empty($validated['password'])) {
                $student->password = Hash::make($validated['password']);
            }

            $student->save();

            StudentProfile::updateOrCreate(
                ['user_id' => $student->id],
                [
                    'organization_id' => auth()->user()->organization_id,
                    'registration_number' => $validated['registration_number'] ?? null,
                ]
            );

            if ($request->has('school_classes')) {
                $student->schoolClasses()->sync($validated['school_classes']);
            } else {
                $student->schoolClasses()->detach();
            }

            AuditLog::log('updated', User::class, $student->id);
        });

        return redirect()->route('institution.students.index')->with('status', 'Aluno atualizado com sucesso!');
    }

    public function destroy(string $id)
    {
        $student = $this->findStudent($id);
        $student->status = $student->status === 'active' ? 'inactive' : 'active';
        $student->save();

        AuditLog::log('updated', User::class, $student->id, [
            'action' => $student->status === 'active' ? 'reactivated' : 'deactivated',
        ]);

        return redirect()->route('institution.students.index')
            ->with('status', $student->status === 'active' ? 'Aluno reativado!' : 'Aluno desativado!');
    }

    /**
     * Encontra aluno no escopo da instituição (legado + pivot).
     */
    private function findStudent(string $id): User
    {
        $orgId = auth()->user()->organization_id;

        return User::where('type', 'student')
            ->where(function ($q) use ($orgId) {
                $q->where('organization_id', $orgId)
                    ->orWhereHas('organizations', fn ($q2) => $q2->where('organizations.id', $orgId)->where('user_organization.status', 'active'));
            })
            ->findOrFail($id);
    }
}
