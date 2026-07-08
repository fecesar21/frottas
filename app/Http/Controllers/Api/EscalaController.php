<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Escala\GerarSemanaRequest;
use App\Http\Requests\Escala\StoreEscalaRequest;
use App\Http\Resources\EscalaResource;
use App\Models\Escala;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Services\EscalaService;
use Illuminate\Http\Request;

class EscalaController extends Controller
{
    public function __construct(private EscalaService $service) {}

    public function index(Request $r)
    {
        $de = $r->get('de', now()->toDateString());
        $ate = $r->get('ate', now()->toDateString());
        $unidadeId = $r->unidade_efetiva;

        $escalas = Escala::with(['motorista', 'veiculo'])
            ->whereBetween('data', [$de, $ate])
            ->when($unidadeId, fn ($q) => $q->whereHas('motorista.unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->orderBy('data')
            ->orderBy('turno')
            ->paginate(min((int) $r->integer('per_page', 25), 100));

        return EscalaResource::collection($escalas);
    }

    public function store(StoreEscalaRequest $request)
    {
        $d = $request->validated();
        $unidadeId = $request->unidade_efetiva;

        if ($unidadeId) {
            $this->validarMotoristaUnidade($d['motorista_id'], $unidadeId);
            if (! empty($d['veiculo_id'])) {
                $this->validarVeiculoUnidade($d['veiculo_id'], $unidadeId);
            }
        }

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

    private function validarMotoristaUnidade(string $motoristaId, string $unidadeId): void
    {
        $pertence = Motorista::where('id', $motoristaId)
            ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $unidadeId))
            ->exists();

        abort_unless($pertence, 422, 'O motorista não pertence à unidade informada.');
    }

    private function validarVeiculoUnidade(string $veiculoId, string $unidadeId): void
    {
        $pertence = Veiculo::where('id', $veiculoId)
            ->whereHas('unidades', fn ($q) => $q->where('unidades.id', $unidadeId))
            ->exists();

        abort_unless($pertence, 422, 'O veículo não pertence à unidade informada.');
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
