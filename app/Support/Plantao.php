<?php

namespace App\Support;

use App\Models\Checkin;
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

    /**
     * Plantão ao qual o check-in pertence, pelo turno do motorista — não pelo
     * relógio. Assim, check-in antecipado (06:50) ou checkout tardio (19:20)
     * continuam no mesmo plantão.
     *
     * @return array{data: string, turno: 'diurno'|'noturno'}
     */
    public static function doCheckin(Checkin $checkin): array
    {
        $inicio = $checkin->checkin_at ?? now();

        if ($checkin->turno === 'dia') {
            return ['data' => $inicio->toDateString(), 'turno' => 'diurno'];
        }

        if ($checkin->turno === 'noite') {
            $data = $inicio->hour < 12 ? $inicio->copy()->subDay() : $inicio;

            return ['data' => $data->toDateString(), 'turno' => 'noturno'];
        }

        return self::atual($inicio);
    }
}
