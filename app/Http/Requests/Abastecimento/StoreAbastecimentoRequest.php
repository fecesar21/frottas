<?php

namespace App\Http\Requests\Abastecimento;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbastecimentoRequest extends FormRequest
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
            'posto'        => 'nullable|string|max:100',
            'combustivel'  => 'required|in:diesel_s10,diesel_s500,gasolina,gasolina_aditivada,etanol,gnv,flex',
            'litros'       => 'required|numeric|min:0.001',
            'valor_litro'  => 'required|numeric|min:0.001',
            'km_momento'   => 'required|integer|min:0',
            'nota_fiscal'  => 'nullable|string|max:60',
            'observacoes'  => 'nullable|string',
        ];
    }
}
