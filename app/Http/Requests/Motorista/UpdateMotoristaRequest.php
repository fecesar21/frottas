<?php

namespace App\Http\Requests\Motorista;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMotoristaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'          => 'sometimes|string|max:100',
            'telefone'      => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:100',
            'cpf'           => 'sometimes|string|max:14',
            'cnh_numero'    => 'sometimes|string|max:20',
            'cnh_categoria' => 'sometimes|string|max:5',
            'cnh_validade'  => 'sometimes|date',
            'turno_padrao'  => 'nullable|in:dia,noite',
            'status'        => 'sometimes|in:ativo,inativo,ferias,afastado',
            'observacoes'   => 'nullable|string',
        ];
    }
}
