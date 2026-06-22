<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Viagem\StoreViagemRequest;
use App\Http\Requests\Viagem\UpdateViagemRequest;
use App\Http\Resources\ViagemResource;
use App\Models\Motorista;
use App\Models\Viagem;
use App\Services\ViagemService;
use Illuminate\Http\Request;

class ViagemController extends Controller
{
    public function __construct(private ViagemService $service) {}

    public function index(Request $r)
    {
        $viagens = Viagem::with(['veiculo', 'motorista'])
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('saida_at')
            ->limit(200)
            ->get();

        return ViagemResource::collection($viagens);
    }

    public function show(Viagem $viagem)
    {
        return new ViagemResource($viagem->load(['veiculo', 'motorista']));
    }

    public function store(StoreViagemRequest $request)
    {
        $data = $request->validated();

        if (auth()->user()->perfil === 'operador') {
            $motorista = Motorista::with('checkinAtivo')->find(auth()->user()->motorista_id);
            $checkin = $motorista?->checkinAtivo;

            if (!$checkin) {
                return response()->json(['error' => 'Realize o check-in antes de registrar uma viagem.'], 403);
            }

            $data['motorista_id'] = auth()->user()->motorista_id;
            $data['veiculo_id']   = $checkin->veiculo_id;
            $data['checkin_id']   = $checkin->id;
        }

        $viagem = $this->service->store($data);

        return (new ViagemResource($viagem->load(['veiculo', 'motorista'])))->response()->setStatusCode(201);
    }

    public function update(UpdateViagemRequest $request, Viagem $viagem)
    {
        $viagem->update($request->validated());

        return new ViagemResource($viagem->fresh());
    }

    public function chegada(Request $r, Viagem $viagem)
    {
        $data = $r->validate(['km_chegada' => 'required|integer|min:0', 'observacoes' => 'nullable|string']);
        $viagem = $this->service->chegada($viagem, $data);

        return new ViagemResource($viagem);
    }
}
