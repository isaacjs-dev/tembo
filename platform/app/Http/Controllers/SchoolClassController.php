<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\SchoolClass;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use App\Services\ClassOwnershipService;
use App\Services\InviteManagerService;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    public function index()
    {
        $orgId = $this->currentOrganizationId();
        // Turmas da organização + turmas onde é professor atribuído
        $schoolClasses = SchoolClass::where('organization_id', $orgId)
            ->withCount('students')
            ->with('teachers')
            ->paginate(10);

        return view('institution.classes.index', compact('schoolClasses'));
    }

    public function create()
    {
        $this->currentOrganizationId();

        return view('institution.classes.create');
    }

    public function store(Request $request)
    {
        abort_unless(in_array(auth()->user()->type, ['institution_admin', 'global_admin']), 403);
        $orgId = $this->currentOrganizationId();

        if (! auth()->user()->organization->canAddClass()) {
            return back()->withInput()->withErrors(['Limite de turmas atingido no plano atual da instituição.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'required|string|max:255',
        ]);

        $class = SchoolClass::create([
            'organization_id' => $orgId,
            'owner_type' => 'organization',
            'owner_id' => $orgId,
            'name' => $validated['name'],
            'year' => $validated['year'],
        ]);

        AuditLog::log('created', SchoolClass::class, $class->id);

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

        $assignedTeacherIds = $schoolClass->teachers()->pluck('users.id')->toArray();

        return view('institution.classes.edit', compact('schoolClass', 'availableTeachers', 'assignedTeacherIds'));
    }

    public function update(Request $request, string $id)
    {
        $schoolClass = $this->findClass($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'year' => 'nullable|string|max:20',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => ['integer', 'distinct', new ActiveOrganizationMember((int) $schoolClass->organization_id, 'teacher')],
        ]);

        $schoolClass->update([
            'name' => $validated['name'],
            'year' => $validated['year'] ?? $schoolClass->year,
        ]);

        // Sync professores atribuídos
        if (isset($validated['teacher_ids'])) {
            $syncData = [];
            foreach ($validated['teacher_ids'] as $tid) {
                $syncData[$tid] = ['assigned_at' => now()];
            }
            $schoolClass->teachers()->sync($syncData);
        } else {
            $schoolClass->teachers()->detach();
        }

        AuditLog::log('updated', SchoolClass::class, $schoolClass->id);

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
        $inviteManager->cancel($invite);

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

        return SchoolClass::where('organization_id', $orgId)->findOrFail($id);
    }

    private function currentOrganizationId(): int
    {
        $user = auth()->user();
        $organizationId = (int) $user->organization_id;

        abort_unless($user->canUseOrganizationContext($organizationId), 403, 'Selecione uma instituição ativa.');

        return $organizationId;
    }
}
