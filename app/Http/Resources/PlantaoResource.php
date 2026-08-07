<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantaoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'veiculo_id'           => $this->veiculo_id,
            'motorista_saindo_id'  => $this->motorista_saindo_id,
            'motorista_entrando_id'=> $this->motorista_entrando_id,
            'turno_saindo'         => $this->turno_saindo,
            'turno_entrando'       => $this->turno_entrando,
            'km_momento'           => $this->km_momento,
            'nivel_combustivel'    => $this->nivel_combustivel,
            'total_itens'          => $this->total_itens,
            'itens_ok'             => $this->itens_ok,
            'itens_pendencia'      => $this->itens_pendencia,
            'observacoes_gerais'   => $this->observacoes_gerais,
            'finalizado_at'        => $this->finalizado_at,
            'veiculo'              => $this->whenLoaded('veiculo', fn () => new VeiculoResource($this->veiculo)),
            'motorista_saindo'     => $this->whenLoaded('motoristaSaindo', fn () => new MotoristaResource($this->motoristaSaindo)),
            'motorista_entrando'   => $this->whenLoaded('motoristaEntrando', fn () => new MotoristaResource($this->motoristaEntrando)),
            'respostas'            => $this->whenLoaded('respostas'),
            'criado_em'            => $this->created_at,
        ];
    }
}
