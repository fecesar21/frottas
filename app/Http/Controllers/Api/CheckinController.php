<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkin\CheckoutRequest;
use App\Http\Requests\Checkin\StoreCheckinRequest;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Services\CheckinService;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function __construct(private CheckinService $service) {}

    public function index(Request $request)
    {
        $checkins = Checkin::with(['motorista', 'veiculo'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->data,   fn ($q, $d) => $q->whereDate('checkin_at', $d))
            ->latest('checkin_at')
            ->limit(100)
            ->get();

        return CheckinResource::collection($checkins);
    }

    public function show(Checkin $checkin)
    {
        return new CheckinResource($checkin->load(['motorista', 'veiculo', 'escala']));
    }

    public function store(StoreCheckinRequest $request)
    {
        $data = $request->validated();

        if (auth()->user()->perfil === 'operador') {
            $data['motorista_id'] = auth()->user()->motorista_id;
        }

        $checkin = $this->service->store($data);

        return (new CheckinResource($checkin))->response()->setStatusCode(201);
    }

    public function checkout(CheckoutRequest $request, Checkin $checkin)
    {
        $checkin = $this->service->checkout($checkin, $request->validated());

        return new CheckinResource($checkin);
    }
}
