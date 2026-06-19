<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotoristaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'nome'                 => $this->nome,
            'cpf'                  => $this->cpf,
            'telefone'             => $this->telefone,
            'email'                => $this->email,
            'cnh_numero'           => $this->cnh_numero,
            'cnh_categoria'        => $this->cnh_categoria,
            'cnh_validade'         => $this->cnh_validade,
            'dias_para_vencer_cnh' => $this->diasParaVencerCnh,
            'turno_padrao'         => $this->turno_padrao,
            'status'               => $this->status,
            'observacoes'          => $this->observacoes,
            'checkin_ativo'        => $this->whenLoaded('checkinAtivo', fn () => new CheckinResource($this->checkinAtivo)),
            'atualizado_em'        => $this->updated_at,
        ];
    }
}
