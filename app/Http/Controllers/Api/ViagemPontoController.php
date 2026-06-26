<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Viagem;
use App\Models\ViagemPonto;
use Illuminate\Http\Request;

class ViagemPontoController extends Controller
{
    public function store(Request $r, Viagem $viagem)
    {
        if ($viagem->status !== 'em_andamento') {
            return response()->json(['message' => 'Viagem não está em andamento'], 422);
        }

        $data = $r->validate([
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
            'accuracy'     => 'nullable|numeric|min:0',
            'capturado_at' => 'nullable|date',
        ]);

        $data['viagem_id']    = $viagem->id;
        $data['capturado_at'] = $data['capturado_at'] ?? now();

        $ponto = ViagemPonto::create($data);

        return response()->json($ponto, 201);
    }

    public function index(Viagem $viagem)
    {
        return response()->json(
            $viagem->pontos()->select('latitude', 'longitude', 'accuracy', 'capturado_at')->get()
        );
    }
}
