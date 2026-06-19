<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\KmRegistro;
use App\Models\Veiculo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckinService
{
    public function store(array $data): Checkin
    {
        if (Checkin::where('motorista_id', $data['motorista_id'])->where('status', 'ativo')->exists()) {
            throw ValidationException::withMessages(['motorista_id' => 'Motorista já possui check-in ativo.']);
        }

        if (Checkin::where('veiculo_id', $data['veiculo_id'])->where('status', 'ativo')->exists()) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo já está em uso por outro motorista.']);
        }

        $veiculo = Veiculo::findOrFail($data['veiculo_id']);

        if ($data['km_saida'] < $veiculo->km_atual) {
            throw ValidationException::withMessages(['km_saida' => 'KM de saída menor que KM atual do veículo.']);
        }

        return DB::transaction(function () use ($data, $veiculo) {
            $data['checkin_at'] = now();
            $checkin = Checkin::create($data);

            $veiculo->update(['status' => 'em_uso', 'km_atual' => $data['km_saida']]);

            KmRegistro::create([
                'veiculo_id'    => $veiculo->id,
                'motorista_id'  => $data['motorista_id'],
                'checkin_id'    => $checkin->id,
                'km_anterior'   => $veiculo->km_atual,
                'km_atual'      => $data['km_saida'],
                'observacao'    => 'Check-in',
                'registrado_at' => now(),
            ]);

            return $checkin->load(['motorista', 'veiculo']);
        });
    }

    public function checkout(Checkin $checkin, array $data): Checkin
    {
        if ($checkin->status !== 'ativo') {
            throw ValidationException::withMessages(['checkin' => 'Check-in não está ativo.']);
        }

        if (isset($data['km_retorno']) && $data['km_retorno'] < $checkin->km_saida) {
            throw ValidationException::withMessages(['km_retorno' => 'KM de retorno menor que KM de saída.']);
        }

        return DB::transaction(function () use ($data, $checkin) {
            $checkin->update([
                'status'                    => 'encerrado',
                'checkout_at'               => now(),
                'km_retorno'                => $data['km_retorno'] ?? null,
                'nivel_combustivel_retorno' => $data['nivel_combustivel_retorno'] ?? null,
                'ocorrencias'               => $data['ocorrencias'] ?? null,
            ]);

            $veiculo = Veiculo::find($checkin->veiculo_id);
            $veiculo->update([
                'status'   => 'disponivel',
                'km_atual' => $data['km_retorno'] ?? $veiculo->km_atual,
            ]);

            if (!empty($data['km_retorno'])) {
                KmRegistro::create([
                    'veiculo_id'    => $checkin->veiculo_id,
                    'motorista_id'  => $checkin->motorista_id,
                    'checkin_id'    => $checkin->id,
                    'km_anterior'   => $checkin->km_saida,
                    'km_atual'      => $data['km_retorno'],
                    'observacao'    => 'Check-out',
                    'registrado_at' => now(),
                ]);
            }

            return $checkin->fresh()->load(['motorista', 'veiculo']);
        });
    }
}
