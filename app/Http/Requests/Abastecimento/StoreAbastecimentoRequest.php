<?php

namespace App\Http\Requests\Abastecimento;

use App\Models\Motorista;
use Illuminate\Foundation\Http\FormRequest;

class StoreAbastecimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara os dados antes de rodar a validação.
     * Isso garante que se o usuário for 'operador', o motorista_id e veiculo_id
     * serão injetados aqui dentro antes que a regra 'required' falhe.
     */
    protected function prepareForValidation()
    {
        $usuarioLogado = auth()->user();

        if ($usuarioLogado && $usuarioLogado->perfil === 'operador') {
            // Busca o motorista e o check-in ativo dele
            $motorista = Motorista::with('checkinAtivo')->find($usuarioLogado->motorista_id);
            $checkin = $motorista?->checkinAtivo;

            if ($checkin) {
                $this->merge([
                    'motorista_id' => $usuarioLogado->motorista_id,
                    'veiculo_id'   => $checkin->veiculo_id,
                    'checkin_id'   => $checkin->id, // Já aproveita e vincula o ID do check-in
                ]);
            }
        } elseif ($usuarioLogado && $usuarioLogado->motorista_id && !$this->has('motorista_id')) {
            // Se for outro perfil que tenha motorista vinculado e não enviou no formulário
            $this->merge([
                'motorista_id' => $usuarioLogado->motorista_id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'veiculo_id'   => 'required|uuid|exists:veiculos,id',
            'motorista_id' => 'required|uuid|exists:motoristas,id',
            'checkin_id'   => 'nullable|uuid|exists:checkins,id',
            'posto'        => 'nullable|string|max:100',
            'combustivel'  => 'required|in:diesel_s10,diesel_s500,gasolina,gasolina_aditivada,etanol',
            'litros'       => 'required|numeric|min:0.001',
            'valor_litro'  => 'required|numeric|min:0.001',
            'km_momento'   => 'required|integer|min:0',
            'nota_fiscal'  => 'nullable|string|max:60',
            'observacoes'  => 'nullable|string',
        ];
    }
}