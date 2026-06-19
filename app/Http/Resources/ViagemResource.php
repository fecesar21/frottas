<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ViagemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'veiculo_id'   => $this->veiculo_id,
            'motorista_id' => $this->motorista_id,
            'checkin_id'   => $this->checkin_id,
            'origem'       => $this->origem,
            'destino'      => $this->destino,
            'km_saida'     => $this->km_saida,
            'km_chegada'   => $this->km_chegada,
            'km_percorrido'=> $this->when($this->km_chegada, fn () => $this->km_chegada - $this->km_saida),
            'saida_at'     => $this->saida_at,
            'chegada_at'   => $this->chegada_at,
            'status'       => $this->status,
            'observacoes'  => $this->observacoes,
            'motorista'    => $this->whenLoaded('motorista', fn () => new MotoristaResource($this->motorista)),
            'veiculo'      => $this->whenLoaded('veiculo', fn () => new VeiculoResource($this->veiculo)),
            'criado_em'    => $this->created_at,
        ];
    }
}
