<?php

namespace App\Http\Requests\Motorista;

use Illuminate\Foundation\Http\FormRequest;

class StoreMotoristaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'          => 'required|string|max:100',
            'cpf'           => 'required|string|max:14|unique:motoristas',
            'telefone'      => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:100',
            'cnh_numero'    => 'required|string|max:20|unique:motoristas',
            'cnh_categoria' => 'required|string|max:5',
            'cnh_validade'  => 'required|date',
            'turno_padrao'  => 'nullable|in:dia,noite',
            'observacoes'   => 'nullable|string',
        ];
    }
}
