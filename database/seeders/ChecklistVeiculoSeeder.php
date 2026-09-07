<?php

namespace Database\Seeders;

use App\Models\ChecklistVeiculoCategoria;
use App\Models\ChecklistVeiculoItemModelo;
use Illuminate\Database\Seeder;

class ChecklistVeiculoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            [
                'nome' => 'Documentação',
                'ordem' => 1,
                'itens' => [
                    ['label' => 'CRLV em dia', 'obrigatorio' => true],
                ],
            ],
            [
                'nome' => 'Exterior',
                'ordem' => 2,
                'itens' => [
                    ['label' => 'Lataria sem avarias', 'obrigatorio' => true],
                    ['label' => 'Vidros íntegros', 'obrigatorio' => true],
                    ['label' => 'Pneus calibrados', 'obrigatorio' => true],
                    ['label' => 'Estepe disponível', 'obrigatorio' => true],
                    ['label' => 'Faróis funcionando', 'obrigatorio' => true],
                    ['label' => 'Lanternas funcionando', 'obrigatorio' => true],
                ],
            ],
            [
                'nome' => 'Mecânica',
                'ordem' => 3,
                'itens' => [
                    ['label' => 'Nível de óleo do motor', 'obrigatorio' => true],
                    ['label' => 'Nível da água do radiador', 'obrigatorio' => true],
                    ['label' => 'Freios funcionando', 'obrigatorio' => true],
                    ['label' => 'Cinto de segurança', 'obrigatorio' => true],
                ],
            ],
            [
                'nome' => 'Interior e Segurança',
                'ordem' => 4,
                'itens' => [
                    ['label' => 'Limpeza interna', 'obrigatorio' => true],
                    ['label' => 'Nível de Oxigênio', 'obrigatorio' => true, 'requer_valor' => true, 'valor_min' => 0, 'valor_max' => 300],
                ],
            ],
        ];

        foreach ($categorias as $cat) {
            $categoria = ChecklistVeiculoCategoria::firstOrCreate(
                ['nome' => $cat['nome']],
                ['ordem' => $cat['ordem'], 'ativo' => true]
            );

            foreach ($cat['itens'] as $i => $item) {
                ChecklistVeiculoItemModelo::firstOrCreate(
                    ['categoria_id' => $categoria->id, 'label' => $item['label']],
                    [
                        'obrigatorio' => $item['obrigatorio'],
                        'requer_valor' => $item['requer_valor'] ?? false,
                        'valor_min' => $item['valor_min'] ?? null,
                        'valor_max' => $item['valor_max'] ?? null,
                        'ordem' => $i + 1,
                        'ativo' => true,
                    ]
                );
            }
        }
    }
}
