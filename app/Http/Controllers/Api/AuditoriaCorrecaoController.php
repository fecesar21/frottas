<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditoriaCorrecao;
use Illuminate\Http\Request;

class AuditoriaCorrecaoController extends Controller
{
    public function index(Request $r)
    {
        $r->validate([
            'entidade' => 'nullable|in:viagem,abastecimento',
            'entidade_id' => 'nullable|uuid',
        ]);

        $registros = AuditoriaCorrecao::with('usuario:id,nome')
            ->when($r->entidade, fn ($q, $e) => $q->where('entidade', $e))
            ->when($r->entidade_id, fn ($q, $id) => $q->where('entidade_id', $id))
            ->latest('created_at')
            ->latest('id')
            ->limit(200)
            ->get();

        return response()->json(['data' => $registros]);
    }
}
