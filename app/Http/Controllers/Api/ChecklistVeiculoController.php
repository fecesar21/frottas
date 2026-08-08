<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChecklistVeiculo\AtualizarItemChecklistRequest;
use App\Http\Requests\ChecklistVeiculo\EnviarChecklistRequest;
use App\Models\Checkin;
use App\Models\ChecklistVeiculo;
use App\Models\ChecklistVeiculoItemModelo;
use App\Models\Motorista;
use App\Services\ChecklistVeiculoService;
use Illuminate\Http\Request;

class ChecklistVeiculoController extends Controller
{
    public function __construct(private ChecklistVeiculoService $service) {}

    public function itensModelo()
    {
        $itens = ChecklistVeiculoItemModelo::with('categoria')
            ->where('ativo', true)
            ->orderBy('categoria_id')
            ->orderBy('ordem')
            ->get();

        return response()->json($itens);
    }

    public function pendente(Request $r)
    {
        if ($r->checkin_id) {
            $checkin = Checkin::findOrFail($r->checkin_id);
        } else {
            $motorista = Motorista::with('checkinAtivo')->find(auth()->user()->motorista_id);
            $checkin = $motorista?->checkinAtivo;
        }

        if (! $checkin) {
            return response()->json(['error' => 'Nenhum check-in ativo encontrado.'], 403);
        }

        if (auth()->user()->perfil === 'operador' && $checkin->motorista_id !== auth()->user()->motorista_id) {
            abort(403);
        }

        $checklist = $this->service->iniciarOuObter($checkin);

        return response()->json($checklist->setAttribute('precisa_enviar', $checklist->status !== 'enviado'));
    }

    public function index(Request $r)
    {
        $isOperador = auth()->user()->perfil === 'operador';

        $checklists = ChecklistVeiculo::with(['veiculo', 'motorista'])
            ->when($r->veiculo_id, fn ($q, $id) => $q->where('veiculo_id', $id))
            ->when($r->status, fn ($q, $s) => $q->where('status', $s))
            ->when($isOperador, fn ($q) => $q->where('motorista_id', auth()->user()->motorista_id))
            ->latest('data_referencia')
            ->limit(200)
            ->get();

        return response()->json($checklists);
    }

    public function show(ChecklistVeiculo $checklistVeiculo)
    {
        if (auth()->user()->perfil === 'operador' && $checklistVeiculo->motorista_id !== auth()->user()->motorista_id) {
            abort(403);
        }

        return response()->json($checklistVeiculo->load(['veiculo', 'motorista', 'respostas.itemModelo.categoria']));
    }

    public function atualizarItem(AtualizarItemChecklistRequest $request, ChecklistVeiculo $checklistVeiculo)
    {
        $data = $request->validated();

        if ($request->hasFile('foto')) {
            $data['foto_path'] = $request->file('foto')->store('checklist_veiculo/fotos', 'public');
        }

        $resposta = $this->service->atualizarItem($checklistVeiculo, $data);

        return response()->json($resposta);
    }

    public function enviar(EnviarChecklistRequest $request, ChecklistVeiculo $checklistVeiculo)
    {
        $checklist = $this->service->enviar($checklistVeiculo, $request->validated());

        return response()->json($checklist);
    }
}
