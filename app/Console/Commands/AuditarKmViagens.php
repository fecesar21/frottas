<?php

namespace App\Console\Commands;

use App\Models\Viagem;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Lista viagens com KM implausível (provável dígito a mais ou trocado) para
 * correção pela gestão. Com --marcar, sinaliza como km_suspeito.
 *
 * - Distância: chegada - saída acima do limite da viagem.
 * - Salto: KM de saída muito acima do fim da viagem anterior do veículo
 *   (pega ida e chegada infladas juntas, que têm distância "normal").
 * - Regressão: KM de saída abaixo do fim da viagem anterior — a anterior
 *   provavelmente está inflada.
 */
class AuditarKmViagens extends Command
{
    protected $signature = 'frota:auditar-km {--marcar : Marca as viagens encontradas como km_suspeito}';

    protected $description = 'Lista viagens com KM implausível (dígito a mais ou trocado)';

    public function handle(): int
    {
        $achados = $this->auditar();

        if ($achados->isEmpty()) {
            $this->info('Nenhuma viagem com KM implausível.');

            return self::SUCCESS;
        }

        $this->table(['Viagem', 'Placa', 'Saída', 'KM saída', 'KM chegada', 'Problema'], $achados->map(fn (array $a) => [
            $a['viagem']->id, $a['viagem']->veiculo?->placa, $a['viagem']->saida_at?->format('d/m/Y H:i'),
            $a['viagem']->km_saida, $a['viagem']->km_chegada, $a['motivo'],
        ]));

        if ($this->option('marcar')) {
            $achados->each(function (array $a) {
                if (! $a['viagem']->km_suspeito) {
                    $a['viagem']->update(['km_suspeito' => true, 'justificativa_km' => 'Auditoria: '.$a['motivo']]);
                }
            });
            $this->info($achados->count().' viagem(ns) marcada(s) como KM suspeito.');
        }

        return self::SUCCESS;
    }

    /** @return Collection<int, array{viagem: Viagem, motivo: string}> */
    private function auditar(): Collection
    {
        $limiteDistancia = (int) config('frota.km.distancia_viagem_alerta');
        $limiteSalto = (int) config('frota.km.salto_saida_alerta');
        $achados = collect();

        Viagem::with('veiculo:id,placa')
            ->where('status', '!=', 'cancelada')
            ->orderBy('veiculo_id')->orderBy('saida_at')
            ->get()
            ->groupBy('veiculo_id')
            ->each(function (Collection $viagens) use ($limiteDistancia, $limiteSalto, $achados) {
                $fimAnterior = null;
                foreach ($viagens as $v) {
                    $motivos = [];
                    if ($v->km_chegada !== null && $v->km_chegada - $v->km_saida > $limiteDistancia) {
                        $motivos[] = 'distância de '.($v->km_chegada - $v->km_saida).' km';
                    }
                    if ($fimAnterior !== null && $v->km_saida - $fimAnterior > $limiteSalto) {
                        $motivos[] = 'saída '.($v->km_saida - $fimAnterior).' km acima da viagem anterior ('.$fimAnterior.')';
                    }
                    if ($fimAnterior !== null && $fimAnterior - $v->km_saida > $limiteSalto) {
                        $motivos[] = 'saída abaixo da viagem anterior ('.$fimAnterior.') — a anterior pode estar inflada';
                    }
                    if ($motivos) {
                        $achados->push(['viagem' => $v, 'motivo' => implode('; ', $motivos)]);
                    }
                    $fimAnterior = $v->km_chegada ?? $v->km_saida;
                }
            });

        return $achados;
    }
}
