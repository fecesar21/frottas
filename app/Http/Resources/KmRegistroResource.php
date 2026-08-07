<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KmRegistroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'veiculo_id'    => $this->veiculo_id,
            'motorista_id'  => $this->motorista_id,
            'checkin_id'    => $this->checkin_id,
            'km_anterior'   => $this->km_anterior,
            'km_atual'      => $this->km_atual,
            'km_percorrido' => $this->km_atual - $this->km_anterior,
            'observacao'    => $this->observacao,
            'registrado_at' => $this->registrado_at,
        ];
    }
}
