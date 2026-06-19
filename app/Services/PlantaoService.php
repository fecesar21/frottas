<?php

namespace App\Services;

use App\Models\ChecklistItemModelo;
use App\Models\ChecklistResposta;
use App\Models\PassagemPlantao;
use Illuminate\Support\Facades\DB;

class PlantaoService
{
    public function store(array $data): PassagemPlantao
    {
        return DB::transaction(function () use ($data) {
            $passagem = PassagemPlantao::create($data);

            $itens = ChecklistItemModelo::where('ativo', true)->get();

            foreach ($itens as $item) {
                ChecklistResposta::create([
                    'passagem_id'    => $passagem->id,
                    'item_modelo_id' => $item->id,
                    'resultado'      => 'nao_verificado',
                ]);
            }

            $passagem->update(['total_itens' => $itens->count()]);

            return $passagem->fresh();
        });
    }
}
