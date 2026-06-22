<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Abastecimento\StoreAbastecimentoRequest;
use App\Http\Resources\AbastecimentoResource;
use App\Models\Abastecimento;
use App\Models\Motorista;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbastecimentoController extends Controller
{
    public function index(Request $r)
    {
        $abastecimentos = Abastecimento::with(['veiculo', 'motorista'])
            ->when($r->veiculo_id, fn ($q, $id) => $q->where('veiculo_id', $id))
            ->latest('abastecido_at')
            ->limit(200)
            ->get();

        return AbastecimentoResource::collection($abastecimentos);
    }

    public function show(Abastecimento $abastecimento)
    {
        return new AbastecimentoResource($abastecimento);
    }

    public function store(StoreAbastecimentoRequest $request)
    {
        $data = $request->validated();

        if (auth()->user()->perfil === 'operador') {
            $motorista = Motorista::with('checkinAtivo')->find(auth()->user()->motorista_id);
            $checkin = $motorista?->checkinAtivo;

            if (!$checkin) {
                return response()->json(['error' => 'Realize o check-in antes de registrar um abastecimento.'], 403);
            }

            $data['motorista_id'] = auth()->user()->motorista_id;
            $data['veiculo_id']   = $checkin->veiculo_id;
        }

        $data['abastecido_at'] = $data['abastecido_at'] ?? now();

        return (new AbastecimentoResource(Abastecimento::create($data)))->response()->setStatusCode(201);
    }

    public function destroy(Abastecimento $abastecimento)
    {
        $abastecimento->delete();

        return response()->json(['message' => 'Abastecimento excluído']);
    }

    public function resumo()
    {
        return response()->json(DB::select('SELECT * FROM vw_resumo_abastecimentos LIMIT 50'));
    }
}
