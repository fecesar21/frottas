<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NovaViagemDesignada extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao, protected bool $fila = false) {}

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
            'motivo' => $this->solicitacao->motivo,
            'detalhe' => $this->detalhe(),
            'fila' => $this->fila,
        ];
    }

    private function detalhe(): string
    {
        $origem = $this->solicitacao->origem();
        $destino = $this->solicitacao->destino();

        if ($origem || $destino) {
            return ($origem?->nome ?? '—').' → '.($destino?->nome ?? '—');
        }

        return $this->solicitacao->cidade ?? $this->solicitacao->hospital_destino ?? 'Sem detalhe';
    }
}
