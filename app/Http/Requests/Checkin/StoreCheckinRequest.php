<?php

namespace App\Http\Requests\Checkin;

use App\Models\Motorista;
use Illuminate\Foundation\Http\FormRequest;

class StoreCheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motorista_id' => 'nullable|uuid|exists:motoristas,id',
            'veiculo_id' => 'required|uuid|exists:veiculos,id',
            'turno' => 'required|in:dia,noite',
            'km_saida' => 'required|integer|min:0',
            'nivel_combustivel_saida' => 'nullable|numeric|min:0|max:100',
            'escala_id' => 'nullable|uuid|exists:escalas,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $user = auth()->user();
        if ($user?->perfil === 'operador' && $user->motorista_id) {
            $this->merge(['motorista_id' => $user->motorista_id]);
        }

        // O turno vem do cadastro do motorista: trocar de turno exigiria refazer o checklist.
        $turnoPadrao = Motorista::whereKey($this->input('motorista_id'))->value('turno_padrao');
        if ($turnoPadrao) {
            $this->merge(['turno' => $turnoPadrao]);
        }
    }
}
