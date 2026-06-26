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
            'cpf'          => ['sometimes', 'string', 'max:14', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'email'        => ['sometimes', 'email', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'perfil'       => 'sometimes|in:admin,gestor,operador',
            'ativo'        => 'sometimes|boolean',
            'senha'        => ['nullable', 'string', 'min:6', 'regex:/^[0-9]+$/'],
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $usuario = $this->route('usuario');
            $perfilEfetivo = $this->input('perfil', $usuario?->perfil);

            if ($perfilEfetivo === 'operador') {
                $novoMotoristaId = array_key_exists('motorista_id', $this->all())
                    ? $this->input('motorista_id')
                    : $usuario?->motorista_id;

                if (empty($novoMotoristaId)) {
                    $v->errors()->add('motorista_id', 'O campo motorista é obrigatório para perfil operador.');
                }
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
