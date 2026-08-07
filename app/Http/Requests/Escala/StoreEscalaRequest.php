<?php

namespace App\Http\Requests\Escala;

use Illuminate\Foundation\Http\FormRequest;

class StoreEscalaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motorista_id' => 'required|uuid|exists:motoristas,id',
            'data'         => 'required|date',
            'turno'        => 'required|in:dia,noite,folga',
            'veiculo_id'   => 'nullable|uuid|exists:veiculos,id',
            'observacao'   => 'nullable|string',
        ];
    }
}
