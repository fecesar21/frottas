<?php

namespace App\Http\Requests\Plantao;

use Illuminate\Foundation\Http\FormRequest;

class StorePlantaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'veiculo_id'            => 'required|uuid|exists:veiculos,id',
            'motorista_saindo_id'   => 'required|uuid|exists:motoristas,id',
            'motorista_entrando_id' => 'required|uuid|exists:motoristas,id|different:motorista_saindo_id',
            'turno_saindo'          => 'required|in:dia,noite',
            'turno_entrando'        => 'required|in:dia,noite',
            'km_momento'            => 'required|integer|min:0',
            'nivel_combustivel'     => 'nullable|numeric|min:0|max:100',
            'checkin_saida_id'      => 'nullable|uuid|exists:checkins,id',
            'checkin_entrada_id'    => 'nullable|uuid|exists:checkins,id',
        ];
    }
}
