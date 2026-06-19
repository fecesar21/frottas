<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nome'         => $this->nome,
            'email'        => $this->email,
            'perfil'       => $this->perfil,
            'ativo'        => $this->ativo,
            'motorista_id' => $this->motorista_id,
            'ultimo_acesso'=> $this->ultimo_acesso,
            'criado_em'    => $this->created_at,
        ];
    }
}
