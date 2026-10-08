<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotivoViagemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nome' => $this->nome,
            'tipo_veiculo' => $this->tipo_veiculo,
            'disponivel_solicitacao' => $this->disponivel_solicitacao,
            'ativo' => $this->ativo,
            'sistema' => $this->sistema,
            'em_uso' => $this->when(array_key_exists('em_uso', $this->resource->getAttributes()), fn () => (bool) $this->em_uso),
        ];
    }
}
