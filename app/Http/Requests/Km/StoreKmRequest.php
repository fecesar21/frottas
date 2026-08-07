<?php

namespace App\Http\Requests\Km;

use Illuminate\Foundation\Http\FormRequest;

class StoreKmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id'   => 'required|uuid|exists:veiculos,id',
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
            'km_atual'     => 'required|integer|min:0',
            'observacao'   => 'nullable|string',
        ];
    }
}
