<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SolicitacaoRecusadaPelaGestao extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Solicitacao $solicitacao,
        protected string $gestorNome,
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
            'tipo' => 'solicitacao_recusada_gestao',
            'solicitacao_id' => $this->solicitacao->id,
            'gestor_nome' => $this->gestorNome,
            'motivo_recusa' => $this->motivo,
        ];
    }
}
