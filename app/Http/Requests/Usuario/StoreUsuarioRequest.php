<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome'         => 'required|string|max:100',
            'email'        => 'nullable|email|unique:usuarios',
            'senha'        => 'required|string|min:6',
            'perfil'       => 'required|in:admin,gestor,operador',
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
