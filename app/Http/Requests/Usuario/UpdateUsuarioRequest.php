<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'         => 'sometimes|string|max:100',
            'email'        => ['sometimes', 'email', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'perfil'       => 'sometimes|in:admin,gestor,operador',
            'ativo'        => 'sometimes|boolean',
            'senha'        => 'nullable|string|min:6',
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->email) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }
}
