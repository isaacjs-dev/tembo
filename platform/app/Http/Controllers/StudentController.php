<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\InviteManagerService;
use App\Services\OrganizationMembershipService;
use App\Services\UserFinderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index()
    {
        $orgId = $this->currentOrganizationId();

        $students = User::query()
            ->memberOfOrganization($orgId, 'student', activeOnly: false)
            ->with([
                'studentProfiles' => fn ($query) => $query->where('organization_id', $orgId),
                'schoolClasses' => fn ($query) => $query->where('school_classes.organization_id', $orgId),
                'organizations' => fn ($query) => $query->where('organizations.id', $orgId),
            ])
            ->paginate(10);

        return view('institution.students.index', compact('students'));
    }

    public function create()
    {
        $this->currentOrganizationId();

        return view('institution.students.create');
    }

    /**
     * Busca AJAX por email ou código de vínculo.
     */
    public function search(Request $request)
    {
        $orgId = $this->currentOrganizationId();
        $request->validate(['search' => 'required|string|min:3']);

        $finder = app(UserFinderService::class);
        $result = $finder->search($request->input('search'));

        if ($result['found']) {
            $user = $result['user'];
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
        $this->currentOrganizationId();
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
            'school_classes.*' => [
                'integer',
                'distinct',
                Rule::exists('school_classes', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $org->id)
                        ->whereNull('deleted_at')),
            ],
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
        $organizationId = $this->currentOrganizationId();
        $student = $this->findStudent($id);
        $student->load([
            'studentProfiles' => fn ($query) => $query->where('organization_id', $organizationId),
            'schoolClasses' => fn ($query) => $query->where('school_classes.organization_id', $organizationId),
        ]);
        $membershipStatus = $student->organizationMembershipStatus($organizationId);

        return view('institution.students.edit', compact('student', 'membershipStatus'));
    }

    public function update(Request $request, string $id)
    {
        $student = $this->findStudent($id);
        $organizationId = $this->currentOrganizationId();

        $validated = $request->validate([
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'password' => ['prohibited'],
            'status' => ['required', 'in:active,inactive'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'school_classes' => ['nullable', 'array'],
            'school_classes.*' => [
                'integer',
                'distinct',
                Rule::exists('school_classes', 'id')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->whereNull('deleted_at')),
            ],
        ]);

        DB::transaction(function () use ($validated, $request, $student, $organizationId) {
            app(OrganizationMembershipService::class)->setStatus(
                $student,
                auth()->user()->organization,
                'student',
                $validated['status'],
            );

            StudentProfile::updateOrCreate(
                [
                    'user_id' => $student->id,
                    'organization_id' => $organizationId,
                ],
                [
                    'registration_number' => $validated['registration_number'] ?? null,
                ]
            );

            $this->syncOrganizationClasses(
                $student,
                $organizationId,
                $request->has('school_classes') ? $validated['school_classes'] : [],
            );

            AuditLog::log('updated', User::class, $student->id);
        });

        return redirect()->route('institution.students.index')->with('status', 'Aluno atualizado com sucesso!');
    }

    public function destroy(string $id)
    {
        $student = $this->findStudent($id);
        $organization = auth()->user()->organization;
        $nextStatus = $student->organizationMembershipStatus((int) $organization->id) === 'active'
            ? 'inactive'
            : 'active';

        app(OrganizationMembershipService::class)->setStatus(
            $student,
            $organization,
            'student',
            $nextStatus,
        );

        AuditLog::log('updated', User::class, $student->id, [
            'action' => 'organization_membership_'.$nextStatus,
            'organization_id' => $organization->id,
        ]);

        return redirect()->route('institution.students.index')
            ->with('status', $nextStatus === 'active'
                ? 'Vínculo do aluno reativado nesta instituição.'
                : 'Aluno desvinculado desta instituição. A conta global foi preservada.');
    }

    /**
     * Encontra aluno no escopo da instituição (legado + pivot).
     */
    private function findStudent(string $id): User
    {
        $orgId = $this->currentOrganizationId();

        return User::query()
            ->memberOfOrganization($orgId, 'student', activeOnly: false)
            ->findOrFail($id);
    }

    private function currentOrganizationId(): int
    {
        $user = auth()->user();
        $organizationId = (int) $user->organization_id;

        abort_unless($user->canUseOrganizationContext($organizationId), 403, 'Selecione uma instituição ativa.');

        return $organizationId;
    }

    /** Preserve class links from every other organization while replacing this tenant's links. */
    private function syncOrganizationClasses(User $student, int $organizationId, array $localClassIds): void
    {
        $foreignClassIds = DB::table('class_student')
            ->join('school_classes', 'school_classes.id', '=', 'class_student.school_class_id')
            ->where('class_student.user_id', $student->id)
            ->where('school_classes.organization_id', '!=', $organizationId)
            ->pluck('class_student.school_class_id')
            ->all();

        $student->schoolClasses()->sync(array_values(array_unique([
            ...$foreignClassIds,
            ...$localClassIds,
        ])));
    }
}
