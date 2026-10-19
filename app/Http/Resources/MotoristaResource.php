<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MotoristaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'telefone' => $this->telefone,
            'email' => $this->email,
            'cnh_numero' => $this->cnh_numero,
            'cnh_categoria' => $this->cnh_categoria,
            'cnh_validade' => $this->cnh_validade,
            'dias_para_vencer_cnh' => $this->diasParaVencerCnh,
            'turno_padrao' => $this->turno_padrao,
            'status' => $this->status,
            'observacoes' => $this->observacoes,
            'permite_checkin_duplo' => (bool) $this->permite_checkin_duplo,
            'unidades' => $this->whenLoaded('unidades', fn () => $this->unidades->map(fn ($u) => ['id' => $u->id, 'nome' => $u->nome, 'tipo' => $u->tipo])),
            'checkin_ativo' => $this->whenLoaded('checkinAtivo', fn () => new CheckinResource($this->checkinAtivo)),
            // null = motorista sem usuário de acesso (não tem como receber push)
            'push_ativo' => $this->whenLoaded('usuario', fn () => $this->usuario
                ? ($this->usuario->push_subscriptions_count ?? $this->usuario->pushSubscriptions()->count()) > 0
                : null),
            'atualizado_em' => $this->updated_at,
        ];
    }
}
