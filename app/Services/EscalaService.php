<?php

namespace App\Services;

use App\Models\Escala;

class EscalaService
{
    public function gerarSemana(string $dataInicio, array $motoristasdia, array $motoristasNoite): int
    {
        $inseridos = 0;

        for ($i = 0; $i < 7; $i++) {
            $data = date('Y-m-d', strtotime("{$dataInicio} +{$i} days"));

            foreach ($motoristasdia as $motoristaId) {
                $escala = Escala::withTrashed()->firstOrNew(
                    ['motorista_id' => $motoristaId, 'data' => $data]
                );
                if ($escala->trashed()) {
                    $escala->restore();
                }
                $escala->fill(['turno' => 'dia'])->save();
                $inseridos++;
            }

            foreach ($motoristasNoite as $motoristaId) {
                $escala = Escala::withTrashed()->firstOrNew(
                    ['motorista_id' => $motoristaId, 'data' => $data]
                );
                if ($escala->trashed()) {
                    $escala->restore();
                }
                $escala->fill(['turno' => 'noite'])->save();
                $inseridos++;
            }
        }

        return $inseridos;
    }
}
