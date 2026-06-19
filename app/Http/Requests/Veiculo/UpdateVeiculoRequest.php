<?php

namespace App\Http\Requests\Veiculo;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modelo'             => 'sometimes|string|max:100',
            'marca'              => 'nullable|string|max:50',
            'ano'                => 'sometimes|integer|min:1980',
            'cor'                => 'nullable|string|max:30',
            'combustivel'        => 'sometimes|in:diesel_s10,diesel_s500,gasolina,gasolina_aditivada,etanol,gnv,flex',
            'capacidade_tanque'  => 'nullable|numeric|min:0',
            'km_proxima_revisao' => 'nullable|integer|min:0',
            'status'             => 'sometimes|in:disponivel,em_uso,manutencao,inativo',
            'observacoes'        => 'nullable|string',
        ];
    }
}
