<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Motorista;
use App\Models\Unidade;
use App\Models\Veiculo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnidadeController extends Controller
{
    public function index(): JsonResponse
    {
        $unidades = Unidade::orderBy('tipo')->orderBy('nome')->get();

        return response()->json($unidades);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'required|string|max:120',
            'tipo' => 'required|in:matriz,filial',
            'ativo' => 'boolean',
        ]);

        $unidade = Unidade::create($data);

        return response()->json($unidade, 201);
    }

    public function show(Unidade $unidade): JsonResponse
    {
        $unidade->load(['motoristas:id,nome,cpf,status,turno_padrao', 'veiculos:id,placa,modelo,marca,status']);

        return response()->json($unidade);
    }

    public function update(Request $request, Unidade $unidade): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'sometimes|string|max:120',
            'tipo' => 'sometimes|in:matriz,filial',
            'ativo' => 'sometimes|boolean',
        ]);

        $unidade->update($data);

        return response()->json($unidade);
    }

    public function destroy(Request $request, Unidade $unidade): JsonResponse
    {
        $this->autorizar($request);

        $unidade->update(['ativo' => false]);

        return response()->json(['message' => 'Unidade desativada.']);
    }

    public function vincularMotoristas(Request $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validate([
            'motorista_ids' => 'required|array|min:1',
            'motorista_ids.*' => 'uuid|exists:motoristas,id',
        ]);

        $unidade->motoristas()->syncWithoutDetaching($data['motorista_ids']);

        return response()->json(['message' => 'Motoristas vinculados.']);
    }

    public function desvincularMotorista(Unidade $unidade, Motorista $motorista): JsonResponse
    {
        $unidade->motoristas()->detach($motorista->id);

        return response()->json(['message' => 'Motorista desvinculado.']);
    }

    public function vincularVeiculos(Request $request, Unidade $unidade): JsonResponse
    {
        $data = $request->validate([
            'veiculo_ids' => 'required|array|min:1',
            'veiculo_ids.*' => 'uuid|exists:veiculos,id',
        ]);

        $unidade->veiculos()->syncWithoutDetaching($data['veiculo_ids']);

        return response()->json(['message' => 'Veículos vinculados.']);
    }

    public function desvincularVeiculo(Unidade $unidade, Veiculo $veiculo): JsonResponse
    {
        $unidade->veiculos()->detach($veiculo->id);

        return response()->json(['message' => 'Veículo desvinculado.']);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar unidades.');
    }
}
