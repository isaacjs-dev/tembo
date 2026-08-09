<?php

namespace App\Notifications;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InstitutionInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Invite $invite) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = $this->invite->organization?->name ?? 'Tembo';

        return (new MailMessage)
            ->subject("Convite para {$organizationName}")
            ->greeting('Você recebeu um convite')
            ->line("{$organizationName} convidou você para participar do workspace no Tembo.")
            ->line('A conta é sua: você definirá a própria senha ou entrará com uma conta existente.')
            ->action('Abrir convite', route('invite.activation.show', $this->invite->token))
            ->line('Este convite expira em 7 dias. Ignore esta mensagem se você não reconhece o convite.');
    }
}
