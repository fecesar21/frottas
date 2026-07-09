<?php

namespace App\Notifications;

use App\Models\Veiculo;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ManutencaoPendenteNotification extends Notification
{
    use Queueable;

    public function __construct(protected Veiculo $veiculo) {}

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
            ->subject('Veículo com manutenção pendente')
            ->greeting('Alerta de frota')
            ->line("O veículo de placa {$this->veiculo->placa} atingiu o km de revisão.")
            ->line("Km atual: {$this->veiculo->km_atual} / Km da próxima revisão: {$this->veiculo->km_proxima_revisao}.")
            ->line('Agende a manutenção o quanto antes.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'veiculo_id' => $this->veiculo->id,
            'placa' => $this->veiculo->placa,
            'km_atual' => $this->veiculo->km_atual,
            'km_proxima_revisao' => $this->veiculo->km_proxima_revisao,
        ];
    }
}
