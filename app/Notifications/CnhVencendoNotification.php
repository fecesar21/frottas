<?php

namespace App\Notifications;

use App\Models\Motorista;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CnhVencendoNotification extends Notification
{
    use Queueable;

    public function __construct(protected Motorista $motorista) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CNH prestes a vencer')
            ->greeting('Alerta de frota')
            ->line("A CNH do motorista {$this->motorista->nome} vence em {$this->motorista->dias_para_vencer_cnh} dia(s).")
            ->line('Verifique a documentação o quanto antes.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'motorista_id' => $this->motorista->id,
            'nome' => $this->motorista->nome,
            'dias_para_vencer' => $this->motorista->dias_para_vencer_cnh,
        ];
    }
}
