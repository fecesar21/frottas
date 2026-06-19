<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plantao\AtualizarItemRequest;
use App\Http\Requests\Plantao\StorePlantaoRequest;
use App\Http\Resources\PlantaoResource;
use App\Models\ChecklistItemModelo;
use App\Models\ChecklistResposta;
use App\Models\PassagemPlantao;
use App\Services\PlantaoService;
use Illuminate\Http\Request;

class PlantaoController extends Controller
{
    public function __construct(private PlantaoService $service) {}

    public function index(Request $request)
    {
        $passagens = PassagemPlantao::with(['veiculo', 'motoristaSaindo', 'motoristaEntrando'])
            ->when($request->veiculo_id, fn ($q, $id) => $q->where('veiculo_id', $id))
            ->latest()
            ->limit((int) ($request->limit ?? 20))
            ->get();

        return PlantaoResource::collection($passagens);
    }

    public function show(PassagemPlantao $plantao)
    {
        $plantao->load(['veiculo', 'motoristaSaindo', 'motoristaEntrando', 'respostas.itemModelo.categoria']);

        return new PlantaoResource($plantao);
    }

    public function store(StorePlantaoRequest $request)
    {
        $passagem = $this->service->store($request->validated());

        return (new PlantaoResource($passagem))->response()->setStatusCode(201);
    }

    public function atualizarItem(AtualizarItemRequest $request, PassagemPlantao $plantao)
    {
        $data = $request->validated();

        $resposta = ChecklistResposta::where('passagem_id', $plantao->id)
            ->where('item_modelo_id', $data['item_modelo_id'])
            ->firstOrFail();

        $resposta->update([
            'resultado'  => $data['resultado'],
            'observacao' => $data['observacao'] ?? null,
        ]);

        // Recalcular contadores
        $okCount         = ChecklistResposta::where('passagem_id', $plantao->id)->where('resultado', 'ok')->count();
        $pendenciaCount  = ChecklistResposta::where('passagem_id', $plantao->id)->where('resultado', 'pendencia')->count();
        $plantao->update(['itens_ok' => $okCount, 'itens_pendencia' => $pendenciaCount]);

        return response()->json($resposta);
    }

    public function finalizar(Request $request, PassagemPlantao $plantao)
    {
        $data = $request->validate(['observacoes_gerais' => 'nullable|string']);

        $plantao->update([
            'observacoes_gerais' => $data['observacoes_gerais'] ?? $plantao->observacoes_gerais,
            'finalizado_at'      => now(),
        ]);

        return new PlantaoResource($plantao->fresh());
    }

    public function modeloItens()
    {
        $itens = ChecklistItemModelo::with('categoria')
            ->where('ativo', true)
            ->orderBy('categoria_id')
            ->orderBy('ordem')
            ->get();

        return response()->json($itens);
    }

    public function encerrar(Request $r, PassagemPlantao $plantao)
    {
        $plantao->update([
            'finalizado_at'  => now(),
            'observacoes_gerais' => $r->observacoes_encerramento ?? $plantao->observacoes_gerais,
        ]);

        return new PlantaoResource($plantao->fresh());
    }
}
