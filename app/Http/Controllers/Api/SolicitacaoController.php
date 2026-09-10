<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Solicitacao\StoreSolicitacaoRequest;
use App\Http\Resources\SolicitacaoResource;
use App\Models\Localidade;
use App\Models\Solicitacao;
use App\Models\Unidade;
use App\Services\SolicitacaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SolicitacaoController extends Controller
{
    public function __construct(private SolicitacaoService $service) {}

    public function index(Request $r)
    {
        $user = $r->user();

        // Admin e gestor enxergam solicitações de todas as unidades por padrão,
        // podendo filtrar opcionalmente via ?unidade_id=. Operador e solicitante só veem as próprias.
        $unidadeFiltro = in_array($user->perfil, ['admin', 'gestor']) ? $r->query('unidade_id') : null;

        $solicitacoes = Solicitacao::with(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente'])
            ->when(! in_array($user->perfil, ['admin', 'gestor']), function ($q) use ($user) {
                if ($user->motorista_id) {
                    $q->where(function ($q) use ($user) {
                        $q->where('usuario_id', $user->id)
                            ->orWhere('motorista_pendente_id', $user->motorista_id);
                    });
                } else {
                    $q->where('usuario_id', $user->id);
                }
            })
            ->when($unidadeFiltro, fn ($q) => $q->where('unidade_id', $unidadeFiltro))
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->limit(200)
            ->get();

        // Resolve nomes de origem/destino em lote (evita N+1) e disponibiliza
        // para o resource via propriedade estática. ->response() força a
        // serialização (toArray de cada item) a acontecer agora, antes de
        // limpar a estática no finally — se retornássemos a collection "crua"
        // o Router só chamaria toResponse()/toArray() depois deste método
        // retornar, quando a estática já teria sido zerada.
        SolicitacaoResource::$nomesPorId = $this->resolverNomesPorId($solicitacoes);

        try {
            return SolicitacaoResource::collection($solicitacoes)->response();
        } finally {
            SolicitacaoResource::$nomesPorId = null;
        }
    }

    /**
     * Resolve, em lote, os nomes de Unidade/Localidade referenciados pelas
     * colunas origem_id/destino_id de uma coleção de solicitações, evitando
     * o N+1 de chamar Solicitacao::origem()/destino() (que fazem find() por
     * solicitação) uma vez por linha da listagem.
     *
     * @param  Collection<int, Solicitacao>  $solicitacoes
     * @return array<string, string>
     */
    private function resolverNomesPorId($solicitacoes): array
    {
        $unidadeIds = collect();
        $localidadeIds = collect();

        foreach (['origem', 'destino'] as $prefixo) {
            $unidadeIds = $unidadeIds->merge(
                $solicitacoes->where("{$prefixo}_tipo", 'unidade')->pluck("{$prefixo}_id")
            );
            $localidadeIds = $localidadeIds->merge(
                $solicitacoes->where("{$prefixo}_tipo", 'localidade')->pluck("{$prefixo}_id")
            );
        }

        $nomesUnidades = Unidade::whereIn('id', $unidadeIds->filter()->unique())->pluck('nome', 'id');
        $nomesLocalidades = Localidade::whereIn('id', $localidadeIds->filter()->unique())->pluck('nome', 'id');

        return $nomesUnidades->merge($nomesLocalidades)->all();
    }

    public function show(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();

        $podeVer = in_array($user->perfil, ['admin', 'gestor'])
            || $solicitacao->usuario_id === $user->id
            || ($user->motorista_id && $solicitacao->motorista_pendente_id === $user->motorista_id);

        if (! $podeVer) {
            return response()->json(['error' => 'Sem permissão para visualizar esta solicitação.'], 403);
        }

        return new SolicitacaoResource(
            $solicitacao->load(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente'])
        );
    }

    public function store(StoreSolicitacaoRequest $request)
    {
        $solicitacao = $this->service->store($request->validated(), $request->user());

        return (new SolicitacaoResource($solicitacao))->response()->setStatusCode(201);
    }

    public function aceitar(Request $r, Solicitacao $solicitacao)
    {
        if (! in_array($r->user()->perfil, ['admin', 'gestor'])) {
            return response()->json(['error' => 'Apenas gestores/admins podem aceitar solicitações.'], 403);
        }

        if (! in_array($solicitacao->status, ['aberto', 'recusada'])) {
            throw ValidationException::withMessages(['status' => 'Esta solicitação já foi tratada.']);
        }

        $data = $r->validate([
            'motorista_id' => 'required|uuid|exists:motoristas,id',
            'veiculo_id' => 'required|uuid|exists:veiculos,id',
        ]);

        $solicitacao = $this->service->aceitar($solicitacao, $data['motorista_id'], $data['veiculo_id']);

        return new SolicitacaoResource($solicitacao->load(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente']));
    }

    public function motoristaAceitar(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();
        if (! $user->motorista_id || $solicitacao->motorista_pendente_id !== $user->motorista_id) {
            return response()->json(['error' => 'Esta viagem não está designada para você.'], 403);
        }

        if (! in_array($solicitacao->status, ['pendente_motorista', 'aguardando_finalizacao_trajeto'])) {
            throw ValidationException::withMessages(['status' => 'Esta solicitação já foi tratada.']);
        }

        $data = $r->validate(['km_saida' => 'nullable|integer|min:0']);

        $solicitacao = $this->service->motoristaAceitar($solicitacao, $user->motorista_id, $data['km_saida'] ?? null);

        return new SolicitacaoResource($solicitacao->load(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente']));
    }

    public function motoristaRecusar(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();
        if (! $user->motorista_id || $solicitacao->motorista_pendente_id !== $user->motorista_id) {
            return response()->json(['error' => 'Esta viagem não está designada para você.'], 403);
        }

        if (! in_array($solicitacao->status, ['pendente_motorista', 'aguardando_finalizacao_trajeto'])) {
            throw ValidationException::withMessages(['status' => 'Esta solicitação já foi tratada.']);
        }

        $data = $r->validate(['motivo' => 'required|string|max:500']);

        $solicitacao = $this->service->motoristaRecusar($solicitacao, $user->motorista_id, $data['motivo']);

        return new SolicitacaoResource($solicitacao->load(['usuario', 'viagem.motorista', 'viagem.veiculo', 'motoristaPendente', 'veiculoPendente']));
    }

    public function cancelar(Request $r, Solicitacao $solicitacao)
    {
        $user = $r->user();
        if ($solicitacao->usuario_id !== $user->id && ! in_array($user->perfil, ['admin', 'gestor'])) {
            return response()->json(['error' => 'Sem permissão para cancelar esta solicitação.'], 403);
        }

        return new SolicitacaoResource($this->service->cancelar($solicitacao));
    }
}
