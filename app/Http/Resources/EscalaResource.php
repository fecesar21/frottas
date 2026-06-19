<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EscalaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'motorista_id' => $this->motorista_id,
            'veiculo_id'   => $this->veiculo_id,
            'data'         => $this->data,
            'turno'        => $this->turno,
            'observacao'   => $this->observacao,
            'motorista'    => $this->whenLoaded('motorista', fn () => new MotoristaResource($this->motorista)),
            'veiculo'      => $this->whenLoaded('veiculo', fn () => new VeiculoResource($this->veiculo)),
        ];
    }
}
