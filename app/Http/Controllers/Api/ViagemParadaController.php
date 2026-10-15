<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\ViagemParada;
use Illuminate\Http\Request;

class ViagemParadaController extends Controller
{
    public function store(Request $r, Viagem $viagem)
    {
        $this->autorizar($viagem);

        if ($viagem->status !== 'em_andamento') {
            return response()->json(['message' => 'Paradas só podem ser registradas em viagem em andamento.'], 422);
        }

        if ($viagem->paradas()->whereNull('saida_at')->exists()) {
            return response()->json(['message' => 'Já existe uma parada aberta. Retome a viagem antes de registrar outra.'], 422);
        }

        $data = $r->validate([
            'local' => 'required|string|max:150',
            'motivo' => 'nullable|string|max:250',
        ]);

        $parada = $viagem->paradas()->create([
            ...$data,
            'ordem' => (int) $viagem->paradas()->max('ordem') + 1,
            'chegada_at' => now(),
        ]);

        return response()->json($parada, 201);
    }

    public function retomar(Viagem $viagem, ViagemParada $parada)
    {
        $this->autorizar($viagem);
        abort_unless($parada->viagem_id === $viagem->id, 404);

        if ($parada->saida_at !== null) {
            return response()->json(['message' => 'Esta parada já foi encerrada.'], 422);
        }

        $parada->update(['saida_at' => now()]);

        return response()->json($parada);
    }

    /** Motorista só mexe nas próprias viagens; admin/gestor em qualquer uma. */
    private function autorizar(Viagem $viagem): void
    {
        $usuario = auth()->user();
        if ($usuario->perfil === 'operador' && $viagem->motorista_id !== $usuario->motorista_id) {
            abort(403);
        }
    }
}
