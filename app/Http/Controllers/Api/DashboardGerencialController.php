<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solicitacao;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard Gerencial (perfil "dashboard", exibido em modo kiosk):
 * indicadores de tempo das transferências de paciente no mês.
 */
class DashboardGerencialController extends Controller
{
    /** Acordo fixo entre autorização da referência e fim da transferência. */
    public const ACORDO_MINUTOS = 30;

    public function transferencias(Request $r): JsonResponse
    {
        abort_unless(in_array($r->user()->perfil, ['admin', 'gestor', 'dashboard'], true), 403);

        $r->validate([
            'mes' => 'nullable|integer|between:1,12',
            'ano' => 'nullable|integer|between:2000,2100',
        ]);

        $inicio = Carbon::create($r->integer('ano', now()->year), $r->integer('mes', now()->month), 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        $solicitacoes = Solicitacao::with('viagem:id,saida_at,chegada_at')
            ->where('motivo', 'transferencia_paciente')
            ->whereNotIn('status', ['cancelado', 'recusada'])
            ->whereBetween('created_at', [$inicio, $fim])
            ->get(['id', 'viagem_id', 'autorizacao_referencia_em', 'created_at']);

        $minutos = fn ($de, $ate) => $de && $ate && $ate->gte($de) ? $de->diffInSeconds($ate) / 60 : null;
        $media = function ($valores) {
            $validos = collect($valores)->filter(fn ($v) => $v !== null);

            return $validos->isEmpty() ? null : round($validos->avg(), 1);
        };

        $tempos = $solicitacoes->map(fn ($s) => [
            'autorizacao_fim' => $minutos($s->autorizacao_referencia_em, $s->viagem?->chegada_at),
            'autorizacao_solicitacao' => $minutos($s->autorizacao_referencia_em, $s->created_at),
            'solicitacao_inicio' => $minutos($s->created_at, $s->viagem?->saida_at),
            'inicio_fim' => $minutos($s->viagem?->saida_at, $s->viagem?->chegada_at),
        ]);

        return response()->json([
            'mes' => $inicio->month,
            'ano' => $inicio->year,
            'acordo_minutos' => self::ACORDO_MINUTOS,
            'total_transferencias' => $solicitacoes->count(),
            'medias_minutos' => [
                'autorizacao_fim' => $media($tempos->pluck('autorizacao_fim')),
                'autorizacao_solicitacao' => $media($tempos->pluck('autorizacao_solicitacao')),
                'solicitacao_inicio' => $media($tempos->pluck('solicitacao_inicio')),
                'inicio_fim' => $media($tempos->pluck('inicio_fim')),
            ],
            'concluidas_com_autorizacao' => $tempos->whereNotNull('autorizacao_fim')->count(),
        ]);
    }
}
