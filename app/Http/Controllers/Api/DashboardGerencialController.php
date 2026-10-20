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
    /** Acordo fixo entre autorização da referência e início da viagem. */
    public const ACORDO_MINUTOS = 30;

    /** Máximo de pares origem/destino exibidos no painel. */
    public const LIMITE_ROTAS = 8;

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
        // solicitação). Autorização e horário da solicitação vêm da
        // solicitação vinculada, quando existir.
        $viagens = Viagem::with('solicitacao:id,viagem_id,unidade_id,origem_tipo,origem_id,autorizacao_referencia_em,created_at')
            ->where('motivo_viagem', 'transferencia_paciente')
            ->whereNull('viagem_ida_id') // retorno à origem não é transferência
            ->where('status', '!=', 'cancelada')
            ->whereBetween('saida_at', [$inicio, $fim])
            ->get(['id', 'origem', 'destino', 'saida_at', 'chegada_at']);

        // A unidade da transferência é a de origem: não dá para usar a do
        // motorista, que costuma atender todas as unidades. Com solicitação,
        // vale a origem (ou a unidade) dela; sem, o primeiro nome do texto de
        // origem comparado ao nome da unidade (ex.: "UPA, HRA" → UPA).
        if ($filtroUnidades) {
            $porNome = Unidade::pluck('id', 'nome')->mapWithKeys(fn ($id, $nome) => [mb_strtoupper(trim($nome)) => $id]);
            $unidadeDa = function (Viagem $v) use ($porNome) {
                $s = $v->solicitacao;
                if ($s) {
                    return $s->origem_tipo === 'unidade' && $s->origem_id ? $s->origem_id : $s->unidade_id;
                }

                return $porNome->get(mb_strtoupper(trim(explode(',', (string) $v->origem)[0])));
            };
            $viagens = $viagens->filter(fn ($v) => in_array($unidadeDa($v), $filtroUnidades, true))->values();
        }

        $minutos = fn ($de, $ate) => $de && $ate && $ate->gte($de) ? $de->diffInSeconds($ate) / 60 : null;
        $media = function ($valores) {
            $validos = collect($valores)->filter(fn ($v) => $v !== null);

            return $validos->isEmpty() ? null : round($validos->avg(), 1);
        };

        $tempos = $viagens->map(fn ($v) => [
            'autorizacao_inicio' => $minutos($v->solicitacao?->autorizacao_referencia_em, $v->saida_at),
            'autorizacao_fim' => $minutos($v->solicitacao?->autorizacao_referencia_em, $v->chegada_at),
            'autorizacao_solicitacao' => $minutos($v->solicitacao?->autorizacao_referencia_em, $v->solicitacao?->created_at),
            'solicitacao_inicio' => $minutos($v->solicitacao?->created_at, $v->saida_at),
            'inicio_fim' => $minutos($v->saida_at, $v->chegada_at),
        ]);

        // Quantidade por par origem → destino, das rotas mais frequentes.
        $porRota = $viagens
            ->groupBy(fn ($v) => ($v->origem ?: '—').'|'.($v->destino ?: '—'))
            ->map(function ($grupo, $chave) {
                [$origem, $destino] = explode('|', $chave, 2);

                return ['origem' => $origem, 'destino' => $destino, 'total' => $grupo->count()];
            })
            ->sortBy([['total', 'desc'], ['origem', 'asc'], ['destino', 'asc']])
            ->values()
            ->take(self::LIMITE_ROTAS);

        return response()->json([
            'mes' => $inicio->month,
            'ano' => $inicio->year,
            'acordo_minutos' => self::ACORDO_MINUTOS,
            'unidades' => $unidades,
            'unidade_id' => $unidadeId,
            'total_transferencias' => $viagens->count(),
            'medias_minutos' => [
                'autorizacao_inicio' => $media($tempos->pluck('autorizacao_inicio')),
                'autorizacao_fim' => $media($tempos->pluck('autorizacao_fim')),
                'autorizacao_solicitacao' => $media($tempos->pluck('autorizacao_solicitacao')),
                'solicitacao_inicio' => $media($tempos->pluck('solicitacao_inicio')),
                'inicio_fim' => $media($tempos->pluck('inicio_fim')),
            ],
            'por_rota' => $porRota,
            'concluidas_com_autorizacao' => $tempos->whereNotNull('autorizacao_inicio')->count(),
        ]);
    }
}
