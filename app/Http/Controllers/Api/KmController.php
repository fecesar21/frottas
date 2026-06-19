<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Km\StoreKmRequest;
use App\Http\Resources\KmRegistroResource;
use App\Models\KmRegistro;
use App\Services\KmService;
use Illuminate\Http\Request;

class KmController extends Controller
{
    public function __construct(private KmService $service) {}

    public function index(Request $r)
    {
        $registros = KmRegistro::with(['veiculo', 'motorista'])
            ->when($r->veiculo_id, fn ($q, $id) => $q->where('veiculo_id', $id))
            ->latest('registrado_at')
            ->limit(200)
            ->get();

        return KmRegistroResource::collection($registros);
    }

    public function store(StoreKmRequest $request)
    {
        $registro = $this->service->store($request->validated());

        return (new KmRegistroResource($registro))->response()->setStatusCode(201);
    }
}
