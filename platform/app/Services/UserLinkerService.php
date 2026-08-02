<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Invite;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserLinkerService
{
    /**
     * Aceita um convite e executa a ação correspondente.
     * Suporta: org_teacher, org_student, class_enrollment, class_ownership_transfer.
     */
    public function acceptInvite(Invite $invite, User $user): void
    {
        if (! $invite->isPending()) {
            throw ValidationException::withMessages([
                'invite' => 'Este convite não está mais pendente.',
            ]);
        }

        if ($invite->isExpired()) {
            $invite->update(['status' => 'expired']);
            throw ValidationException::withMessages([
                'invite' => 'Este convite expirou.',
            ]);
        }

        if ($user->email !== $invite->invitee_email) {
            throw ValidationException::withMessages([
                'invite' => 'Este convite foi enviado para outro e-mail.',
            ]);
        }

        // Despachar por tipo de convite
        match ($invite->invite_type) {
            'org_teacher', 'org_student' => $this->acceptOrgInvite($invite, $user),
            'class_enrollment' => $this->acceptClassEnrollment($invite, $user),
            'class_ownership_transfer' => $this->acceptClassTransfer($invite, $user),
            default => $this->acceptOrgInvite($invite, $user),
        };
    }

    /**
     * Aceita convite de vínculo a organização (professor ou aluno).
     */
    private function acceptOrgInvite(Invite $invite, User $user): void
    {
        DB::transaction(function () use ($invite, $user) {
            $invite->accept();

            if ($invite->organization_id) {
                $user->organizations()->syncWithoutDetaching([
                    $invite->organization_id => [
                        'role_in_org' => $invite->target_role,
                        'status' => 'active',
                        'joined_at' => now(),
                    ],
                ]);

                if (! $user->organization_id) {
                    $user->update(['organization_id' => $invite->organization_id]);
                }
            }

            $this->purgeEffectivePlanCache($user);

            AuditLog::log('invite_accepted', Invite::class, $invite->id, [
                'invite_type' => $invite->invite_type,
                'organization_id' => $invite->organization_id,
            ]);
        });
    }

    /**
     * Aceita convite de matrícula em turma (class_enrollment).
     */
    private function acceptClassEnrollment(Invite $invite, User $user): void
    {
        DB::transaction(function () use ($invite, $user) {
            $invite->accept();

            $classId = $invite->target_entity_id;
            $class = SchoolClass::withoutGlobalScopes()->findOrFail($classId);

            // Matricular aluno na turma (sem duplicar)
            $user->schoolClasses()->syncWithoutDetaching([$classId]);

            // Se aluno não está vinculado à org da turma, vincular
            if ($invite->organization_id) {
                $user->organizations()->syncWithoutDetaching([
                    $invite->organization_id => [
                        'role_in_org' => 'student',
                        'status' => 'active',
                        'joined_at' => now(),
                    ],
                ]);

                if (! $user->organization_id) {
                    $user->update(['organization_id' => $invite->organization_id]);
                }
            }

            $this->purgeEffectivePlanCache($user);

            AuditLog::log('invite_accepted', Invite::class, $invite->id, [
                'invite_type' => 'class_enrollment',
                'class_id' => $classId,
                'class_name' => $class->name,
            ]);
        });
    }

    /**
     * Aceita convite de transferência de propriedade de turma.
     * Delega ao ClassOwnershipService.
     */
    private function acceptClassTransfer(Invite $invite, User $user): void
    {
        $service = app(ClassOwnershipService::class);
        $service->acceptTransfer($invite, $user);
    }

    /**
     * Recusa um convite.
     */
    public function declineInvite(Invite $invite, User $user): void
    {
        if (! $invite->isPending()) {
            throw ValidationException::withMessages([
                'invite' => 'Este convite não está mais pendente.',
            ]);
        }

        if ($user->email !== $invite->invitee_email) {
            throw ValidationException::withMessages([
                'invite' => 'Este convite foi enviado para outro e-mail.',
            ]);
        }

        $invite->decline();

        AuditLog::log('invite_declined', Invite::class, $invite->id, [
            'invite_type' => $invite->invite_type,
        ]);
    }

    /**
     * Desvincula um usuário de uma organização.
     * Remove acesso a turmas institucionais e recalcula plano efetivo.
     */
    public function unlink(User $user, int $organizationId): void
    {
        DB::transaction(function () use ($user, $organizationId) {
            $user->organizations()->updateExistingPivot($organizationId, [
                'status' => 'inactive',
            ]);

            if ($user->organization_id === $organizationId) {
                $nextOrg = $user->activeOrganizations()->first();
                $user->update(['organization_id' => $nextOrg?->id]);
            }

            $classIds = SchoolClass::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->pluck('id');
            $user->schoolClasses()->detach($classIds);

            $this->purgeEffectivePlanCache($user);

            AuditLog::log('unlinked', User::class, $user->id, [
                'organization_id' => $organizationId,
            ]);
        });
    }

    /**
     * Invalida o cache do plano efetivo do usuário.
     */
    private function purgeEffectivePlanCache(User $user): void
    {
        cache()->forget("effective_plan_user_{$user->id}");
    }
}
