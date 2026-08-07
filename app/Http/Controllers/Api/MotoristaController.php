<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Motorista\StoreMotoristaRequest;
use App\Http\Requests\Motorista\UpdateMotoristaRequest;
use App\Http\Resources\MotoristaResource;
use App\Models\Motorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MotoristaController extends Controller
{
    // Admin e gestor enxergam motoristas de todas as unidades por padrão,
    // podendo filtrar opcionalmente via ?unidade_id=. Demais perfis usam a própria unidade.
    private function unidadeFiltro(Request $r): ?string
    {
        return in_array($r->user()->perfil, ['admin', 'gestor'])
            ? $r->query('unidade_id')
            : $r->unidade_efetiva;
    }

    public function index(Request $r)
    {
        $unidadeId = $this->unidadeFiltro($r);

        $motoristas = Motorista::with('checkinAtivo.veiculo')
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->when($unidadeId, fn ($q) => $q->whereHas('unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->orderBy('nome')
            ->get();

        return MotoristaResource::collection($motoristas);
    }

    public function show(Motorista $motorista)
    {
        return new MotoristaResource($motorista->load(['checkinAtivo.veiculo', 'unidades']));
    }

    public function store(StoreMotoristaRequest $request)
    {
        return (new MotoristaResource(Motorista::create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateMotoristaRequest $request, Motorista $motorista)
    {
        $motorista->update($request->validated());

        if (($request->validated()['status'] ?? null) === 'inativo') {
            $motorista->usuario?->update(['ativo' => false]);
        }

        return new MotoristaResource($motorista->fresh());
    }

    public function destroy(Motorista $motorista)
    {
        $motorista->update(['status' => 'inativo']);
        $motorista->usuario?->update(['ativo' => false]);

        return response()->json(['message' => 'Motorista desativado']);
    }

    public function disponiveis(Request $r)
    {
        $unidadeId = $this->unidadeFiltro($r);

        $motoristas = Motorista::where('status', 'ativo')
            ->whereHas('usuario', fn ($q) => $q->where('perfil', 'operador'))
            ->whereHas('checkinAtivo')
            ->when($unidadeId, fn ($q) => $q->whereHas('unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        return MotoristaResource::collection($motoristas);
    }

    public function semUsuario(Request $r)
    {
        $unidadeId = $this->unidadeFiltro($r);

        $motoristas = Motorista::where('status', 'ativo')
            ->whereDoesntHave('usuario')
            ->when($unidadeId, fn ($q) => $q->whereHas('unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        return MotoristaResource::collection($motoristas);
    }

    public function alertasCnh()
    {
        return response()->json(DB::select('SELECT * FROM vw_cnh_vencimento'));
    }
}
