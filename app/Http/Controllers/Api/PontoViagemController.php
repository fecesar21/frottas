<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidade;
use App\Models\Unidade;
use Illuminate\Http\JsonResponse;

class PontoViagemController extends Controller
{
    public function index(): JsonResponse
    {
        $unidades = Unidade::where('ativo', true)->get(['id', 'nome'])
            ->map(fn ($u) => ['tipo' => 'unidade', 'id' => $u->id, 'nome' => $u->nome]);

        $localidades = Localidade::where('ativo', true)->get(['id', 'nome'])
            ->map(fn ($l) => ['tipo' => 'localidade', 'id' => $l->id, 'nome' => $l->nome]);

        $pontos = $unidades->concat($localidades)->sortBy('nome')->values();

        return response()->json($pontos);
    }
}
