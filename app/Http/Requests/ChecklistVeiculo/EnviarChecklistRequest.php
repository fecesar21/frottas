<?php

namespace App\Http\Requests\ChecklistVeiculo;

use Illuminate\Foundation\Http\FormRequest;

class EnviarChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'observacoes_gerais' => 'nullable|string',
        ];
    }
}
