<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\ChecklistVeiculo;
use App\Models\ChecklistVeiculoItemModelo;
use App\Models\ChecklistVeiculoResposta;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistVeiculoService
{
    public function necessitaChecklist(string $veiculoId): bool
    {
        $checklist = ChecklistVeiculo::where('veiculo_id', $veiculoId)
            ->whereDate('data_referencia', now()->toDateString())
            ->first();

        return ! $checklist || $checklist->status !== 'enviado';
    }

    public function bloqueiaOperacao(string $veiculoId): bool
    {
        return $this->necessitaChecklist($veiculoId);
    }

    public function iniciarOuObter(Checkin $checkin): ChecklistVeiculo
    {
        $hoje = now()->toDateString();

        $existente = ChecklistVeiculo::where('veiculo_id', $checkin->veiculo_id)
            ->whereDate('data_referencia', $hoje)
            ->first();

        if ($existente) {
            return $existente->load(['veiculo', 'respostas.itemModelo.categoria']);
        }

        try {
            return DB::transaction(function () use ($checkin, $hoje) {
                $checklist = ChecklistVeiculo::create([
                    'veiculo_id' => $checkin->veiculo_id,
                    'motorista_id' => $checkin->motorista_id,
                    'checkin_id' => $checkin->id,
                    'data_referencia' => $hoje,
                    'status' => 'pendente',
                ]);

                $itens = ChecklistVeiculoItemModelo::where('ativo', true)->get();

                foreach ($itens as $item) {
                    ChecklistVeiculoResposta::create([
                        'checklist_veiculo_id' => $checklist->id,
                        'item_modelo_id' => $item->id,
                        'conforme' => null,
                    ]);
                }

                return $checklist->load(['veiculo', 'respostas.itemModelo.categoria']);
            });
        } catch (QueryException) {
            return ChecklistVeiculo::where('veiculo_id', $checkin->veiculo_id)
                ->whereDate('data_referencia', $hoje)
                ->firstOrFail()
                ->load(['veiculo', 'respostas.itemModelo.categoria']);
        }
    }

    public function atualizarItem(ChecklistVeiculo $checklist, array $data): ChecklistVeiculoResposta
    {
        if ($checklist->status === 'enviado') {
            throw ValidationException::withMessages(['checklist' => 'Checklist já enviado, não pode ser alterado.']);
        }

        if ($data['conforme'] === false && empty($data['observacao'])) {
            throw ValidationException::withMessages(['observacao' => 'Observação obrigatória quando o item não está conforme.']);
        }

        $resposta = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)
            ->where('item_modelo_id', $data['item_modelo_id'])
            ->firstOrFail();

        $item = $resposta->itemModelo ?? ChecklistVeiculoItemModelo::find($data['item_modelo_id']);

        if ($data['conforme'] === null) {
            $resposta->update([
                'conforme' => null,
                'observacao' => null,
                'valor' => null,
                'foto_path' => null,
            ]);

            $conformeCount = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)->where('conforme', true)->count();
            $naoConformeCount = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)->where('conforme', false)->count();

            $checklist->update(['itens_conforme' => $conformeCount, 'itens_nao_conforme' => $naoConformeCount]);

            return $resposta;
        }

        if ($item && $item->requer_valor) {
            $valor = $data['valor'] ?? null;

            if ($valor === null || $valor === '') {
                throw ValidationException::withMessages(['valor' => 'Informe o valor para este item.']);
            }

            $min = $item->valor_min ?? 0;
            $max = $item->valor_max ?? 300;

            if (! is_numeric($valor) || (int) $valor != $valor || $valor < $min || $valor > $max) {
                throw ValidationException::withMessages(['valor' => "O valor deve ser um número inteiro entre {$min} e {$max}."]);
            }
        }

        $resposta->update([
            'conforme' => $data['conforme'],
            'observacao' => $data['observacao'] ?? null,
            'valor' => $item && $item->requer_valor ? (int) $data['valor'] : null,
            'foto_path' => $data['foto_path'] ?? $resposta->foto_path,
        ]);

        $conformeCount = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)->where('conforme', true)->count();
        $naoConformeCount = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)->where('conforme', false)->count();

        $checklist->update(['itens_conforme' => $conformeCount, 'itens_nao_conforme' => $naoConformeCount]);

        return $resposta;
    }

    public function enviar(ChecklistVeiculo $checklist, array $data): ChecklistVeiculo
    {
        if ($checklist->status === 'enviado') {
            throw ValidationException::withMessages(['checklist' => 'Checklist já enviado.']);
        }

        $pendentes = ChecklistVeiculoResposta::where('checklist_veiculo_id', $checklist->id)
            ->whereNull('conforme')
            ->whereHas('itemModelo', fn ($q) => $q->where('obrigatorio', true))
            ->with('itemModelo')
            ->get();

        if ($pendentes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'checklist' => 'Itens obrigatórios pendentes: '.$pendentes->pluck('itemModelo.label')->implode(', '),
            ]);
        }

        $checklist->update([
            'status' => 'enviado',
            'enviado_at' => now(),
            'observacoes_gerais' => $data['observacoes_gerais'] ?? $checklist->observacoes_gerais,
        ]);

        return $checklist->fresh()->load(['veiculo', 'respostas.itemModelo.categoria']);
    }
}
