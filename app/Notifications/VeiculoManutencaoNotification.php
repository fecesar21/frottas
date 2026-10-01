<?php

namespace App\Notifications;

use App\Models\Veiculo;
use App\Models\VeiculoManutencao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Avisa a gestão quando um motorista coloca/retira um veículo da manutenção.
 */
class VeiculoManutencaoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected VeiculoManutencao $manutencao,
        protected Veiculo $veiculo,
        protected string $evento,
        protected string $responsavel,
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
            'tipo' => $this->evento === 'entrada' ? 'veiculo_entrou_manutencao' : 'veiculo_saiu_manutencao',
            'manutencao_id' => $this->manutencao->id,
            'veiculo_id' => $this->veiculo->id,
            'placa' => $this->veiculo->placa,
            'tipo_manutencao' => $this->manutencao->tipo,
            'tipo_manutencao_label' => VeiculoManutencao::rotuloTipo($this->manutencao->tipo),
            'motivo' => $this->manutencao->motivo,
            'responsavel' => $this->responsavel,
        ];
    }
}
