<?php

namespace App\Http\Requests\Viagem;

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
            'veiculo_id'   => 'sometimes|uuid|exists:veiculos,id',
            'motorista_id' => 'sometimes|uuid|exists:motoristas,id',
            'origem'       => 'sometimes|string|max:150',
            'destino'      => 'sometimes|string|max:150',
            'km_saida'     => 'sometimes|integer|min:0',
            'km_chegada'   => 'nullable|integer|min:0',
            'status'       => 'sometimes|in:em_andamento,concluida,cancelada',
            'observacoes'  => 'nullable|string',
        ];
    }
}
