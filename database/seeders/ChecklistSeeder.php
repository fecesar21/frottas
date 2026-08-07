<?php

namespace Database\Seeders;

use App\Models\ChecklistCategoria;
use App\Models\ChecklistItemModelo;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            [
                'nome'  => 'Documentação',
                'ordem' => 1,
                'itens' => [
                    ['label' => 'CRLV em dia',         'obrigatorio' => true],
                ],
            ],
            [
                'nome'  => 'Exterior',
                'ordem' => 2,
                'itens' => [
                    ['label' => 'Lataria sem avarias',  'obrigatorio' => true],
                    ['label' => 'Vidros íntegros',      'obrigatorio' => true],
                    ['label' => 'Pneus calibrados',     'obrigatorio' => true],
                    ['label' => 'Estepe disponível',    'obrigatorio' => true],
                    ['label' => 'Faróis funcionando',   'obrigatorio' => true],
                    ['label' => 'Lanternas funcionando','obrigatorio' => true],
                ],
            ],
            [
                'nome'  => 'Mecânica',
                'ordem' => 3,
                'itens' => [
                    ['label' => 'Nível de óleo do motor',    'obrigatorio' => true],
                    ['label' => 'Nível da água do radiador', 'obrigatorio' => true],
                    ['label' => 'Freios funcionando',        'obrigatorio' => true],
                    ['label' => 'Cinto de segurança',        'obrigatorio' => true],
                ],
            ],
            [
                'nome'  => 'Interior',
                'ordem' => 4,
                'itens' => [
                    ['label' => 'Limpeza interna',           'obrigatorio' => true],
                        ['label' => 'Triângulo de segurança',    'obrigatorio' => true],
                ],
            ],
        ];

        foreach ($categorias as $ordem => $cat) {
            $categoria = ChecklistCategoria::firstOrCreate(
                ['nome' => $cat['nome']],
                ['ordem' => $cat['ordem'], 'ativo' => true]
            );

            foreach ($cat['itens'] as $i => $item) {
                ChecklistItemModelo::firstOrCreate(
                    ['categoria_id' => $categoria->id, 'label' => $item['label']],
                    ['obrigatorio' => $item['obrigatorio'], 'ordem' => $i + 1, 'ativo' => true]
                );
            }
        }
    }
}
