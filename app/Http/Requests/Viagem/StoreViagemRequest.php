<?php

namespace App\Http\Requests\Viagem;

use App\Models\Veiculo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreViagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id' => 'required|uuid|exists:veiculos,id',
            'motorista_id' => 'required|uuid|exists:motoristas,id',
            'checkin_id' => 'nullable|uuid|exists:checkins,id',
            'origem' => 'required|string|max:150',
            'destino' => 'required|string|max:150',
            'motivo_viagem' => 'required|in:transferencia_paciente,buscar_medico,material_outro_hospital,transporte_colaborador,buscar_material_fornecedor,tfd,alimentacao,servicos_administrativos',
            // Exatamente 6 dígitos: rejeita "1", "000000" etc.
            'numero_atendimento' => 'required_if:motivo_viagem,transferencia_paciente|nullable|integer|min:100000|max:999999',
            'km_saida' => 'required|integer|min:0',
            'colaborador_ids' => 'required_if:motivo_viagem,transporte_colaborador|nullable|array|min:1',
            'colaborador_ids.*' => ['uuid', 'distinct', Rule::exists('colaboradores', 'id')->where('ativo', true)],
        ];
    }

    public function messages(): array
    {
        return [
            'numero_atendimento.min' => 'O número do atendimento deve ter exatamente 6 dígitos e não pode ser 000000.',
            'numero_atendimento.max' => 'O número do atendimento deve ter exatamente 6 dígitos.',
            'colaborador_ids.required_if' => 'Selecione ao menos um colaborador transportado.',
            'colaborador_ids.min' => 'Selecione ao menos um colaborador transportado.',
            'colaborador_ids.*.exists' => 'Colaborador inválido ou inativo.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->filled('motivo_viagem') || $validator->errors()->hasAny(['motivo_viagem', 'veiculo_id'])) {
                return;
            }
            $veiculo = Veiculo::find($this->input('veiculo_id'));
            if ($veiculo && ! $veiculo->permiteMotivoViagem($this->input('motivo_viagem'))) {
                $validator->errors()->add('motivo_viagem', $veiculo->ehAmbulancia()
                    ? 'Motivo não permitido para ambulância.'
                    : 'Motivo não permitido para veículo administrativo.');
            }
        }];
    }
}
