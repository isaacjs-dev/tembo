<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InstitutionInviteNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class InviteManagerService
{
    /**
     * Cria e envia um convite.
     *
     * @param  string  $inviteType  org_teacher|org_student|class_enrollment|class_ownership_transfer
     * @param  int|null  $inviteeUserId  ID do usuário existente sendo convidado
     * @param  string|null  $targetEntityType  App\Models\SchoolClass, etc.
     * @param  int|null  $targetEntityId  ID da turma, etc.
     *
     * @throws ValidationException
     */
    public function send(
        User $inviter,
        string $email,
        string $targetRole,
        ?Organization $organization = null,
        string $inviteType = 'org_teacher',
        ?int $inviteeUserId = null,
        ?string $targetEntityType = null,
        ?int $targetEntityId = null,
    ): Invite {
        $email = mb_strtolower(trim($email));

        // Validações de negócio
        if ($organization) {
            abort_unless($inviter->canUseOrganizationContext((int) $organization->id), 403);

            if (str_starts_with($inviteType, 'org_')) {
                $allowedRoles = app(InstitutionPermissionService::class)
                    ->invitationalRoles($inviter, (int) $organization->id);

                if (! in_array($targetRole, $allowedRoles, true)) {
                    throw ValidationException::withMessages([
                        'target_role' => 'Seu papel não pode convidar este perfil institucional.',
                    ]);
                }
            }
        }

        $this->validateNoDuplicate($email, $organization, $inviteType, $targetEntityType, $targetEntityId);
        $this->validateLimits($organization, $targetRole);

        $invite = Invite::create([
            'inviter_id' => $inviter->id,
            'organization_id' => $organization?->id,
            'invitee_email' => $email,
            'invitee_user_id' => $inviteeUserId,
            'target_role' => $targetRole,
            'invite_type' => $inviteType,
            'target_entity_type' => $targetEntityType,
            'target_entity_id' => $targetEntityId,
        ]);

        // Registrar audit log
        AuditLog::log('invite_sent', Invite::class, $invite->id, [
            'invite_type' => $inviteType,
            'invitee_email' => $email,
            'target_role' => $targetRole,
            'organization_id' => $organization?->id,
        ]);

        // Enviar email
        $this->sendEmail($invite);

        return $invite;
    }

    /**
     * Cancela um convite pendente.
     */
    public function cancel(Invite $invite, User $actor): void
    {
        if ($invite->organization_id) {
            abort_unless($actor->canUseOrganizationContext((int) $invite->organization_id), 403);
        }

        if (! $invite->isPending()) {
            throw ValidationException::withMessages([
                'invite' => 'Apenas convites pendentes podem ser cancelados.',
            ]);
        }

        $invite->cancel();

        AuditLog::log('invite_canceled', Invite::class, $invite->id, [
            'organization_id' => $invite->organization_id,
            'invite_type' => $invite->invite_type,
        ]);
    }

    /**
     * Expira convites vencidos (usado pelo Artisan Command).
     */
    public function expirePending(): int
    {
        return Invite::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }

    /**
     * Valida que não existe convite pendente duplicado.
     */
    private function validateNoDuplicate(
        string $email,
        ?Organization $organization,
        string $inviteType,
        ?string $targetEntityType,
        ?int $targetEntityId
    ): void {
        $exists = Invite::pending()
            ->forEmail($email)
            ->where('invite_type', $inviteType)
            ->when($organization, fn ($q) => $q->where('organization_id', $organization->id))
            ->when($targetEntityType, fn ($q) => $q->where('target_entity_type', $targetEntityType)->where('target_entity_id', $targetEntityId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => 'Já existe um convite pendente para este e-mail.',
            ]);
        }
    }

    /**
     * Valida se o plano da organização ainda permite adicionar o role.
     */
    private function validateLimits(?Organization $organization, string $targetRole): void
    {
        if (! $organization) {
            return;
        }

        $limiterService = app(PlanLimiterService::class);

        if ($targetRole === 'teacher') {
            $current = User::query()->memberOfOrganization((int) $organization->id, 'teacher')->count();
            if (! $limiterService->canCreate($organization, 'max_teachers', $current)) {
                throw ValidationException::withMessages([
                    'limit' => 'Limite de professores do plano foi atingido.',
                ]);
            }
        }

        if ($targetRole === 'student') {
            $current = User::query()->memberOfOrganization((int) $organization->id, 'student')->count();
            if (! $limiterService->canCreate($organization, 'max_students', $current)) {
                throw ValidationException::withMessages([
                    'limit' => 'Limite de alunos do plano foi atingido.',
                ]);
            }
        }
    }

    /**
     * Envia o email de convite.
     */
    private function sendEmail(Invite $invite): void
    {
        $invite->loadMissing('organization:id,name');
        Notification::route('mail', $invite->invitee_email)
            ->notify(new InstitutionInviteNotification($invite));
    }
}
