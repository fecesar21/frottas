<?php

/*
 * Plausibilidade do KM informado pelo motorista (evita dígito a mais ou
 * dígito trocado no odômetro). Acima do "alerta" o motorista precisa
 * confirmar e justificar; acima do "bloqueio" o valor é rejeitado.
 */
return [
    'km' => [
        // Salto entre o último KM conhecido do veículo e o KM de saída.
        'salto_saida_alerta' => (int) env('FROTA_KM_SALTO_SAIDA_ALERTA', 300),
        'salto_saida_bloqueio' => (int) env('FROTA_KM_SALTO_SAIDA_BLOQUEIO', 2000),

        // Distância de uma viagem (chegada - saída).
        'distancia_viagem_alerta' => (int) env('FROTA_KM_DISTANCIA_ALERTA', 800),
        'distancia_viagem_bloqueio' => (int) env('FROTA_KM_DISTANCIA_BLOQUEIO', 2000),

        // Alerta também se a distância exceder o possível no tempo decorrido.
        'velocidade_max_kmh' => (int) env('FROTA_KM_VELOCIDADE_MAX', 110),
        'folga_km' => (int) env('FROTA_KM_FOLGA', 20),
    ],
];
