<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalidadeController extends Controller
{
    public function index(): JsonResponse
    {
        $localidades = Localidade::orderBy('nome')->get();

        return response()->json($localidades);
    }

    public function store(Request $request): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'required|string|max:150',
            'endereco' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ativo' => 'boolean',
        ]);

        $localidade = Localidade::create($data);

        return response()->json($localidade, 201);
    }

    public function show(Localidade $localidade): JsonResponse
    {
        return response()->json($localidade);
    }

    public function update(Request $request, Localidade $localidade): JsonResponse
    {
        $this->autorizar($request);

        $data = $request->validate([
            'nome' => 'sometimes|string|max:150',
            'endereco' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ativo' => 'sometimes|boolean',
        ]);

        $localidade->update($data);

        return response()->json($localidade);
    }

    public function destroy(Request $request, Localidade $localidade): JsonResponse
    {
        $this->autorizar($request);

        $localidade->update(['ativo' => false]);

        return response()->json(['message' => 'Localidade desativada.']);
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->perfil === 'admin', 403, 'Apenas administradores podem gerenciar localidades.');
    }
}
