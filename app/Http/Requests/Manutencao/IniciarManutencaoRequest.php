<?php

namespace App\Http\Requests\Manutencao;

use App\Models\VeiculoManutencao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IniciarManutencaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(VeiculoManutencao::TIPOS))],
            'motivo' => 'nullable|required_if:tipo,outro|string|max:255',
            'km_entrada' => 'nullable|integer|min:0',
            'km_chegada' => 'nullable|integer|min:0',
            'local' => 'nullable|string|max:150',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Informe o tipo de manutenção.',
            'motivo.required_if' => 'Descreva o motivo da manutenção.',
        ];
    }
}
