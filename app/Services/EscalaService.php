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
                Escala::updateOrCreate(
                    ['motorista_id' => $motoristaId, 'data' => $data],
                    ['turno' => 'dia']
                );
                $inseridos++;
            }

            foreach ($motoristasNoite as $motoristaId) {
                Escala::updateOrCreate(
                    ['motorista_id' => $motoristaId, 'data' => $data],
                    ['turno' => 'noite']
                );
                $inseridos++;
            }
        }

        return $inseridos;
    }
}
