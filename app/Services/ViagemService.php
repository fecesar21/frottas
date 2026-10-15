<?php

namespace App\Services;

use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViagemService
{
    public function __construct(
        private SolicitacaoService $solicitacaoService,
        private KmPlausibilidade $kmPlausibilidade,
    ) {}

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
        $veiculo->garantirForaDeManutencao();

        if ($data['km_saida'] < $veiculo->km_atual) {
            throw ValidationException::withMessages([
                'km_saida' => 'KM de saída menor que o KM atual do veículo.',
            ]);
        }

        $confirmacao = array_intersect_key($data, array_flip(['confirmar_km', 'justificativa_km']));
        unset($data['confirmar_km'], $data['justificativa_km']);
        if ($this->kmPlausibilidade->validarSaida($veiculo, (int) $data['km_saida'], $confirmacao)) {
            $data['km_suspeito'] = true;
            $data['justificativa_km'] = 'Saída: '.trim($confirmacao['justificativa_km']);
        }

        $data['saida_at'] = $data['saida_at'] ?? now();
        $data['status'] = 'em_andamento';

        $colaboradorIds = $data['colaborador_ids'] ?? [];
        unset($data['colaborador_ids']);

        return DB::transaction(function () use ($data, $colaboradorIds) {
            $viagem = Viagem::create($data);
            if ($colaboradorIds) {
                $viagem->colaboradores()->sync($colaboradorIds);
            }

            return $viagem;
        });
    }

    public function chegada(Viagem $viagem, array $data): Viagem
    {
        if ($data['km_chegada'] < $viagem->km_saida) {
            throw ValidationException::withMessages(['km_chegada' => 'KM de chegada menor que KM de saída.']);
        }

        $suspeito = $this->kmPlausibilidade->validarChegada($viagem, (int) $data['km_chegada'], $data);

        // Parada esquecida aberta é encerrada no momento da chegada.
        $viagem->paradas()->whereNull('saida_at')->update(['saida_at' => now()]);

        $viagem->update([
            ...($suspeito ? [
                'km_suspeito' => true,
                'justificativa_km' => trim(($viagem->justificativa_km ? $viagem->justificativa_km.' | ' : '').'Chegada: '.trim($data['justificativa_km'])),
            ] : []),
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
