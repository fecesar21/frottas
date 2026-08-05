<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NovaSolicitacaoTransporte extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $detalhe = $this->detalhe();

        return (new MailMessage)
            ->subject('Nova solicitação de transporte')
            ->greeting('Nova solicitação recebida')
            ->line("Solicitante: {$this->solicitacao->usuario?->nome}")
            ->line("Motivo: {$this->solicitacao->motivo}")
            ->line("Detalhe: {$detalhe}")
            ->action('Ver solicitações', url('/solicitacoes'))
            ->line('Acesse o sistema para aceitar ou tratar essa solicitação.');
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
            'solicitante_nome' => $this->solicitacao->usuario?->nome,
            'unidade_id' => $this->solicitacao->unidade_id,
        ];
    }

    private function detalhe(): string
    {
        $this->solicitacao->loadMissing(['origemUnidade', 'destinoUnidade']);

        if ($this->solicitacao->origemUnidade || $this->solicitacao->destinoUnidade) {
            return ($this->solicitacao->origemUnidade?->nome ?? '—').' → '.($this->solicitacao->destinoUnidade?->nome ?? '—');
        }

        return $this->solicitacao->cidade ?? $this->solicitacao->hospital_destino ?? 'Sem detalhe';
    }
}
