<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Veiculo\StoreVeiculoRequest;
use App\Http\Requests\Veiculo\UpdateVeiculoRequest;
use App\Http\Resources\VeiculoResource;
use App\Models\Veiculo;
use Illuminate\Http\Request;

class VeiculoController extends Controller
{
    public function index(Request $request)
    {
        $unidadeId = $request->unidade_efetiva;

        $veiculos = Veiculo::query()
            ->with(['checkinAtivo.motorista'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($unidadeId, fn ($q) => $q->whereHas('unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->orderBy('placa')
            ->get();

        return VeiculoResource::collection($veiculos);
    }

    public function show(Veiculo $veiculo)
    {
        $veiculo->load([
            'checkinAtivo.motorista',
            'kmRegistros' => fn ($q) => $q->latest()->limit(10),
            'unidades',
        ]);

        return new VeiculoResource($veiculo);
    }

    public function store(StoreVeiculoRequest $request)
    {
        $data = $request->validated();
        $data['combustivel'] ??= 'diesel_s10';
        $data['km_atual'] ??= 0;

        return (new VeiculoResource(Veiculo::create($data)))->response()->setStatusCode(201);
    }

    public function update(UpdateVeiculoRequest $request, Veiculo $veiculo)
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            if ($data['status'] === 'manutencao' && $veiculo->status !== 'manutencao') {
                $data['manutencao_inicio'] = now();
            } elseif ($data['status'] !== 'manutencao') {
                $data['manutencao_inicio'] = null;
            }
        }

        $veiculo->update($data);

        return new VeiculoResource($veiculo->fresh());
    }

    public function destroy(Veiculo $veiculo)
    {
        $veiculo->update(['status' => 'inativo']);

        return response()->json(['message' => 'Veículo desativado']);
    }
}
