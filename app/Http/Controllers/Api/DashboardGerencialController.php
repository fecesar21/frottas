<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unidade;
use App\Models\Viagem;
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
            'unidade_id' => 'nullable|uuid',
        ]);

        // Perfil "dashboard" vê só as unidades vinculadas a ele (nenhuma
        // vinculada = todas); admin e gestor veem todas.
        $user = $r->user();
        $vinculadas = $user->perfil === 'dashboard' ? $user->unidadesDashboard()->pluck('unidades.id') : collect();
        $unidades = Unidade::query()
            ->when($vinculadas->isNotEmpty(), fn ($q) => $q->whereIn('id', $vinculadas))
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $unidadeId = $r->query('unidade_id');
        abort_if($unidadeId && ! $unidades->contains('id', $unidadeId), 403, 'Unidade não permitida para este usuário.');
        $filtroUnidades = $unidadeId ? [$unidadeId] : ($vinculadas->isNotEmpty() ? $vinculadas->all() : null);

        $inicio = Carbon::create($r->integer('ano', now()->year), $r->integer('mes', now()->month), 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth();

        // Base: viagens de transferência (muitas são lançadas direto, sem
        // solicitação). A unidade da viagem é a do motorista, como na lista
        // de viagens. Autorização e horário da solicitação vêm da solicitação
        // vinculada, quando existir.
        $viagens = Viagem::with('solicitacao:id,viagem_id,autorizacao_referencia_em,created_at')
            ->where('motivo_viagem', 'transferencia_paciente')
            ->where('status', '!=', 'cancelada')
            ->whereBetween('saida_at', [$inicio, $fim])
            ->when($filtroUnidades, fn ($q) => $q->whereHas('motorista.unidades', fn ($u) => $u->whereIn('unidades.id', $filtroUnidades)))
            ->get(['id', 'saida_at', 'chegada_at']);

        $minutos = fn ($de, $ate) => $de && $ate && $ate->gte($de) ? $de->diffInSeconds($ate) / 60 : null;
        $media = function ($valores) {
            $validos = collect($valores)->filter(fn ($v) => $v !== null);

            return $validos->isEmpty() ? null : round($validos->avg(), 1);
        };

        $tempos = $viagens->map(fn ($v) => [
            'autorizacao_fim' => $minutos($v->solicitacao?->autorizacao_referencia_em, $v->chegada_at),
            'autorizacao_solicitacao' => $minutos($v->solicitacao?->autorizacao_referencia_em, $v->solicitacao?->created_at),
            'solicitacao_inicio' => $minutos($v->solicitacao?->created_at, $v->saida_at),
            'inicio_fim' => $minutos($v->saida_at, $v->chegada_at),
        ]);

        return response()->json([
            'mes' => $inicio->month,
            'ano' => $inicio->year,
            'acordo_minutos' => self::ACORDO_MINUTOS,
            'unidades' => $unidades,
            'unidade_id' => $unidadeId,
            'total_transferencias' => $viagens->count(),
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
