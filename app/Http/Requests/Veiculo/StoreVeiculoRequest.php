<?php

namespace App\Http\Requests\Veiculo;

use Illuminate\Foundation\Http\FormRequest;

class StoreVeiculoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placa'              => 'required|string|max:10|unique:veiculos',
            'modelo'             => 'required|string|max:100',
            'marca'              => 'nullable|string|max:50',
            'ano'                => 'required|integer|min:1980|max:' . (date('Y') + 1),
            'cor'                => 'nullable|string|max:30',
            'chassi'             => 'nullable|string|max:17|unique:veiculos',
            'renavam'            => 'nullable|string|max:11|unique:veiculos',
            'combustivel'        => 'nullable|in:diesel_s10,diesel_s500,gasolina,gasolina_aditivada,etanol,gnv,flex',
            'capacidade_tanque'  => 'nullable|numeric|min:0',
            'km_atual'           => 'nullable|integer|min:0',
            'km_proxima_revisao' => 'nullable|integer|min:0',
            'observacoes'        => 'nullable|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->placa) {
            $this->merge(['placa' => strtoupper($this->placa)]);
        }
    }
}
