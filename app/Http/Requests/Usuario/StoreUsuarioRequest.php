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
            'cpf'          => 'required|string|max:14|unique:usuarios',
            'email'        => 'nullable|email|unique:usuarios',
            'senha'        => 'required|string|min:6',
            'perfil'       => 'required|in:admin,gestor,operador',
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->input('perfil') === 'operador' && empty($this->input('motorista_id'))) {
                $v->errors()->add('motorista_id', 'O campo motorista é obrigatório para perfil operador.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->email) {
            $this->merge(['email' => strtolower($this->email)]);
        }
    }
}