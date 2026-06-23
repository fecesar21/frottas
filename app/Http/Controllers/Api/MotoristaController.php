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
    public function index(Request $r)
    {
        $motoristas = Motorista::with('checkinAtivo.veiculo')
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('nome')
            ->get();

        return MotoristaResource::collection($motoristas);
    }

    public function show(Motorista $motorista)
    {
        return new MotoristaResource($motorista->load('checkinAtivo.veiculo'));
    }

    public function store(StoreMotoristaRequest $request)
    {
        return (new MotoristaResource(Motorista::create($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateMotoristaRequest $request, Motorista $motorista)
    {
        $motorista->update($request->validated());

        return new MotoristaResource($motorista->fresh());
    }

    public function destroy(Motorista $motorista)
    {
        $motorista->update(['status' => 'inativo']);

        return response()->json(['message' => 'Motorista desativado']);
    }

    public function disponiveis()
    {
        $motoristas = Motorista::where('status', 'ativo')
            ->whereDoesntHave('usuario', fn ($q) => $q->where('perfil', 'operador'))
            ->orderBy('nome')
            ->get(['id', 'nome', 'cpf']);

        return MotoristaResource::collection($motoristas);
    }

    public function alertasCnh()
    {
        return response()->json(DB::select('SELECT * FROM vw_cnh_vencimento'));
    }
}
