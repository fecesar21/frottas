<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escala\GerarSemanaRequest;
use App\Http\Requests\Escala\StoreEscalaRequest;
use App\Http\Resources\EscalaResource;
use App\Models\Escala;
use App\Services\EscalaService;
use Illuminate\Http\Request;

class EscalaController extends Controller
{
    public function __construct(private EscalaService $service) {}

    public function index(Request $r)
    {
        $de  = $r->get('de',  now()->toDateString());
        $ate = $r->get('ate', now()->toDateString());

        $escalas = Escala::with(['motorista', 'veiculo'])
            ->whereBetween('data', [$de, $ate])
            ->orderBy('data')
            ->orderBy('turno')
            ->get();

        return EscalaResource::collection($escalas);
    }

    public function store(StoreEscalaRequest $request)
    {
        $d = $request->validated();
        $escala = Escala::updateOrCreate(
            ['motorista_id' => $d['motorista_id'], 'data' => $d['data']],
            $d
        );

        return (new EscalaResource($escala))->response()->setStatusCode(201);
    }

    public function destroy(Escala $escala)
    {
        $escala->delete();

        return response()->json(['message' => 'Escala removida']);
    }

    public function gerarSemana(GerarSemanaRequest $request)
    {
        $d = $request->validated();
        $inseridos = $this->service->gerarSemana(
            $d['data_inicio'],
            $d['motoristas_dia'] ?? [],
            $d['motoristas_noite'] ?? []
        );

        return response()->json(['inseridos' => $inseridos], 201);
    }
}
