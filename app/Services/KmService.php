<?php

namespace App\Services;

use App\Models\KmRegistro;
use App\Models\Veiculo;
use Illuminate\Validation\ValidationException;

class KmService
{
    public function store(array $data): KmRegistro
    {
        $veiculo = Veiculo::findOrFail($data['veiculo_id']);

        if ($data['km_atual'] < $veiculo->km_atual) {
            throw ValidationException::withMessages(['km_atual' => 'KM não pode ser menor que o KM atual do veículo.']);
        }

        $registro = KmRegistro::create([
            'veiculo_id'    => $data['veiculo_id'],
            'motorista_id'  => $data['motorista_id'] ?? null,
            'km_anterior'   => $veiculo->km_atual,
            'km_atual'      => $data['km_atual'],
            'observacao'    => $data['observacao'] ?? null,
            'registrado_at' => now(),
        ]);

        $veiculo->update(['km_atual' => $data['km_atual']]);

        return $registro;
    }
}
