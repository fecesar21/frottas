<?php

namespace App\Console\Commands;

use App\Models\Viagem;
use Illuminate\Console\Command;

/**
 * Lista viagens concluídas com distância implausível (provável KM digitado
 * errado) para correção pela gestão. Com --marcar, sinaliza como km_suspeito.
 */
class AuditarKmViagens extends Command
{
    protected $signature = 'frota:auditar-km {--marcar : Marca as viagens encontradas como km_suspeito}';

    protected $description = 'Lista viagens com KM implausível (dígito a mais ou trocado)';

    public function handle(): int
    {
        $limite = (int) config('frota.km.distancia_viagem_alerta');

        $viagens = Viagem::with('veiculo:id,placa')
            ->whereNotNull('km_chegada')
            ->whereRaw('km_chegada - km_saida > ?', [$limite])
            ->orderBy('saida_at')
            ->get();

        if ($viagens->isEmpty()) {
            $this->info('Nenhuma viagem com KM implausível.');

            return self::SUCCESS;
        }

        $this->table(['Viagem', 'Placa', 'Saída', 'KM saída', 'KM chegada', 'Distância'], $viagens->map(fn (Viagem $v) => [
            $v->id, $v->veiculo?->placa, $v->saida_at?->format('d/m/Y H:i'), $v->km_saida, $v->km_chegada, $v->km_chegada - $v->km_saida,
        ]));

        if ($this->option('marcar')) {
            Viagem::whereIn('id', $viagens->pluck('id'))->where('km_suspeito', false)->update([
                'km_suspeito' => true,
                'justificativa_km' => 'Auditoria: distância acima de '.$limite.' km',
            ]);
            $this->info($viagens->count().' viagem(ns) marcada(s) como KM suspeito.');
        }

        return self::SUCCESS;
    }
}
