<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ClassOwnershipLog;
use App\Models\Invite;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassOwnershipService
{
    /**
     * Inicia transferência de propriedade de turma via convite.
     *
     * @param  SchoolClass  $class  Turma a transferir
     * @param  User  $initiator  Quem inicia a transferência
     * @param  string  $recipientType  'user' ou 'organization'
     * @param  int  $recipientId  ID do destinatário
     */
    public function initiateTransfer(
        SchoolClass $class,
        User $initiator,
        string $recipientType,
        int $recipientId,
    ): Invite {
        // Validar que o initiator é dono da turma
        $this->assertOwnership($class, $initiator);

        // Validar que não há transferência pendente
        $pendingTransfer = Invite::where('invite_type', 'class_ownership_transfer')
            ->where('target_entity_type', SchoolClass::class)
            ->where('target_entity_id', $class->id)
            ->where('status', 'pending')
            ->exists();

        if ($pendingTransfer) {
            throw ValidationException::withMessages([
                'transfer' => 'Já existe uma transferência pendente para esta turma.',
            ]);
        }

        // Resolver email e user_id do destinatário
        if ($recipientType === 'user') {
            $recipient = User::findOrFail($recipientId);
            $email = $recipient->email;
            $inviteeUserId = $recipient->id;
        } else {
            $org = Organization::findOrFail($recipientId);
            $email = $org->owner?->email ?? '';
            $inviteeUserId = $org->owner_user_id;
        }

        // Validar capacidade do destinatário (se org, verificar max_classes)
        if ($recipientType === 'organization') {
            $org = Organization::findOrFail($recipientId);
            if (! $org->canAddClass()) {
                throw ValidationException::withMessages([
                    'transfer' => 'A instituição destino atingiu o limite de turmas do plano.',
                ]);
            }
        }

        $inviteManager = app(InviteManagerService::class);

        $invite = Invite::create([
            'inviter_id' => $initiator->id,
            'organization_id' => $class->organization_id,
            'invitee_email' => $email,
            'invitee_user_id' => $inviteeUserId,
            'target_role' => 'owner',
            'invite_type' => 'class_ownership_transfer',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $class->id,
        ]);

        AuditLog::log('transfer_initiated', SchoolClass::class, $class->id, [
            'invite_id' => $invite->id,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
        ]);

        return $invite;
    }

    /**
     * Processa aceite de transferência de propriedade.
     */
    public function acceptTransfer(Invite $invite, User $acceptor): void
    {
        if ($invite->invite_type !== 'class_ownership_transfer') {
            throw ValidationException::withMessages([
                'invite' => 'Este convite não é de transferência de turma.',
            ]);
        }

        $class = SchoolClass::withoutGlobalScopes()->findOrFail($invite->target_entity_id);

        DB::transaction(function () use ($invite, $class, $acceptor) {
            $previousOwnerType = $class->owner_type;
            $previousOwnerId = $class->owner_id;

            // Determinar novo owner
            if ($acceptor->type === 'institution_admin') {
                $newOwnerType = 'organization';
                $newOwnerId = $acceptor->organization_id;
                $newOrgId = $acceptor->organization_id;
            } else {
                $newOwnerType = 'user';
                $newOwnerId = $acceptor->id;
                $newOrgId = $acceptor->organization_id;
            }

            // Atualizar propriedade da turma
            $class->update([
                'owner_type' => $newOwnerType,
                'owner_id' => $newOwnerId,
                'organization_id' => $newOrgId,
            ]);

            // Registrar log de transferência
            ClassOwnershipLog::create([
                'school_class_id' => $class->id,
                'previous_owner_type' => $previousOwnerType,
                'previous_owner_id' => $previousOwnerId,
                'new_owner_type' => $newOwnerType,
                'new_owner_id' => $newOwnerId,
                'initiated_by' => $invite->inviter_id,
            ]);

            // Aceitar o convite
            $invite->accept();

            AuditLog::log('transfer_accepted', SchoolClass::class, $class->id, [
                'invite_id' => $invite->id,
                'previous_owner' => "{$previousOwnerType}:{$previousOwnerId}",
                'new_owner' => "{$newOwnerType}:{$newOwnerId}",
            ]);
        });
    }

    /**
     * Verifica se o usuário é proprietário da turma.
     */
    private function assertOwnership(SchoolClass $class, User $user): void
    {
        $isOwner = false;

        if ($class->owner_type === 'organization' && $class->owner_id == $user->organization_id) {
            // Admin da org é dono
            $isOwner = $user->type === 'institution_admin';
        } elseif ($class->owner_type === 'user' && $class->owner_id == $user->id) {
            $isOwner = true;
        }

        if (! $isOwner) {
            throw ValidationException::withMessages([
                'transfer' => 'Você não tem permissão para transferir esta turma.',
            ]);
        }
    }
}
