<?php

namespace App\Notifications;

use App\Models\MotivoViagem;
use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

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
        $canais = $notifiable->email ? ['mail', 'database'] : ['database'];

        return [...$canais, WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nova solicitação de transporte disponível')
            ->greeting('Há uma nova solicitação disponível para você')
            ->line("Solicitante: {$this->solicitacao->usuario?->nome}")
            ->line('Motivo: '.MotivoViagem::rotulo($this->solicitacao->motivo))
            ->line("Detalhe: {$this->detalhe()}")
            ->action('Abrir o sistema', url('/'))
            ->line('Acesse o sistema para assumir essa viagem.');
    }

    /**
     * Push no celular do motorista, para o aviso chegar com o app fechado.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nova solicitação disponível')
            ->body(MotivoViagem::rotulo($this->solicitacao->motivo).' — '.$this->detalhe())
            ->icon('/app-icons/icon-192.png')
            ->badge('/app-icons/badge-96.png')
            ->tag('solicitacao-disponivel-'.$this->solicitacao->id)
            ->renotify()
            ->requireInteraction()
            ->vibrate([300, 150, 300, 150, 300])
            ->data(['url' => '/', 'solicitacao_id' => $this->solicitacao->id])
            ->options(['TTL' => 1800, 'urgency' => 'high']);
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
            'motivo_nome' => MotivoViagem::rotulo($this->solicitacao->motivo),
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
