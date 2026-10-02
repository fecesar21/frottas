<?php

namespace App\Services;

use App\Models\Checkin;
use App\Models\KmRegistro;
use App\Models\Motorista;
use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckinService
{
    public function store(array $data): Checkin
    {
        $motorista = Motorista::findOrFail($data['motorista_id']);
        $ativos = Checkin::where('motorista_id', $motorista->id)->where('status', 'ativo')->count();

        if ($ativos >= $motorista->limiteCheckinsAtivos()) {
            throw ValidationException::withMessages([
                'motorista_id' => $ativos > 1
                    ? 'Motorista já possui 2 check-ins ativos.'
                    : 'Motorista já possui check-in ativo.',
            ]);
        }

        if (Checkin::where('veiculo_id', $data['veiculo_id'])->where('status', 'ativo')->exists()) {
            throw ValidationException::withMessages(['veiculo_id' => 'Veículo já está em uso por outro motorista.']);
        }

        $veiculo = Veiculo::findOrFail($data['veiculo_id']);
        $veiculo->garantirForaDeManutencao();

        if ($data['km_saida'] < $veiculo->km_atual) {
            throw ValidationException::withMessages(['km_saida' => 'KM de saída menor que KM atual do veículo.']);
        }

        return DB::transaction(function () use ($data, $veiculo) {
            $data['checkin_at'] = now();
            $checkin = Checkin::create($data);

            $kmAnterior = $veiculo->km_atual;
            $veiculo->update(['status' => 'em_uso', 'km_atual' => $data['km_saida']]);

            KmRegistro::create([
                'veiculo_id' => $veiculo->id,
                'motorista_id' => $data['motorista_id'],
                'checkin_id' => $checkin->id,
                'km_anterior' => $kmAnterior,
                'km_atual' => $data['km_saida'],
                'observacao' => 'Check-in',
                'registrado_at' => now(),
            ]);

            return $checkin->load(['motorista', 'veiculo']);
        });
    }

    public function checkout(Checkin $checkin, array $data, bool $iniciadoPeloOperador = false): Checkin
    {
        if ($checkin->status !== 'ativo') {
            throw ValidationException::withMessages(['checkin' => 'Check-in não está ativo.']);
        }

        if (isset($data['km_retorno']) && $data['km_retorno'] < $checkin->km_saida) {
            throw ValidationException::withMessages(['km_retorno' => 'KM de retorno menor que KM de saída.']);
        }

        if (isset($data['km_retorno']) && ($kmFixo = $checkin->kmRetornoSemViagem()) !== null && (int) $data['km_retorno'] !== $kmFixo) {
            throw ValidationException::withMessages([
                'km_retorno' => "Nenhuma viagem registrada após o check-in: o KM de retorno deve ser igual a {$kmFixo}.",
            ]);
        }

        // Com viagens no período, KM diferente do esperado é aceito, mas exige
        // justificativa e fica registrado para a gestão.
        $kmEsperado = isset($data['km_retorno']) ? $checkin->kmRetornoEsperado() : null;
        $divergencia = $kmEsperado !== null ? (int) $data['km_retorno'] - $kmEsperado : null;
        $justificativa = trim((string) ($data['justificativa_divergencia_km'] ?? ''));

        if ($divergencia && $justificativa === '') {
            throw ValidationException::withMessages([
                'justificativa_divergencia_km' => "KM de retorno diferente do esperado ({$kmEsperado}: KM de saída + viagens registradas). Informe a justificativa.",
            ]);
        }

        if ($iniciadoPeloOperador) {
            // Só bloqueia por viagem feita com o veículo deste check-in: quem tem
            // check-in duplo pode liberar um carro enquanto viaja com o outro.
            $viagemEmAndamento = Viagem::where('motorista_id', $checkin->motorista_id)
                ->where('veiculo_id', $checkin->veiculo_id)
                ->where('status', 'em_andamento')
                ->exists();

            if ($viagemEmAndamento) {
                throw ValidationException::withMessages([
                    'checkin' => 'Para realizar o Check-out é necessário encerrar qualquer viagem que consta em andamento antes',
                ]);
            }
        }

        return DB::transaction(function () use ($data, $checkin, $kmEsperado, $divergencia, $justificativa) {
            $checkin->update([
                'status' => 'encerrado',
                'checkout_at' => now(),
                'km_retorno' => $data['km_retorno'] ?? null,
                'km_retorno_esperado' => $kmEsperado,
                'divergencia_km' => $divergencia,
                'justificativa_divergencia_km' => $divergencia ? $justificativa : null,
                'nivel_combustivel_retorno' => $data['nivel_combustivel_retorno'] ?? null,
                'ocorrencias' => $data['ocorrencias'] ?? null,
            ]);

            $veiculo = Veiculo::find($checkin->veiculo_id);
            // Em manutenção o veículo continua indisponível mesmo após o check-out.
            $veiculo->update([
                'status' => $veiculo->status === 'manutencao' ? 'manutencao' : 'disponivel',
                'km_atual' => $data['km_retorno'] ?? $veiculo->km_atual,
            ]);

            if (! empty($data['km_retorno'])) {
                KmRegistro::create([
                    'veiculo_id' => $checkin->veiculo_id,
                    'motorista_id' => $checkin->motorista_id,
                    'checkin_id' => $checkin->id,
                    'km_anterior' => $checkin->km_saida,
                    'km_atual' => $data['km_retorno'],
                    'observacao' => 'Check-out',
                    'registrado_at' => now(),
                ]);
            }

            return $checkin->fresh()->load(['motorista', 'veiculo']);
        });
    }
}
