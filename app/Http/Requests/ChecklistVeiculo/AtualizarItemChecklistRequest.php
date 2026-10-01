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
            'conforme' => 'present|nullable|boolean',
            'observacao' => 'nullable|string',
            'valor' => 'nullable|integer',
            'fotos' => 'nullable|array|max:3',
            'fotos.*' => 'file|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'fotos.max' => 'Anexe no máximo 3 fotos por item.',
            'fotos.*.uploaded' => 'Não foi possível enviar a foto. Tente uma imagem menor.',
            'fotos.*.max' => 'Cada foto deve ter no máximo 5 MB.',
            'fotos.*.mimes' => 'A foto deve ser JPG, PNG ou WEBP.',
        ];
    }
}
