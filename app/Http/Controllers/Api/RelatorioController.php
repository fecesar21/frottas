<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    // ── DASHBOARD (KPIs gerais) ──────────────────────────────────
    public function dashboard(Request $r)
    {
        $mes = now()->format('Y-m');

        $veiculos = DB::table('veiculos')->selectRaw("
            COUNT(*) as total,
            SUM(status='disponivel') as disponiveis,
            SUM(status='em_uso') as em_uso,
            SUM(status='manutencao') as manutencao,
            SUM(status='inativo') as inativos
        ")->first();

        $checkins_ativos = DB::table('checkins')
            ->whereNull('checkout_at')->count();

        $km_mes = DB::table('viagens')
            ->whereNotNull('km_chegada')
            ->whereRaw("DATE_FORMAT(saida_at,'%Y-%m') = ?", [$mes])
            ->selectRaw('SUM(km_chegada - km_saida) as total')
            ->value('total') ?? 0;

        $custo_mes = DB::table('abastecimentos')
            ->whereRaw("DATE_FORMAT(COALESCE(abastecido_at, created_at),'%Y-%m') = ?", [$mes])
            ->sum('valor_total');

        $motoristas = DB::table('motoristas')
            ->selectRaw("COUNT(*) as total, SUM(status='ativo') as ativos")
            ->first();

        $cnh_vencendo = DB::table('motoristas')
            ->where('status', 'ativo')
            ->whereBetween('cnh_validade', [now(), now()->addDays(30)])
            ->pluck('nome');

        return response()->json(compact(
            'veiculos', 'checkins_ativos', 'km_mes',
            'custo_combustivel_mes', 'motoristas', 'cnh_vencendo'
        ) + ['custo_combustivel_mes' => $custo_mes]);
    }

    // ── RELATÓRIO: ABASTECIMENTOS ─────────────────────────────────
    public function abastecimentos(Request $r)
    {
        $de  = $r->de  ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('abastecimentos as a')
            ->leftJoin('veiculos as v',   'a.veiculo_id',   '=', 'v.id')
            ->leftJoin('motoristas as m', 'a.motorista_id', '=', 'm.id')
            ->whereRaw("DATE(COALESCE(a.abastecido_at, a.created_at)) BETWEEN ? AND ?", [$de, $ate])
            ->orderByRaw("COALESCE(a.abastecido_at, a.created_at) DESC")
            ->select(
                'a.id',
                DB::raw("COALESCE(a.abastecido_at, a.created_at) as data"),
                'v.placa', 'v.modelo',
                'm.nome as motorista_nome',
                'a.posto', 'a.combustivel',
                'a.litros', 'a.valor_litro', 'a.valor_total', 'a.km_momento'
            )
            ->get();

        // Totalizadores
        $totais = [
            'total_litros' => $rows->sum('litros'),
            'total_valor'  => $rows->sum('valor_total'),
            'preco_medio'  => $rows->sum('litros') > 0
                ? round($rows->sum('valor_total') / $rows->sum('litros'), 3)
                : 0,
            'total_registros' => $rows->count(),
        ];

        // Por veículo
        $por_veiculo = $rows->groupBy('placa')->map(fn($g) => [
            'placa'        => $g->first()->placa,
            'modelo'       => $g->first()->modelo,
            'total_litros' => round($g->sum('litros'), 2),
            'total_valor'  => round($g->sum('valor_total'), 2),
            'abastecimentos' => $g->count(),
        ])->values();

        return response()->json(compact('rows', 'totais', 'por_veiculo'));
    }

    // ── RELATÓRIO: VIAGENS ────────────────────────────────────────
    public function viagens(Request $r)
    {
        $de  = $r->de  ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('viagens as vg')
            ->leftJoin('veiculos as v',   'vg.veiculo_id',   '=', 'v.id')
            ->leftJoin('motoristas as m', 'vg.motorista_id', '=', 'm.id')
            ->whereRaw("DATE(vg.saida_at) BETWEEN ? AND ?", [$de, $ate])
            ->orderBy('vg.saida_at', 'desc')
            ->select(
                'vg.id', 'vg.saida_at', 'vg.chegada_at',
                'v.placa', 'v.modelo',
                'm.nome as motorista_nome',
                'vg.origem', 'vg.destino',
                'vg.km_saida', 'vg.km_chegada', 'vg.status',
                DB::raw("IF(vg.km_chegada IS NOT NULL, vg.km_chegada - vg.km_saida, NULL) as km_percorrido"),
                DB::raw("IF(vg.chegada_at IS NOT NULL AND vg.saida_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, vg.saida_at, vg.chegada_at), NULL) as duracao_min")
            )
            ->get();

        $totais = [
            'total_viagens'     => $rows->count(),
            'viagens_concluidas'=> $rows->where('status','concluida')->count(),
            'km_total'          => $rows->sum('km_percorrido'),
            'duracao_media_min' => $rows->whereNotNull('duracao_min')->avg('duracao_min'),
        ];

        $por_motorista = $rows->groupBy('motorista_nome')->map(fn($g) => [
            'motorista' => $g->first()->motorista_nome ?? '—',
            'viagens'   => $g->count(),
            'km_total'  => $g->sum('km_percorrido'),
        ])->values()->sortByDesc('km_total')->values();

        return response()->json(compact('rows', 'totais', 'por_motorista'));
    }

    // ── RELATÓRIO: PASSAGENS DE PLANTÃO ──────────────────────────
    public function plantao(Request $r)
    {
        $de  = $r->de  ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('passagens_plantao as pp')
            ->leftJoin('veiculos as v',          'pp.veiculo_id',            '=', 'v.id')
            ->leftJoin('motoristas as ms',        'pp.motorista_saindo_id',   '=', 'ms.id')
            ->leftJoin('motoristas as me',        'pp.motorista_entrando_id', '=', 'me.id')
            ->whereRaw("DATE(pp.created_at) BETWEEN ? AND ?", [$de, $ate])
            ->orderBy('pp.created_at', 'desc')
            ->select(
                'pp.id', 'pp.created_at', 'pp.finalizado_at', 'pp.data_encerramento',
                'pp.turno_saindo', 'pp.turno_entrando',
                'pp.km_momento', 'pp.nivel_combustivel',
                'pp.observacoes_gerais',
                'v.placa', 'v.modelo',
                'ms.nome as motorista_saindo',
                'me.nome as motorista_entrando',
                DB::raw("IF(pp.data_encerramento IS NOT NULL AND pp.created_at IS NOT NULL,
                    TIMESTAMPDIFF(MINUTE, pp.created_at, pp.data_encerramento), NULL) as duracao_min")
            )
            ->get();

        // Conta itens de checklist por passagem
        $checklist = DB::table('checklist_respostas')
            ->select('passagem_id',
                DB::raw("SUM(resultado='ok') as ok"),
                DB::raw("SUM(resultado='pendencia') as pendencias")
            )
            ->groupBy('passagem_id')
            ->get()->keyBy('passagem_id');

        $rows = $rows->map(function($p) use ($checklist) {
            $cl = $checklist[$p->id] ?? null;
            $p->itens_ok       = $cl?->ok ?? 0;
            $p->itens_pendencia= $cl?->pendencias ?? 0;
            return $p;
        });

        $totais = [
            'total'              => $rows->count(),
            'encerrados'         => $rows->whereNotNull('data_encerramento')->count(),
            'com_pendencia'      => $rows->where('itens_pendencia', '>', 0)->count(),
            'duracao_media_min'  => $rows->whereNotNull('duracao_min')->avg('duracao_min'),
        ];

        return response()->json(compact('rows', 'totais'));
    }

    // ── RELATÓRIO: MOTORISTAS ─────────────────────────────────────
    public function motoristas(Request $r)
    {
        $rows = DB::table('motoristas as m')
            ->leftJoin('viagens as vg',            'm.id', '=', 'vg.motorista_id')
            ->leftJoin('abastecimentos as ab',     'm.id', '=', 'ab.motorista_id')
            ->leftJoin('passagens_plantao as pp',  'm.id', '=', 'pp.motorista_entrando_id')
            ->where('m.status', '!=', 'inativo')
            ->groupBy('m.id','m.nome','m.cnh_numero','m.cnh_categoria','m.cnh_validade','m.turno_padrao','m.status')
            ->select(
                'm.id', 'm.nome', 'm.cnh_numero', 'm.cnh_categoria',
                'm.cnh_validade', 'm.turno_padrao', 'm.status',
                DB::raw("COUNT(DISTINCT vg.id) as total_viagens"),
                DB::raw("SUM(IF(vg.km_chegada IS NOT NULL, vg.km_chegada - vg.km_saida, 0)) as km_total"),
                DB::raw("COUNT(DISTINCT ab.id) as total_abastecimentos"),
                DB::raw("COUNT(DISTINCT pp.id) as total_plantoes"),
                DB::raw("IF(m.cnh_validade < CURDATE(), 'vencida', IF(m.cnh_validade < DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'vencendo', 'ok')) as cnh_status")
            )
            ->orderBy('m.nome')
            ->get();

        return response()->json($rows);
    }

    // ── RELATÓRIO: CHECKINS (para dashboard) ─────────────────────
    public function checkins(Request $r)
    {
        $rows = DB::table('checkins as c')
            ->leftJoin('veiculos as v',   'c.veiculo_id',   '=', 'v.id')
            ->leftJoin('motoristas as m', 'c.motorista_id', '=', 'm.id')
            ->orderBy('c.checkin_at', 'desc')
            ->limit(50)
            ->select('c.*', 'v.placa as veiculo_placa', 'v.modelo as veiculo_modelo', 'm.nome as motorista_nome')
            ->get();
        return response()->json($rows);
    }
}
