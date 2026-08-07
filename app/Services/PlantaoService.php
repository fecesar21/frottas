<?php

namespace App\Services;

use App\Models\ChecklistItemModelo;
use App\Models\ChecklistResposta;
use App\Models\Motorista;
use App\Models\PassagemPlantao;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;

class PlantaoService
{
    public function store(array $data): PassagemPlantao
    {
        $this->validarMesmaUnidade(
            $data['veiculo_id'],
            $data['motorista_saindo_id'],
            $data['motorista_entrando_id']
        );

        return DB::transaction(function () use ($data) {
            $passagem = PassagemPlantao::create($data);

            $itens = ChecklistItemModelo::where('ativo', true)->get();

            foreach ($itens as $item) {
                ChecklistResposta::create([
                    'passagem_id' => $passagem->id,
                    'item_modelo_id' => $item->id,
                    'resultado' => 'nao_verificado',
                ]);
            }

            $passagem->update(['total_itens' => $itens->count()]);

            return $passagem->fresh();
        });
    }

    private function validarMesmaUnidade(string $veiculoId, string $motoristaSaindoId, string $motoristaEntrandoId): void
    {
        $veiculo = Veiculo::with('unidades')->findOrFail($veiculoId);
        $motoristaSaindo = Motorista::with('unidades')->findOrFail($motoristaSaindoId);
        $motoristaEntrando = Motorista::with('unidades')->findOrFail($motoristaEntrandoId);

        $unidadesVeiculo = $veiculo->unidades->pluck('id');
        $unidadesSaindo = $motoristaSaindo->unidades->pluck('id');
        $unidadesEntrando = $motoristaEntrando->unidades->pluck('id');

        $comum = $unidadesVeiculo->intersect($unidadesSaindo)->intersect($unidadesEntrando);

        abort_if(
            $comum->isEmpty(),
            422,
            'Veículo e motoristas não pertencem à mesma unidade. Verifique os vínculos antes de registrar a passagem de plantão.'
        );
    }
}
