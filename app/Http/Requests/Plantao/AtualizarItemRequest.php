<?php

namespace App\Http\Requests\Plantao;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_modelo_id' => 'required|integer|exists:checklist_itens_modelo,id',
            'resultado'      => 'required|in:ok,pendencia,nao_verificado',
            'observacao'     => 'nullable|string',
        ];
    }
}
