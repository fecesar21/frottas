<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AbastecimentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'veiculo_id'    => $this->veiculo_id,
            'motorista_id'  => $this->motorista_id,
            'checkin_id'    => $this->checkin_id,
            'posto'         => $this->posto,
            'combustivel'   => $this->combustivel,
            'litros'        => (float) $this->litros,
            'valor_litro'   => (float) $this->valor_litro,
            'valor_total'   => round((float) $this->litros * (float) $this->valor_litro, 2),
            'km_momento'    => $this->km_momento,
            'abastecido_at' => $this->abastecido_at,
            'nota_fiscal'   => $this->nota_fiscal,
            'observacoes'   => $this->observacoes,
            'motorista'     => $this->whenLoaded('motorista', fn () => new MotoristaResource($this->motorista)),
            'veiculo'       => $this->whenLoaded('veiculo', fn () => new VeiculoResource($this->veiculo)),
        ];
    }
}
