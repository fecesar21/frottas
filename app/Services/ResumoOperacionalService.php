<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Resumo operacional de um intervalo (plantão ou mês), com recorte por
 * data e hora: viagens contam pela saída dentro da janela; o tempo de
 * manutenção é a parte de cada manutenção que cai dentro da janela.
 */
class ResumoOperacionalService
{
    public function gerar(CarbonInterface $inicio, CarbonInterface $fim): array
    {
        $viagens = DB::table('viagens as vg')
            ->join('veiculos as v', 'v.id', '=', 'vg.veiculo_id')
            ->join('motoristas as m', 'm.id', '=', 'vg.motorista_id')
            ->whereBetween('vg.saida_at', [$inicio, $fim])
            ->select('vg.veiculo_id', 'v.placa', 'v.modelo', 'vg.motorista_id', 'm.nome',
                'vg.motivo_viagem', 'vg.km_saida', 'vg.km_chegada')
            ->get()
            ->map(function ($vg) {
                $vg->km = $vg->km_chegada !== null ? max(0, $vg->km_chegada - $vg->km_saida) : 0;

                return $vg;
            });

        $kmPorVeiculo = $viagens->groupBy('veiculo_id')->map(fn ($g) => [
            'placa' => $g->first()->placa,
            'modelo' => $g->first()->modelo,
            'viagens' => $g->count(),
            'km' => round($g->sum('km'), 1),
        ])->sortByDesc('km')->values()->all();

        $porMotorista = $viagens->groupBy('motorista_id')->map(fn ($g) => [
            'nome' => $g->first()->nome,
            'viagens' => $g->count(),
            'km' => round($g->sum('km'), 1),
        ]);

        $porMotivo = $viagens
            ->groupBy(fn ($vg) => trim((string) $vg->motivo_viagem) !== '' ? trim($vg->motivo_viagem) : 'Não informado')
            ->map(fn ($g, $motivo) => ['motivo' => $motivo, 'viagens' => $g->count()])
            ->sortByDesc('viagens')->values()->all();

        $agora = now();
        $manutencoes = DB::table('veiculo_manutencoes as vm')
            ->join('veiculos as v', 'v.id', '=', 'vm.veiculo_id')
            ->where('vm.inicio', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('vm.fim')->orWhere('vm.fim', '>=', $inicio))
            ->select('vm.veiculo_id', 'v.placa', 'v.modelo', 'vm.inicio', 'vm.fim')
            ->get()
            ->map(function ($m) use ($inicio, $fim, $agora) {
                $ini = Carbon::parse($m->inicio)->max($inicio);
                $fimRecorte = ($m->fim ? Carbon::parse($m->fim) : $agora->copy())->min($fim)->min($agora);
                $m->minutos = $fimRecorte->gt($ini) ? (int) $ini->diffInMinutes($fimRecorte) : 0;

                return $m;
            });

        $manutencaoPorVeiculo = $manutencoes->groupBy('veiculo_id')->map(fn ($g) => [
            'placa' => $g->first()->placa,
            'modelo' => $g->first()->modelo,
            'manutencoes' => $g->count(),
            'minutos' => $g->sum('minutos'),
        ])->sortByDesc('minutos')->values()->all();

        return [
            'totais' => [
                'viagens' => $viagens->count(),
                'km' => round($viagens->sum('km'), 1),
                'manutencao_minutos' => $manutencoes->sum('minutos'),
            ],
            'km_por_veiculo' => $kmPorVeiculo,
            'viagens_por_motorista' => $porMotorista->sortByDesc('viagens')->values()->all(),
            'km_por_motorista' => $porMotorista->sortByDesc('km')->values()->all(),
            'manutencao_por_veiculo' => $manutencaoPorVeiculo,
            'viagens_por_motivo' => $porMotivo,
        ];
    }
}
