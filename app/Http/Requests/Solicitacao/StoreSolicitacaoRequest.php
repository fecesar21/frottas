<?php

namespace App\Http\Requests\Solicitacao;

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
            'motivo' => 'required|in:transferencia_paciente,buscar_medico,material_outro_hospital,transporte_colaborador,buscar_material_fornecedor,tfd',

            'origem_unidade_id' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|uuid|exists:unidades,id',
            'destino_unidade_id' => 'required_if:motivo,transferencia_paciente,transporte_colaborador|nullable|uuid|exists:unidades,id',
            'numero_atendimento' => 'required_if:motivo,transferencia_paciente|nullable|integer|digits_between:1,6',
            'cidade' => 'required_if:motivo,buscar_medico|nullable|string|max:150',
            'hospital_destino' => 'required_if:motivo,material_outro_hospital|nullable|string|max:150',
            'fornecedor_nome' => 'required_if:motivo,buscar_material_fornecedor|nullable|string|max:150',

            'observacoes' => 'nullable|string',
        ];
    }
}
