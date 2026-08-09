<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\Invite;
use App\Models\SchoolClass;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use App\Services\AcademicRelationshipService;
use App\Services\ClassOwnershipService;
use App\Services\EntitlementService;
use App\Services\InstitutionPermissionService;
use App\Services\InviteManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SchoolClassController extends Controller
{
    public function index()
    {
        $orgId = $this->currentOrganizationId();
        $this->authorizeClassViewing();
        // Turmas da organização + turmas onde é professor atribuído
        $user = auth()->user();
        $schoolClasses = SchoolClass::where('organization_id', $orgId)
            ->when($user->hasWorkspaceRole('teacher'), function ($query) use ($user) {
                $query->where(function ($scope) use ($user) {
                    $scope->where(fn ($owner) => $owner
                        ->where('owner_type', 'user')
                        ->where('owner_id', $user->id))
                        ->orWhereHas('teachers', fn ($teachers) => $teachers->where('users.id', $user->id));
                });
            })
            ->withCount('students')
            ->with('teachers')
            ->paginate(10);

        return view('institution.classes.index', compact('schoolClasses'));
    }

    public function create()
    {
        $organizationId = $this->currentOrganizationId();
        $this->authorizeClassCreation();
        $disciplines = Discipline::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->orderBy('name')->get(['id', 'name']);

        return view('institution.classes.create', compact('disciplines'));
    }

    public function store(Request $request)
    {
        $this->authorizeClassCreation();
        $orgId = $this->currentOrganizationId();

        if (! $this->canAddClass()) {
            return back()->withInput()->withErrors(['Limite de turmas atingido no plano atual da instituição.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|string|max:255',
            'discipline_ids' => ['nullable', 'array'],
            'discipline_ids.*' => ['integer', 'distinct'],
        ]);

        $user = auth()->user();
        DB::transaction(function () use ($orgId, $user, $validated): void {
            $personal = $user->organization->isPersonalWorkspace();
            $class = SchoolClass::create([
                'organization_id' => $orgId,
                'owner_type' => $personal ? 'user' : 'organization',
                'owner_id' => $personal ? $user->id : $orgId,
                'name' => $validated['name'],
                'year' => $validated['year'],
            ]);

            app(AcademicRelationshipService::class)->syncClass(
                $class,
                [],
                $validated['discipline_ids'] ?? [],
                $user,
            );

            AuditLog::log('created', SchoolClass::class, $class->id);
        });

        return redirect()->route('institution.classes.index')->with('status', 'Turma criada com sucesso!');
    }

    public function edit(string $id)
    {
        $schoolClass = $this->findClass($id);

        $orgId = $this->currentOrganizationId();
        $availableTeachers = User::query()
            ->where('status', 'active')
            ->memberOfOrganization($orgId, 'teacher')
            ->get();
        $availableDisciplines = Discipline::withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->orderBy('name')->get(['id', 'name']);

        $assignedTeacherIds = $schoolClass->teachers()->pluck('users.id')->toArray();
        $assignedDisciplineIds = $schoolClass->disciplines()->pluck('disciplines.id')->toArray();

        return view('institution.classes.edit', compact(
            'schoolClass',
            'availableTeachers',
            'availableDisciplines',
            'assignedTeacherIds',
            'assignedDisciplineIds',
        ));
    }

    public function update(Request $request, string $id)
    {
        $schoolClass = $this->findClass($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'nullable|string|max:20',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => ['integer', 'distinct', new ActiveOrganizationMember((int) $schoolClass->organization_id, 'teacher')],
            'discipline_ids' => ['nullable', 'array'],
            'discipline_ids.*' => ['integer', 'distinct'],
        ]);

        DB::transaction(function () use ($schoolClass, $validated): void {
            $schoolClass->update([
                'name' => $validated['name'],
                'year' => $validated['year'] ?? $schoolClass->year,
            ]);

            app(AcademicRelationshipService::class)->syncClass(
                $schoolClass,
                $validated['teacher_ids'] ?? [],
                $validated['discipline_ids'] ?? [],
                auth()->user(),
            );

            AuditLog::log('updated', SchoolClass::class, $schoolClass->id);
        });

        return redirect()->route('institution.classes.index')->with('status', 'Turma atualizada.');
    }

    /**
     * Enviar convite de enrollment (aluno → turma).
     */
    public function enroll(Request $request, string $id)
    {
        $schoolClass = $this->findClass($id);

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', new ActiveOrganizationMember((int) $schoolClass->organization_id, 'student')],
        ]);

        $student = User::findOrFail($validated['student_user_id']);

        // Verificar se já está na turma
        if ($schoolClass->students()->where('users.id', $student->id)->exists()) {
            return back()->withErrors(['Este aluno já está matriculado nesta turma.']);
        }

        $inviteManager = app(InviteManagerService::class);
        $inviteManager->send(
            inviter: auth()->user(),
            email: $student->email,
            targetRole: 'student',
            organization: auth()->user()->organization,
            inviteType: 'class_enrollment',
            inviteeUserId: $student->id,
            targetEntityType: SchoolClass::class,
            targetEntityId: $schoolClass->id,
        );

        return back()->with('status', "Convite de matrícula enviado para {$student->name}!");
    }

    /**
     * Iniciar transferência de propriedade da turma.
     */
    public function initiateTransfer(Request $request, string $id)
    {
        $schoolClass = $this->findClass($id);

        $validated = $request->validate([
            'recipient_type' => 'required|in:user,organization',
            'recipient_id' => 'required|integer',
        ]);

        $service = app(ClassOwnershipService::class);
        $invite = $service->initiateTransfer(
            $schoolClass,
            auth()->user(),
            $validated['recipient_type'],
            $validated['recipient_id'],
        );

        return back()->with('status', 'Transferência de turma iniciada! Aguardando aceite do destinatário.');
    }

    /**
     * Cancelar transferência pendente de turma.
     */
    public function cancelTransfer(Request $request, string $id)
    {
        $schoolClass = $this->findClass($id);

        $invite = Invite::where('invite_type', 'class_ownership_transfer')
            ->where('target_entity_type', SchoolClass::class)
            ->where('target_entity_id', $schoolClass->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $inviteManager = app(InviteManagerService::class);
        $inviteManager->cancel($invite, auth()->user());

        return back()->with('status', 'Transferência cancelada.');
    }

    public function destroy(string $id)
    {
        $schoolClass = $this->findClass($id);
        $schoolClass->delete();

        AuditLog::log('deleted', SchoolClass::class, $schoolClass->id);

        return redirect()->route('institution.classes.index')->with('status', 'Turma excluída.');
    }

    /**
     * Encontra turma no escopo da organização ou como professor atribuído.
     */
    private function findClass(string $id): SchoolClass
    {
        $orgId = $this->currentOrganizationId();
        $class = SchoolClass::where('organization_id', $orgId)->findOrFail($id);
        $user = auth()->user();

        abort_unless(
            app(InstitutionPermissionService::class)->allows(
                $user,
                'manage_classes',
                (int) $class->organization_id,
            )
                || ($user->organization->isPersonalWorkspace() && $class->isOwnedBy($user)),
            403,
        );

        return $class;
    }

    private function authorizeClassCreation(): void
    {
        $user = auth()->user();
        abort_unless(
            app(InstitutionPermissionService::class)->allows(
                $user,
                'manage_classes',
                (int) $user->organization_id,
            ),
            403,
        );
    }

    private function authorizeClassViewing(): void
    {
        $user = auth()->user();
        abort_unless(
            app(InstitutionPermissionService::class)->allows(
                $user,
                'view_classes',
                (int) $user->organization_id,
            ),
            403,
        );
    }

    private function canAddClass(): bool
    {
        $user = auth()->user();
        if (! $user->organization->isPersonalWorkspace()) {
            return $user->organization->canAddClass();
        }

        $plan = app(EntitlementService::class)->effectivePlan($user);
        if (! $plan) {
            return false;
        }

        $limit = $plan->getLimit('max_classes');
        $current = SchoolClass::where('organization_id', $user->organization_id)->count();

        return $limit === null || $current < $limit;
    }

    private function currentOrganizationId(): int
    {
        $user = auth()->user();
        $organizationId = (int) $user->organization_id;

        abort_unless($user->canUseOrganizationContext($organizationId), 403, 'Selecione uma instituição ativa.');

        return $organizationId;
    }
}
