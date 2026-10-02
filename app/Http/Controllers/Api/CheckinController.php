<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkin\CheckoutRequest;
use App\Http\Requests\Checkin\StoreCheckinRequest;
use App\Http\Resources\CheckinResource;
use App\Models\Checkin;
use App\Models\Solicitacao;
use App\Models\Viagem;
use App\Services\CheckinService;
use App\Services\SolicitacaoService;
use App\Services\ViagemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckinController extends Controller
{
    public function __construct(private CheckinService $service) {}

    public function index(Request $request)
    {
        $unidadeId = $request->unidade_efetiva;
        $perPage = max(1, min((int) $request->integer('per_page', 25), 100));
        $isOperador = auth()->user()->perfil === 'operador';

        $checkins = Checkin::with(['motorista', 'veiculo'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->data, fn ($q, $d) => $q->whereDate('checkin_at', $d))
            ->when($isOperador, fn ($q) => $q->where('motorista_id', auth()->user()->motorista_id))
            ->when(! $isOperador && $unidadeId, fn ($q) => $q->whereHas('motorista.unidades', fn ($u) => $u->where('unidades.id', $unidadeId)))
            ->latest('checkin_at')
            ->paginate($perPage);

        return CheckinResource::collection($checkins);
    }

    public function show(Checkin $checkin)
    {
        if (auth()->user()->perfil === 'operador' && $checkin->motorista_id !== auth()->user()->motorista_id) {
            abort(403);
        }

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

    private function encerrarViagemNoCheckout(Viagem $viagem, ?int $kmChegada): void
    {
        if ($kmChegada === null) {
            throw ValidationException::withMessages([
                'km_chegada_viagem' => 'Há uma viagem em andamento com este veículo. Informe o KM de chegada para encerrá-la.',
            ]);
        }

        DB::transaction(function () use ($viagem, $kmChegada) {
            // Devolve a fila antes, para a chegada não chamar o motorista para a
            // próxima solicitação deste veículo (mesmo padrão da manutenção).
            Solicitacao::whereIn('status', ['pendente_motorista', 'aguardando_finalizacao_trajeto'])
                ->where('veiculo_pendente_id', $viagem->veiculo_id)
                ->get()
                ->each(fn (Solicitacao $s) => app(SolicitacaoService::class)->devolverParaFila($s));

            app(ViagemService::class)->chegada($viagem, [
                'km_chegada' => $kmChegada,
                'observacoes' => trim(($viagem->observacoes ? $viagem->observacoes.' ' : '').'[Finalizada no check-out]'),
            ]);
        });
    }

    public function checkout(CheckoutRequest $request, Checkin $checkin)
    {
        $isOperador = auth()->user()->perfil === 'operador';

        if ($isOperador && $checkin->motorista_id !== auth()->user()->motorista_id) {
            abort(403);
        }

        // A gestão encerra no próprio check-out a viagem que ficou em andamento;
        // para o motorista o CheckinService continua bloqueando.
        if (! $isOperador && $checkin->status === 'ativo' && ($viagem = $checkin->viagemEmAndamento())) {
            $this->encerrarViagemNoCheckout($viagem, $request->validated('km_chegada_viagem'));
        }

        $checkin = $this->service->checkout($checkin, $request->validated(), $isOperador);

        return new CheckinResource($checkin);
    }
}
