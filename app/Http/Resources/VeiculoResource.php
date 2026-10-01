<?php

namespace App\Http\Resources;

use App\Models\VeiculoManutencao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VeiculoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'placa' => $this->placa,
            'modelo' => $this->modelo,
            'marca' => $this->marca,
            'ano' => $this->ano,
            'cor' => $this->cor,
            'combustivel' => $this->combustivel,
            'capacidade_tanque' => $this->capacidade_tanque,
            'km_atual' => $this->km_atual,
            'km_proxima_revisao' => $this->km_proxima_revisao,
            'status' => $this->status,
            'manutencao_inicio' => $this->manutencao_inicio,
            'observacoes' => $this->observacoes,
            'manutencao_atual' => $this->when($this->status === 'manutencao', function () {
                $m = $this->manutencaoAberta;

                return $m ? [
                    'id' => $m->id,
                    'tipo' => $m->tipo,
                    'tipo_label' => VeiculoManutencao::rotuloTipo($m->tipo),
                    'motivo' => $m->motivo,
                    'inicio' => $m->inicio,
                    'origem' => $m->origem,
                    'local' => $m->local,
                    'km_entrada' => $m->km_entrada,
                    'aberta_por_id' => $m->aberta_por_id,
                    'aberta_por' => $m->abertaPor?->nome,
                ] : null;
            }),
            'unidades' => $this->whenLoaded('unidades', fn () => $this->unidades->map(fn ($u) => ['id' => $u->id, 'nome' => $u->nome, 'tipo' => $u->tipo])),
            'checkin_ativo' => $this->whenLoaded('checkinAtivo', fn () => new CheckinResource($this->checkinAtivo)),
            'km_registros' => $this->whenLoaded('kmRegistros', fn () => KmRegistroResource::collection($this->kmRegistros)),
            'atualizado_em' => $this->updated_at,
        ];
    }
}
