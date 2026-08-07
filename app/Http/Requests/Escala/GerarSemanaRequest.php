<?php

namespace App\Http\Requests\Escala;

use Illuminate\Foundation\Http\FormRequest;

class GerarSemanaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data_inicio'        => 'required|date',
            'motoristas_dia'     => 'nullable|array',
            'motoristas_dia.*'   => 'uuid|exists:motoristas,id',
            'motoristas_noite'   => 'nullable|array',
            'motoristas_noite.*' => 'uuid|exists:motoristas,id',
        ];
    }
}
