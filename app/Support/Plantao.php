<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Plantao
{
    /**
     * Plantão vigente no instante informado.
     *
     * @return array{data: string, turno: 'diurno'|'noturno'}
     */
    public static function atual(?CarbonInterface $agora = null): array
    {
        $agora = $agora ?? now();
        $hora = $agora->format('H:i');
        $diurno = config('plantao.inicio_diurno', '07:00');
        $noturno = config('plantao.inicio_noturno', '19:00');

        if ($hora < $diurno) {
            return ['data' => $agora->copy()->subDay()->toDateString(), 'turno' => 'noturno'];
        }

        if ($hora < $noturno) {
            return ['data' => $agora->toDateString(), 'turno' => 'diurno'];
        }

        return ['data' => $agora->toDateString(), 'turno' => 'noturno'];
    }
}
