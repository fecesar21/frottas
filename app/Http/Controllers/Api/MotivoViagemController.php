<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MotivoViagemResource;
use App\Models\MotivoViagem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MotivoViagemController extends Controller
{
    public function index(Request $r): AnonymousResourceCollection
    {
        // "todos" (inclui inativos e flag em_uso) é exclusivo do admin
        $todos = $r->boolean('todos') && $r->user()->perfil === 'admin';
        $q = MotivoViagem::query()->orderBy('nome');
        if (! $todos) {
            $q->ativos();
        }
        if ($r->boolean('solicitacao')) {
            $q->paraSolicitacao();
        }
        $motivos = $q->get();
        if ($todos) {
            $motivos->each(fn ($m) => $m->setAttribute('em_uso', $m->emUso()));
        }

        return MotivoViagemResource::collection($motivos);
    }

    public function store(Request $r): JsonResponse
    {
        $data = $r->validate($this->regras());
        $data['codigo'] = MotivoViagem::gerarCodigo($data['nome']);

        return (new MotivoViagemResource(MotivoViagem::create($data)))->response()->setStatusCode(201);
    }

    public function update(Request $r, MotivoViagem $motivoViagem): MotivoViagemResource
    {
        // "codigo" não consta nas regras: é imutável após a criação
        $data = $r->validate($this->regras($motivoViagem));
        if ($motivoViagem->sistema && isset($data['tipo_veiculo']) && $data['tipo_veiculo'] !== $motivoViagem->tipo_veiculo) {
            throw ValidationException::withMessages(['tipo_veiculo' => 'O tipo de veículo de um motivo de sistema não pode ser alterado.']);
        }
        $motivoViagem->update($data);

        return new MotivoViagemResource($motivoViagem);
    }

    public function destroy(MotivoViagem $motivoViagem): JsonResponse
    {
        abort_if($motivoViagem->sistema, 422, 'Motivos de sistema não podem ser excluídos. Inative-o.');
        abort_if($motivoViagem->emUso(), 422, 'Motivo já usado em viagens ou solicitações. Inative-o em vez de excluir.');
        $motivoViagem->delete();

        return response()->json(['message' => 'Motivo excluído.']);
    }

    private function regras(?MotivoViagem $atual = null): array
    {
        $req = $atual ? 'sometimes' : 'required';

        return [
            'nome' => [$req, 'string', 'max:120', Rule::unique('motivos_viagem', 'nome')->ignore($atual?->id)],
            'tipo_veiculo' => [$req, Rule::in(['administrativo', 'ambulancia', 'ambos'])],
            'disponivel_solicitacao' => 'sometimes|boolean',
            'ativo' => 'sometimes|boolean',
        ];
    }
}
