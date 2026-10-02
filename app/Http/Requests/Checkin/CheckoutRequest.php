<?php

namespace App\Http\Requests\Checkin;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'km_retorno' => 'nullable|integer|min:0',
            'nivel_combustivel_retorno' => 'nullable|numeric|min:0|max:100',
            'ocorrencias' => 'nullable|string',
            'justificativa_divergencia_km' => 'nullable|string|max:500',
            'km_chegada_viagem' => 'nullable|integer|min:0',
        ];
    }
}
