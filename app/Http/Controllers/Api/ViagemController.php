<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viagem\StoreViagemRequest;
use App\Http\Requests\Viagem\UpdateViagemRequest;
use App\Http\Resources\ViagemResource;
use App\Models\AuditoriaCorrecao;
use App\Models\Motorista;
use App\Models\Viagem;
use App\Services\ChecklistVeiculoService;
use App\Services\ViagemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViagemController extends Controller
{
    public function __construct(
        private ViagemService $service,
        private ChecklistVeiculoService $checklistService,
    ) {}

    public function index(Request $r)
    {
        $unidadeId = $r->unidade_efetiva;
        $isOperador = auth()->user()->perfil === 'operador';

        $viagens = Viagem::with(['veiculo', 'motorista'])
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->when($isOperador, fn ($q) => $q->where('motorista_id', auth()->user()->motorista_id))
            ->when(! $isOperador && $unidadeId, fn ($q) => $q->whereHas('motorista.unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->latest('saida_at')
            ->limit(200)
            ->get();

        return ViagemResource::collection($viagens);
    }

    public function show(Viagem $viagem)
    {
        if (auth()->user()->perfil === 'operador' && $viagem->motorista_id !== auth()->user()->motorista_id) {
            abort(403);
        }

        return new ViagemResource($viagem->load(['veiculo', 'motorista', 'colaboradores.unidade:id,nome']));
    }

    public function store(StoreViagemRequest $request)
    {
        $data = $request->validated();

        if (auth()->user()->perfil === 'operador') {
            $motorista = Motorista::find(auth()->user()->motorista_id);
            $checkin = $motorista?->resolverCheckinAtivo($request->input('veiculo_id'));

            if (! $checkin) {
                return response()->json(['error' => 'Realize o check-in antes de registrar uma viagem.'], 403);
            }

            $data['motorista_id'] = auth()->user()->motorista_id;
            $data['veiculo_id'] = $checkin->getAttribute('veiculo_id');
            $data['checkin_id'] = $checkin->getAttribute('id');
        }

        if ($this->checklistService->bloqueiaOperacao($data['veiculo_id'], $checkin ?? null)) {
            return response()->json(['error' => 'Checklist do veículo pendente. Realize o checklist antes de iniciar a viagem.'], 403);
        }

        $viagem = $this->service->store($data);

        return (new ViagemResource($viagem->load(['veiculo', 'motorista', 'colaboradores.unidade:id,nome'])))->response()->setStatusCode(201);
    }

    public function update(UpdateViagemRequest $request, Viagem $viagem)
    {
        $data = $request->validated();
        $antes = $viagem->only(array_keys($data));
        $viagem->update($data);
        AuditoriaCorrecao::registrar($viagem, 'viagem', 'correcao', $antes, $viagem->only(array_keys($data)));

        return new ViagemResource($viagem->fresh());
    }

    public function corrigir(Request $r, Viagem $viagem)
    {
        $data = $r->validate([
            'km_saida' => 'required|integer|min:0',
            'km_chegada' => 'nullable|integer|min:0',
        ]);

        if ($viagem->status !== 'concluida') {
            unset($data['km_chegada']);
        } elseif (! isset($data['km_chegada'])) {
            return response()->json(['message' => 'Informe o KM de chegada.', 'errors' => ['km_chegada' => ['Informe o KM de chegada.']]], 422);
        }

        if (isset($data['km_chegada']) && $data['km_chegada'] < $data['km_saida']) {
            return response()->json(['message' => 'KM de chegada menor que KM de saída.', 'errors' => ['km_chegada' => ['KM de chegada menor que KM de saída.']]], 422);
        }

        $antes = $viagem->only(array_keys($data));
        $viagem->update($data);
        AuditoriaCorrecao::registrar($viagem, 'viagem', 'correcao', $antes, $viagem->only(array_keys($data)));

        return new ViagemResource($viagem->fresh(['veiculo', 'motorista', 'colaboradores.unidade:id,nome']));
    }

    public function chegada(Request $r, Viagem $viagem)
    {
        $data = $r->validate([
            'km_chegada' => 'required|integer|min:0',
            'observacoes' => 'nullable|string',
            'retornar_origem' => 'sometimes|boolean',
        ]);

        if ($r->boolean('retornar_origem') && $viagem->motivo_viagem !== 'transferencia_paciente') {
            return response()->json([
                'message' => 'Retorno à origem disponível apenas para transferência de paciente.',
                'errors' => ['retornar_origem' => ['Retorno à origem disponível apenas para transferência de paciente.']],
            ], 422);
        }

        [$viagem, $retorno] = DB::transaction(function () use ($viagem, $data, $r) {
            $concluida = $this->service->chegada($viagem, $data);

            // Volta à origem: mesma equipe/veículo/atendimento, trajeto invertido, saindo do KM de chegada.
            $retorno = $r->boolean('retornar_origem') ? $this->service->store([
                'veiculo_id' => $concluida->veiculo_id,
                'motorista_id' => $concluida->motorista_id,
                'checkin_id' => $concluida->checkin_id,
                'origem' => $concluida->destino,
                'destino' => $concluida->origem,
                'motivo_viagem' => $concluida->motivo_viagem,
                'numero_atendimento' => $concluida->numero_atendimento,
                'km_saida' => $concluida->km_chegada,
            ]) : null;

            return [$concluida, $retorno];
        });

        $resource = new ViagemResource($viagem);

        return $retorno
            ? $resource->additional(['viagem_retorno' => new ViagemResource($retorno->load(['veiculo', 'motorista']))])
            : $resource;
    }
}
