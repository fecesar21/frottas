<?php

namespace App\Notifications;

use App\Models\MotivoViagem;
use App\Models\Solicitacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NovaViagemDesignada extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected Solicitacao $solicitacao, protected bool $fila = false) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * Push no celular do motorista, para o aviso chegar com o app fechado.
     */
    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Nova viagem para você')
            ->body(MotivoViagem::rotulo($this->solicitacao->motivo).' — '.$this->detalhe())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-96.png')
            ->tag('viagem-designada-'.$this->solicitacao->id)
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
            'solicitacao_id' => $this->solicitacao->id,
            'motivo' => $this->solicitacao->motivo,
            'motivo_nome' => MotivoViagem::rotulo($this->solicitacao->motivo),
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
