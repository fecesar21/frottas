<?php

namespace App\Http\Requests\Viagem;

use Illuminate\Foundation\Http\FormRequest;

class StoreViagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id'   => 'required|uuid|exists:veiculos,id',
            'motorista_id' => 'required|uuid|exists:motoristas,id',
            'checkin_id'   => 'nullable|uuid|exists:checkins,id',
            'origem'       => 'required|string|max:150',
            'destino'      => 'required|string|max:150',
            'km_saida'     => 'required|integer|min:0',
        ];
    }
}
