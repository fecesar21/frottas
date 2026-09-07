<?php

namespace App\Http\Requests\ChecklistVeiculo;

use Illuminate\Foundation\Http\FormRequest;

class AtualizarItemChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_modelo_id' => 'required|integer|exists:checklist_veiculo_itens_modelo,id',
            'conforme' => 'required|boolean',
            'observacao' => 'nullable|string',
            'valor' => 'nullable|integer',
            'foto' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
        ];
    }
}
