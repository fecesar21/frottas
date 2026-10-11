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
            'nome' => 'required|string|max:100',
            'cpf' => 'required_unless:perfil,dashboard|nullable|string|max:14|unique:usuarios',
            'login' => ['required_if:perfil,dashboard', 'nullable', 'string', 'max:60', 'regex:/^(?=.*[a-z])[a-z0-9._-]+$/', 'unique:usuarios'],
            'email' => 'nullable|email|unique:usuarios',
            'senha' => $this->input('perfil') === 'dashboard'
                ? ['required', 'string', 'min:6']
                : ['required', 'string', 'min:6', 'regex:/^[0-9]+$/'],
            'perfil' => 'required|in:admin,gestor,operador,dashboard',
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
            'unidade_id' => 'nullable|uuid|exists:unidades,id',
            'unidades' => 'nullable|array',
            'unidades.*' => 'uuid|exists:unidades,id',
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

        if ($this->login) {
            $this->merge(['login' => mb_strtolower(trim($this->login))]);
        }

        if ($this->cpf) {
            $this->merge(['cpf' => preg_replace('/\D/', '', $this->cpf)]);
        }
    }
}
