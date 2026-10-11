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
            'nome' => 'sometimes|string|max:100',
            'cpf' => [Rule::requiredIf(fn () => $this->perfilEfetivo() !== 'dashboard' && ! $this->route('usuario')?->cpf), 'nullable', 'string', 'max:14', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'login' => [Rule::requiredIf(fn () => $this->perfilEfetivo() === 'dashboard' && ! $this->route('usuario')?->login), 'nullable', 'string', 'max:60', 'regex:/^(?=.*[a-z])[a-z0-9._-]+$/', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'email' => ['sometimes', 'email', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'perfil' => 'sometimes|in:admin,gestor,operador,dashboard',
            'ativo' => 'sometimes|boolean',
            'senha' => $this->perfilEfetivo() === 'dashboard'
                ? ['nullable', 'string', 'min:6']
                : ['nullable', 'string', 'min:6', 'regex:/^[0-9]+$/'],
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
            'unidade_id' => 'nullable|uuid|exists:unidades,id',
            'unidades' => 'nullable|array',
            'unidades.*' => 'uuid|exists:unidades,id',
        ];
    }

    private function perfilEfetivo(): ?string
    {
        return $this->input('perfil', $this->route('usuario')?->perfil);
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

        if ($this->login) {
            $this->merge(['login' => mb_strtolower(trim($this->login))]);
        }

        if ($this->cpf) {
            $this->merge(['cpf' => preg_replace('/\D/', '', $this->cpf)]);
        }
    }
}
