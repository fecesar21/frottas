<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Services\ColaboradorSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColaboradorController extends Controller
{
    /**
     * Busca de colaboradores ativos para o lançamento de viagens de
     * transporte de colaborador. Filtra por nome/login e, opcionalmente, unidade.
     */
    public function index(Request $r): JsonResponse
    {
        $r->validate([
            'busca' => 'nullable|string|max:100',
            'unidade_id' => 'nullable|uuid',
        ]);

        $busca = trim((string) $r->input('busca'));

        $colaboradores = Colaborador::with('unidade:id,nome')
            ->where('ativo', true)
            ->when($r->input('unidade_id'), fn ($q, $u) => $q->where('unidade_id', $u))
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('nome', 'like', '%'.mb_strtoupper($busca).'%')
                ->orWhere('samaccountname', 'like', '%'.$busca.'%')))
            ->orderBy('nome')
            ->limit(50)
            ->get();

        return response()->json(['data' => $colaboradores->map(fn (Colaborador $c) => [
            'id' => $c->id,
            'nome' => $c->nome,
            'departamento' => $c->departamento,
            'cargo' => $c->cargo,
            'unidade_id' => $c->unidade_id,
            'unidade' => $c->unidade?->nome,
        ])]);
    }

    /** Sincronização manual (admin), além da execução agendada de hora em hora. */
    public function sincronizar(ColaboradorSyncService $service): JsonResponse
    {
        return response()->json(['data' => $service->sincronizarTodas()]);
    }
}
