<?php

namespace App\Http\Requests\Solicitacao;

use App\Rules\MotivoViagemValido;
use App\Rules\PontoViagemExiste;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', new MotivoViagemValido(solicitacao: true)],

            'origem_tipo' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|in:unidade,localidade',
            'origem_id' => ['required_if:motivo,transferencia_paciente,transporte_colaborador', 'nullable', 'uuid', new PontoViagemExiste($this->input('origem_tipo'))],
            'destino_tipo' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|in:unidade,localidade',
            'destino_id' => ['required_if:motivo,transferencia_paciente,transporte_colaborador', 'nullable', 'uuid', new PontoViagemExiste($this->input('destino_tipo'))],
            'numero_atendimento' => 'required_if:motivo,transferencia_paciente|nullable|integer|digits_between:1,6',
            'autorizacao_referencia_em' => 'required_if:motivo,transferencia_paciente|nullable|date|before_or_equal:now',
            'cidade' => 'required_if:motivo,buscar_medico|nullable|string|max:150',
            'hospital_destino' => 'required_if:motivo,material_outro_hospital|nullable|string|max:150',
            'fornecedor_nome' => 'required_if:motivo,buscar_material_fornecedor|nullable|string|max:150',

            'observacoes' => 'nullable|string',
        ];
    }
}
