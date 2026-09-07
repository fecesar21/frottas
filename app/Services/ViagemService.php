<?php

namespace App\Services;

use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViagemService
{
    public function __construct(private SolicitacaoService $solicitacaoService) {}

    public function store(array $data): Viagem
    {
        $jaEmAndamento = Viagem::where('motorista_id', $data['motorista_id'])
            ->where('status', 'em_andamento')
            ->exists();

        if ($jaEmAndamento) {
            throw ValidationException::withMessages([
                'motorista_id' => 'Este motorista já possui uma viagem em andamento.',
            ]);
        }

        $veiculo = Veiculo::findOrFail($data['veiculo_id']);
        if ($data['km_saida'] < $veiculo->km_atual) {
            throw ValidationException::withMessages([
                'km_saida' => 'KM de saída menor que o KM atual do veículo.',
            ]);
        }

        $data['saida_at'] = $data['saida_at'] ?? now();
        $data['status'] = 'em_andamento';

        return DB::transaction(fn () => Viagem::create($data));
    }

    public function chegada(Viagem $viagem, array $data): Viagem
    {
        if ($data['km_chegada'] < $viagem->km_saida) {
            throw ValidationException::withMessages(['km_chegada' => 'KM de chegada menor que KM de saída.']);
        }

        $viagem->update([
            'status' => 'concluida',
            'chegada_at' => now(),
            'km_chegada' => $data['km_chegada'],
            'observacoes' => $data['observacoes'] ?? $viagem->observacoes,
        ]);

        $viagem->solicitacao?->update(['status' => 'finalizado']);

        $this->solicitacaoService->processarPendentePara($viagem->motorista_id, $viagem->km_chegada);

        return $viagem->fresh();
    }
}
