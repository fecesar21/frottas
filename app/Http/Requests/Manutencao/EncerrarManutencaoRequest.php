<?php

namespace App\Http\Requests\Manutencao;

use Illuminate\Foundation\Http\FormRequest;

class EncerrarManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'km_saida' => 'nullable|integer|min:0',
            'observacao_saida' => 'nullable|string|max:255',
        ];
    }
}
