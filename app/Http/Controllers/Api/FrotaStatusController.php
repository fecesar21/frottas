<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Http\Request;

/**
 * Painel "Frota agora" do app de solicitação: veículos e motoristas em
 * plantão (ou em manutenção) com o status atual. Somente leitura, sem
 * dados sensíveis (CPF, KM).
 */
class FrotaStatusController extends Controller
{
    public function status(Request $r)
    {
        $unidadeId = $r->unidade_efetiva;
        $daUnidade = fn ($q) => $q->whereHas('unidades', fn ($u) => $u->where('unidades.id', $unidadeId));

        $viagens = Viagem::where('status', 'em_andamento')->get(['veiculo_id', 'motorista_id', 'saida_at']);
        $viagemPorVeiculo = $viagens->keyBy('veiculo_id');
        $viagemPorMotorista = $viagens->keyBy('motorista_id');

        $veiculos = Veiculo::with(['checkinAtivo.motorista:id,nome', 'manutencaoAberta'])
            ->where(fn ($q) => $q->where('status', 'manutencao')->orWhereHas('checkinAtivo'))
            ->when($unidadeId, $daUnidade)
            ->orderBy('placa')
            ->get()
            ->map(function (Veiculo $v) use ($viagemPorVeiculo) {
                $viagem = $viagemPorVeiculo->get($v->id);
                [$status, $desde] = match (true) {
                    $v->emManutencao() => ['manutencao', $v->manutencaoAberta?->inicio ?? $v->manutencao_inicio],
                    $viagem !== null => ['em_viagem', $viagem->saida_at],
                    default => ['disponivel', $v->checkinAtivo?->checkin_at],
                };

                return [
                    'id' => $v->id,
                    'placa' => $v->placa,
                    'modelo' => $v->modelo,
                    'status' => $status,
                    'motorista_nome' => $v->checkinAtivo?->motorista?->nome,
                    'desde' => $desde?->toIso8601String(),
                ];
            });

        $motoristas = Motorista::with('checkinAtivo.veiculo:id,placa')
            ->where('status', 'ativo')
            ->whereHas('usuario', fn ($q) => $q->where('perfil', 'operador'))
            ->whereHas('checkinAtivo')
            ->when($unidadeId, $daUnidade)
            ->orderBy('nome')
            ->get()
            ->map(fn (Motorista $m) => [
                'id' => $m->id,
                'nome' => $m->nome,
                'status' => $viagemPorMotorista->has($m->id) ? 'em_viagem' : 'disponivel',
                'placa' => $m->checkinAtivo?->veiculo?->placa,
            ]);

        return response()->json([
            'veiculos' => $veiculos->values(),
            'motoristas' => $motoristas->values(),
            'atualizado_em' => now()->toIso8601String(),
        ]);
    }
}
