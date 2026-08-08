<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SolicitacaoRecusadaPeloMotorista extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Solicitacao $solicitacao,
        protected string $motoristaNome,
        protected string $motivo
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'solicitacao_id' => $this->solicitacao->id,
            'motorista_nome' => $this->motoristaNome,
            'motivo_recusa' => $this->motivo,
        ];
    }
}
