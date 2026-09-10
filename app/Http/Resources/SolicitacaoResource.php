<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitacaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'usuario_id' => $this->usuario_id,
            'unidade_id' => $this->unidade_id,
            'motivo' => $this->motivo,
            'origem_tipo' => $this->origem_tipo,
            'origem_id' => $this->origem_id,
            'destino_tipo' => $this->destino_tipo,
            'destino_id' => $this->destino_id,
            'numero_atendimento' => $this->numero_atendimento,
            'cidade' => $this->cidade,
            'hospital_destino' => $this->hospital_destino,
            'fornecedor_nome' => $this->fornecedor_nome,
            'status' => $this->status,
            'motivo_recusa' => $this->when(in_array($request->user()?->perfil, ['admin', 'gestor']), $this->motivo_recusa),
            'viagem_id' => $this->viagem_id,
            'observacoes' => $this->observacoes,
            'usuario_nome' => $this->whenLoaded('usuario', fn () => $this->usuario->nome),
            'origem' => $this->origem()?->nome,
            'destino' => $this->destino()?->nome,
            'motorista_nome' => $this->viagem?->motorista?->nome ?? $this->motoristaPendente?->nome,
            'veiculo_placa' => $this->viagem?->veiculo?->placa ?? $this->veiculoPendente?->placa,
            'saida_at' => $this->viagem?->saida_at,
            'chegada_at' => $this->viagem?->chegada_at,
            'criado_em' => $this->created_at,
        ];
    }
}
