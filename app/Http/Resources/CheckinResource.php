<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'motorista_id'               => $this->motorista_id,
            'veiculo_id'                 => $this->veiculo_id,
            'escala_id'                  => $this->escala_id,
            'turno'                      => $this->turno,
            'km_saida'                   => $this->km_saida,
            'km_retorno'                 => $this->km_retorno,
            'nivel_combustivel_saida'    => $this->nivel_combustivel_saida,
            'nivel_combustivel_retorno'  => $this->nivel_combustivel_retorno,
            'checkin_at'                 => $this->checkin_at,
            'checkout_at'                => $this->checkout_at,
            'duracao_min'                => $this->when($this->checkin_at, fn () => (int) now()->diffInMinutes($this->checkin_at)),
            'status'                     => $this->status,
            'ocorrencias'                => $this->ocorrencias,
            'motorista'                  => $this->whenLoaded('motorista', fn () => new MotoristaResource($this->motorista)),
            'veiculo'                    => $this->whenLoaded('veiculo', fn () => new VeiculoResource($this->veiculo)),
        ];
    }
}
