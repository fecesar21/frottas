<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidade;
use Illuminate\Http\JsonResponse;

class PontoViagemController extends Controller
{
    public function index(): JsonResponse
    {
        // Origem/Destino vêm apenas de Configurações > Localidades; Unidades
        // ficaram de fora para não exibir nomes duplicados. Solicitações antigas
        // com tipo "unidade" continuam válidas (ver App\Rules\PontoViagemExiste).
        $pontos = Localidade::where('ativo', true)->orderBy('nome')->get(['id', 'nome'])
            ->map(fn ($l) => ['tipo' => 'localidade', 'id' => $l->id, 'nome' => $l->nome])
            ->values();

        return response()->json($pontos);
    }
}
