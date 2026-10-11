<?php

namespace App\Services;

use App\Models\Veiculo;
use App\Models\Viagem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

/**
 * Detecta KM implausível (dígito a mais, dígito inicial trocado) comparando
 * com o último KM conhecido do veículo ou com o KM de saída da viagem.
 *
 * - Bloqueio: valor rejeitado (422).
 * - Alerta: 422 com `km_confirmacao_necessaria`, até o motorista reenviar
 *   com `confirmar_km` + `justificativa_km`; a viagem fica `km_suspeito`.
 *
 * Retorna true quando o valor foi aceito sob alerta (suspeito).
 */
class KmPlausibilidade
{
    public function validarSaida(Veiculo $veiculo, int $km, array $confirmacao = [], string $campo = 'km_saida'): bool
    {
        $base = $veiculo->ultimoKmConhecido();
        $salto = $km - $base;

        return $this->avaliar($campo, $km, $base, $salto,
            alerta: config('frota.km.salto_saida_alerta'),
            bloqueio: config('frota.km.salto_saida_bloqueio'),
            descricao: 'acima do último KM registrado do veículo',
            confirmacao: $confirmacao,
        );
    }

    public function validarChegada(Viagem $viagem, int $km, array $confirmacao = [], string $campo = 'km_chegada'): bool
    {
        $base = (int) $viagem->km_saida;
        $distancia = $km - $base;

        $horas = $viagem->saida_at ? max(0, $viagem->saida_at->diffInMinutes(now())) / 60 : null;
        $alerta = config('frota.km.distancia_viagem_alerta');
        if ($horas !== null) {
            $alerta = min($alerta, (int) ceil($horas * config('frota.km.velocidade_max_kmh')) + config('frota.km.folga_km'));
        }

        return $this->avaliar($campo, $km, $base, $distancia,
            alerta: $alerta,
            bloqueio: config('frota.km.distancia_viagem_bloqueio'),
            descricao: 'de distância nesta viagem',
            confirmacao: $confirmacao,
        );
    }

    private function avaliar(string $campo, int $km, int $base, int $delta, int $alerta, int $bloqueio, string $descricao, array $confirmacao): bool
    {
        if ($delta <= $alerta) {
            return false;
        }

        $fmt = fn (int $n) => number_format($n, 0, ',', '.');

        if ($base >= 1000 && $km >= $base * 5) {
            throw ValidationException::withMessages([
                $campo => "KM {$fmt($km)} parece ter um dígito a mais. Último KM: {$fmt($base)}. Confira o odômetro.",
            ]);
        }

        if ($delta > $bloqueio) {
            throw ValidationException::withMessages([
                $campo => "KM {$fmt($km)} está {$fmt($delta)} km {$descricao} ({$fmt($base)}). Confira se algum dígito foi trocado.",
            ]);
        }

        $justificativa = trim((string) ($confirmacao['justificativa_km'] ?? ''));
        if (! empty($confirmacao['confirmar_km']) && mb_strlen($justificativa) >= 5) {
            return true;
        }

        $mensagem = "KM {$fmt($km)} está {$fmt($delta)} km {$descricao} ({$fmt($base)}). Confirme o valor e informe uma justificativa.";

        throw new HttpResponseException(response()->json([
            'message' => $mensagem,
            'errors' => [$campo => [$mensagem]],
            'km_confirmacao_necessaria' => true,
            'km_referencia' => $base,
            'km_delta' => $delta,
        ], 422));
    }
}
