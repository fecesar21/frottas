<?php

/*
 * Horários de início dos plantões de 12h. O checklist do veículo vale por
 * plantão: diurno (inicio_diurno → inicio_noturno) e noturno (inicio_noturno
 * → inicio_diurno do dia seguinte, referenciado à data em que começou).
 */
return [
    'inicio_diurno' => env('PLANTAO_INICIO_DIURNO', '07:00'),
    'inicio_noturno' => env('PLANTAO_INICIO_NOTURNO', '19:00'),
];
