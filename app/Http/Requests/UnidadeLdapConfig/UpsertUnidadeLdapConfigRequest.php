<?php

namespace App\Http\Requests\UnidadeLdapConfig;

use Illuminate\Foundation\Http\FormRequest;

class UpsertUnidadeLdapConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'base_dn' => 'required|string|max:500',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
            'use_ssl' => 'boolean',
            'use_starttls' => 'boolean',
            'unidade_attribute' => 'required|string|max:100',
            'valores_ad' => 'required|array|min:1',
            'valores_ad.*' => 'required|string|max:255',
            'ativo' => 'boolean',
        ];
    }
}
