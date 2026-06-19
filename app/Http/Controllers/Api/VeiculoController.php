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
   // public function index(Request $request)
   // {
   //     $query = Veiculo::query()
   //         ->with(['checkinAtivo.motorista'])
   //         ->when($request->status, fn ($q, $s) => $q->where('status', $s))
   //         ->when(!$request->has('status'), fn($q) => $q->where('status', '!=', 'inativo'))
   //         ->orderBy('placa');
//
//        return response()->json($query->get());
//    }

    public function index(Request $request)
    {
        $veiculos = Veiculo::query()
            ->with(['checkinAtivo.motorista'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('placa')
            ->get();

        return VeiculoResource::collection($veiculos);
    }

    public function show(Veiculo $veiculo)
    {
        $veiculo->load(['checkinAtivo.motorista', 'kmRegistros' => fn ($q) => $q->latest()->limit(10)]);

        return new VeiculoResource($veiculo);
    }

    public function store(StoreVeiculoRequest $request)
    {
        $data = $request->validated();
        $data['combustivel'] ??= 'diesel_s10';
        $data['km_atual']    ??= 0;

        return (new VeiculoResource(Veiculo::create($data)))->response()->setStatusCode(201);
    }

    public function update(UpdateVeiculoRequest $request, Veiculo $veiculo)
    {
        $veiculo->update($request->validated());

        return new VeiculoResource($veiculo->fresh());
    }

    public function destroy(Veiculo $veiculo)
    {
        $veiculo->update(['status' => 'inativo']);

        return response()->json(['message' => 'Veículo desativado']);
    }
}
