<?php

namespace App\Http\Requests\Viagem;

use App\Models\Veiculo;
use Illuminate\Foundation\Http\FormRequest;

class UpdateViagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id' => 'sometimes|uuid|exists:veiculos,id',
            'motorista_id' => 'sometimes|uuid|exists:motoristas,id',
            'origem' => 'sometimes|string|max:150',
            'destino' => 'sometimes|string|max:150',
            'motivo_viagem' => 'sometimes|in:transferencia_paciente,buscar_medico,material_outro_hospital,transporte_colaborador,buscar_material_fornecedor,tfd,alimentacao',
            'numero_atendimento' => 'required_if:motivo_viagem,transferencia_paciente|nullable|integer|min:100000|max:999999',
            'km_saida' => 'sometimes|integer|min:0',
            'km_chegada' => 'nullable|integer|min:0',
            'status' => 'sometimes|in:em_andamento,concluida,cancelada',
            'observacoes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'numero_atendimento.min' => 'O número do atendimento deve ter exatamente 6 dígitos e não pode ser 000000.',
            'numero_atendimento.max' => 'O número do atendimento deve ter exatamente 6 dígitos.',
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->filled('motivo_viagem') || $validator->errors()->hasAny(['motivo_viagem', 'veiculo_id'])) {
                return;
            }
            $veiculo = Veiculo::find($this->input('veiculo_id', ($this->route('viagem') ?? $this->route('viagen'))?->veiculo_id));
            if ($veiculo && ! $veiculo->permiteMotivoViagem($this->input('motivo_viagem'))) {
                $validator->errors()->add('motivo_viagem', $veiculo->ehAmbulancia()
                    ? 'Motivo não permitido para ambulância.'
                    : 'Motivo não permitido para veículo administrativo.');
            }
        }];
    }
}
