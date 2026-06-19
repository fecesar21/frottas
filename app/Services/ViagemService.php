<?php

namespace App\Services;

use App\Models\Viagem;
use Illuminate\Validation\ValidationException;

class ViagemService
{
    public function store(array $data): Viagem
    {
        $data['saida_at'] = $data['saida_at'] ?? now();
        $data['status']   = 'em_andamento';

        return Viagem::create($data);
    }

    public function chegada(Viagem $viagem, array $data): Viagem
    {
        if ($data['km_chegada'] < $viagem->km_saida) {
            throw ValidationException::withMessages(['km_chegada' => 'KM de chegada menor que KM de saída.']);
        }

        $viagem->update([
            'status'      => 'concluida',
            'chegada_at'  => now(),
            'km_chegada'  => $data['km_chegada'],
            'observacoes' => $data['observacoes'] ?? $viagem->observacoes,
        ]);

        return $viagem->fresh();
    }
}
