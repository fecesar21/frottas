<?php

/*
 * Roteamento das notificações de novas solicitações para motoristas em
 * atividade (check-in ativo). Os textos são comparados sem diferenciar
 * maiúsculas/acentos e por "contém" (nome da unidade / modelo do veículo).
 * Motivos ausentes deste mapa (ex.: tfd) notificam apenas admin/gestor.
 */
return [
    'roteamento_motoristas' => [
        // Por unidade do solicitante: só motoristas vinculados à mesma unidade,
        // com check-in em veículo do modelo indicado e vinculado a essa unidade.
        'transferencia_paciente' => [
            'por_unidade' => [
                'PAI' => ['AMBULANCIA PAI'],
                'UPA' => ['AMBULANCIA UPA'],
            ],
        ],

        // Independente da unidade do solicitante.
        'buscar_medico' => ['modelos' => ['FIORINO', 'COROLLA', 'DUSTER', 'STRADA']],
        'material_outro_hospital' => ['modelos' => ['FIORINO', 'COROLLA', 'DUSTER', 'STRADA']],
        'transporte_colaborador' => ['modelos' => ['FIORINO', 'COROLLA', 'DUSTER', 'STRADA']],
        'buscar_material_fornecedor' => ['modelos' => ['FIORINO', 'COROLLA', 'DUSTER', 'STRADA']],
    ],
];
