<?php

namespace App\Notifications;

use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada a motoristas em atividade elegíveis para uma nova solicitação,
 * que podem assumi-la diretamente.
 */
class NovaSolicitacaoDisponivel extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail', 'database'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova solicitação de transporte disponível')
            ->greeting('Há uma nova solicitação disponível para você')
            ->line("Solicitante: {$this->solicitacao->usuario?->nome}")
            ->line("Motivo: {$this->solicitacao->motivo}")
            ->line("Detalhe: {$this->detalhe()}")
            ->action('Abrir o sistema', url('/'))
            ->line('Acesse o sistema para assumir essa viagem.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tipo' => 'solicitacao_disponivel',
            'solicitacao_id' => $this->solicitacao->id,
            'motivo' => $this->solicitacao->motivo,
            'detalhe' => $this->detalhe(),
            'solicitante_nome' => $this->solicitacao->usuario?->nome,
            'unidade_id' => $this->solicitacao->unidade_id,
        ];
    }

    private function detalhe(): string
    {
        $origem = $this->solicitacao->origem();
        $destino = $this->solicitacao->destino();

        if ($origem || $destino) {
            return ($origem?->nome ?? '—').' → '.($destino?->nome ?? '—');
        }

        return $this->solicitacao->cidade ?? $this->solicitacao->hospital_destino ?? $this->solicitacao->fornecedor_nome ?? 'Sem detalhe';
    }
}
