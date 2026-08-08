<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RelatorioController extends Controller
{
    // ── DASHBOARD (KPIs gerais) ──────────────────────────────────
    public function dashboard(Request $r)
    {
        $dados = Cache::remember('relatorio.dashboard', now()->addMinutes(5), function () {
            $inicio = now()->startOfMonth();
            $fim = now()->endOfMonth();

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
                ->whereBetween('saida_at', [$inicio, $fim])
                ->selectRaw('SUM(km_chegada - km_saida) as total')
                ->value('total') ?? 0;

            $custo_mes = DB::table('abastecimentos')
                ->whereBetween(DB::raw('COALESCE(abastecido_at, created_at)'), [$inicio, $fim])
                ->selectRaw('SUM(litros * valor_litro) as total')
                ->value('total') ?? 0;

            $motoristas = DB::table('motoristas')
                ->selectRaw("COUNT(*) as total, SUM(status='ativo') as ativos")
                ->first();

            $cnh_vencendo = DB::table('motoristas')
                ->where('status', 'ativo')
                ->whereBetween('cnh_validade', [now(), now()->addDays(30)])
                ->pluck('nome');

            return [
                'veiculos' => $veiculos,
                'checkins_ativos' => $checkins_ativos,
                'km_mes' => $km_mes,
                'custo_combustivel_mes' => $custo_mes,
                'motoristas' => $motoristas,
                'cnh_vencendo' => $cnh_vencendo,
            ];
        });

        return response()->json($dados);
    }

    // ── DASHBOARD (gráficos interativos) ─────────────────────────
    // Todas as agregações por mês/duração são feitas em PHP com Carbon,
    // nunca com funções SQL específicas de motor (strftime, DATE_FORMAT,
    // TIMESTAMPDIFF, IF(), CURDATE()), para permanecer portátil entre
    // SQLite (banco real do projeto) e MySQL. Ver bug conhecido em
    // viagensData()/motoristasData() acima.
    public function dashboardGraficos(Request $r)
    {
        $r->validate([
            'mes' => 'nullable|integer|min:1|max:12',
            'ano' => 'nullable|integer|min:2000|max:'.(now()->year + 1),
        ]);

        $mes = (int) ($r->mes ?? now()->month);
        $ano = (int) ($r->ano ?? now()->year);

        $dados = Cache::remember("relatorio.dashboard.graficos.{$ano}.{$mes}", now()->addMinutes(5), function () use ($mes, $ano) {
            $refDate = Carbon::createFromDate($ano, $mes, 1);
            $inicioMes = $refDate->copy()->startOfMonth();
            $fimMes = $refDate->copy()->endOfMonth();

            return [
                'abastecimento_mensal' => $this->serieAbastecimentoMensal($refDate),
                'km_mensal' => $this->serieKmMensal($refDate),
                'viagens_por_motivo' => $this->viagensPorMotivo($inicioMes, $fimMes),
                'km_por_motorista' => $this->kmPorMotorista($inicioMes, $fimMes),
                'viagens_por_motorista' => $this->viagensPorMotorista($inicioMes, $fimMes),
                'tempo_medio_motorista' => $this->tempoMedioPorMotorista($inicioMes, $fimMes),
            ];
        });

        return response()->json($dados);
    }

    private function preencherMeses(Carbon $inicio, Carbon $fim, array $porMes): array
    {
        $meses = [];
        $cursor = $inicio->copy()->startOfMonth();

        while ($cursor->lte($fim)) {
            $chave = $cursor->format('Y-m');
            $meses[] = [
                'mes' => $chave,
                'label' => $cursor->translatedFormat('M/Y'),
                'total' => round($porMes[$chave] ?? 0, 2),
            ];
            $cursor->addMonth();
        }

        return $meses;
    }

    private function serieAbastecimentoMensal(Carbon $refDate): array
    {
        $inicio = $refDate->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $fim = $refDate->copy()->endOfMonth();

        $rows = DB::table('abastecimentos')
            ->whereRaw('DATE(COALESCE(abastecido_at, created_at)) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->select('abastecido_at', 'created_at', 'litros', 'valor_litro')
            ->get();

        $porMes = [];
        foreach ($rows as $row) {
            $chave = Carbon::parse($row->abastecido_at ?? $row->created_at)->format('Y-m');
            $porMes[$chave] = ($porMes[$chave] ?? 0) + ($row->litros * $row->valor_litro);
        }

        return $this->preencherMeses($inicio, $fim, $porMes);
    }

    private function serieKmMensal(Carbon $refDate): array
    {
        $inicio = $refDate->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $fim = $refDate->copy()->endOfMonth();

        $rows = DB::table('viagens')
            ->whereNotNull('km_chegada')
            ->whereRaw('DATE(saida_at) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->select('saida_at', 'km_saida', 'km_chegada')
            ->get();

        $porMes = [];
        foreach ($rows as $row) {
            $chave = Carbon::parse($row->saida_at)->format('Y-m');
            $porMes[$chave] = ($porMes[$chave] ?? 0) + ($row->km_chegada - $row->km_saida);
        }

        return $this->preencherMeses($inicio, $fim, $porMes);
    }

    private function viagensPorMotivo(Carbon $inicio, Carbon $fim): array
    {
        $rows = DB::table('viagens')
            ->whereRaw('DATE(saida_at) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->select('motivo_viagem')
            ->get();

        $porMotivo = [];
        foreach ($rows as $row) {
            $motivo = trim((string) $row->motivo_viagem) !== '' ? $row->motivo_viagem : 'Não informado';
            $porMotivo[$motivo] = ($porMotivo[$motivo] ?? 0) + 1;
        }

        $total = array_sum($porMotivo);

        return collect($porMotivo)
            ->map(fn ($qtd, $motivo) => [
                'motivo' => $motivo,
                'total' => $qtd,
                'percentual' => $total > 0 ? round($qtd / $total * 100, 1) : 0,
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function kmPorMotorista(Carbon $inicio, Carbon $fim): array
    {
        $rows = DB::table('viagens as vg')
            ->join('motoristas as m', 'm.id', '=', 'vg.motorista_id')
            ->whereNotNull('vg.km_chegada')
            ->whereRaw('DATE(vg.saida_at) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->select('m.nome', 'vg.km_saida', 'vg.km_chegada')
            ->get();

        $porMotorista = [];
        foreach ($rows as $row) {
            $porMotorista[$row->nome] = ($porMotorista[$row->nome] ?? 0) + ($row->km_chegada - $row->km_saida);
        }

        $total = array_sum($porMotorista);

        return collect($porMotorista)
            ->map(fn ($km, $nome) => [
                'nome' => $nome,
                'km_total' => round($km, 2),
                'percentual' => $total > 0 ? round($km / $total * 100, 1) : 0,
            ])
            ->sortByDesc('km_total')
            ->values()
            ->all();
    }

    private function viagensPorMotorista(Carbon $inicio, Carbon $fim): array
    {
        return DB::table('viagens as vg')
            ->join('motoristas as m', 'm.id', '=', 'vg.motorista_id')
            ->whereRaw('DATE(vg.saida_at) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->groupBy('m.id', 'm.nome')
            ->select('m.nome', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['nome' => $row->nome, 'total' => $row->total])
            ->all();
    }

    private function tempoMedioPorMotorista(Carbon $inicio, Carbon $fim): array
    {
        $rows = DB::table('viagens as vg')
            ->join('motoristas as m', 'm.id', '=', 'vg.motorista_id')
            ->where('vg.status', 'concluida')
            ->whereNotNull('vg.chegada_at')
            ->whereRaw('DATE(vg.saida_at) BETWEEN ? AND ?', [$inicio->toDateString(), $fim->toDateString()])
            ->select('m.nome', 'vg.saida_at', 'vg.chegada_at')
            ->get();

        $porMotorista = [];
        foreach ($rows as $row) {
            $minutos = Carbon::parse($row->saida_at)->diffInMinutes(Carbon::parse($row->chegada_at));
            $porMotorista[$row->nome]['soma'] = ($porMotorista[$row->nome]['soma'] ?? 0) + $minutos;
            $porMotorista[$row->nome]['qtd'] = ($porMotorista[$row->nome]['qtd'] ?? 0) + 1;
        }

        return collect($porMotorista)
            ->map(fn ($x, $nome) => [
                'nome' => $nome,
                'tempo_medio_minutos' => (int) round($x['soma'] / $x['qtd']),
            ])
            ->sortByDesc('tempo_medio_minutos')
            ->values()
            ->all();
    }

    // ── RELATÓRIO: ABASTECIMENTOS ─────────────────────────────────
    private function abastecimentosData(Request $r): array
    {
        $de = $r->de ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('abastecimentos as a')
            ->leftJoin('veiculos as v', 'a.veiculo_id', '=', 'v.id')
            ->leftJoin('motoristas as m', 'a.motorista_id', '=', 'm.id')
            ->whereRaw('DATE(COALESCE(a.abastecido_at, a.created_at)) BETWEEN ? AND ?', [$de, $ate])
            ->orderByRaw('COALESCE(a.abastecido_at, a.created_at) DESC')
            ->select(
                'a.id',
                DB::raw('COALESCE(a.abastecido_at, a.created_at) as data'),
                'v.placa', 'v.modelo',
                'm.nome as motorista_nome',
                'a.posto', 'a.combustivel',
                'a.litros', 'a.valor_litro',
                DB::raw('(a.litros * a.valor_litro) as valor_total'),
                'a.km_momento'
            )
            ->get();

        // Totalizadores
        $totais = [
            'total_litros' => $rows->sum('litros'),
            'total_valor' => $rows->sum('valor_total'),
            'preco_medio' => $rows->sum('litros') > 0
                ? round($rows->sum('valor_total') / $rows->sum('litros'), 3)
                : 0,
            'total_registros' => $rows->count(),
        ];

        // Por veículo
        $por_veiculo = $rows->groupBy('placa')->map(fn ($g) => [
            'placa' => $g->first()->placa,
            'modelo' => $g->first()->modelo,
            'total_litros' => round($g->sum('litros'), 2),
            'total_valor' => round($g->sum('valor_total'), 2),
            'abastecimentos' => $g->count(),
        ])->values();

        return compact('rows', 'totais', 'por_veiculo', 'de', 'ate');
    }

    public function abastecimentos(Request $r)
    {
        return response()->json($this->abastecimentosData($r));
    }

    public function abastecimentosPdf(Request $r)
    {
        $dados = $this->abastecimentosData($r);

        return Pdf::loadView('relatorios.pdf.abastecimentos', $dados)
            ->download('relatorio-abastecimentos.pdf');
    }

    // ── RELATÓRIO: VIAGENS ────────────────────────────────────────
    // BUG CONHECIDO: usa TIMESTAMPDIFF/IF() (raw SQL exclusivo do MySQL) — quebra em SQLite.
    // Sem cobertura de teste por esse motivo. Ver plano "Fundação de Qualidade".
    private function viagensData(Request $r): array
    {
        $de = $r->de ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('viagens as vg')
            ->leftJoin('veiculos as v', 'vg.veiculo_id', '=', 'v.id')
            ->leftJoin('motoristas as m', 'vg.motorista_id', '=', 'm.id')
            ->whereRaw('DATE(vg.saida_at) BETWEEN ? AND ?', [$de, $ate])
            ->orderBy('vg.saida_at', 'desc')
            ->select(
                'vg.id', 'vg.saida_at', 'vg.chegada_at',
                'v.placa', 'v.modelo',
                'm.nome as motorista_nome',
                'vg.origem', 'vg.destino',
                'vg.motivo_viagem', 'vg.numero_atendimento',
                'vg.km_saida', 'vg.km_chegada', 'vg.status',
                DB::raw('IF(vg.km_chegada IS NOT NULL, vg.km_chegada - vg.km_saida, NULL) as km_percorrido'),
                DB::raw('IF(vg.chegada_at IS NOT NULL AND vg.saida_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, vg.saida_at, vg.chegada_at), NULL) as duracao_min')
            )
            ->get();

        $totais = [
            'total_viagens' => $rows->count(),
            'viagens_concluidas' => $rows->where('status', 'concluida')->count(),
            'km_total' => $rows->sum('km_percorrido'),
            'duracao_media_min' => $rows->whereNotNull('duracao_min')->avg('duracao_min'),
        ];

        $por_motorista = $rows->groupBy('motorista_nome')->map(fn ($g) => [
            'motorista' => $g->first()->motorista_nome ?? '—',
            'viagens' => $g->count(),
            'km_total' => $g->sum('km_percorrido'),
        ])->values()->sortByDesc('km_total')->values();

        return compact('rows', 'totais', 'por_motorista', 'de', 'ate');
    }

    public function viagens(Request $r)
    {
        return response()->json($this->viagensData($r));
    }

    public function viagensPdf(Request $r)
    {
        $dados = $this->viagensData($r);

        return Pdf::loadView('relatorios.pdf.viagens', $dados)
            ->download('relatorio-viagens.pdf');
    }

    // ── RELATÓRIO: PASSAGENS DE PLANTÃO ──────────────────────────
    private function plantaoData(Request $r): array
    {
        $de = $r->de ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $rows = DB::table('passagens_plantao as pp')
            ->leftJoin('veiculos as v', 'pp.veiculo_id', '=', 'v.id')
            ->leftJoin('motoristas as ms', 'pp.motorista_saindo_id', '=', 'ms.id')
            ->leftJoin('motoristas as me', 'pp.motorista_entrando_id', '=', 'me.id')
            ->whereRaw('DATE(pp.created_at) BETWEEN ? AND ?', [$de, $ate])
            ->orderBy('pp.created_at', 'desc')
            ->select(
                'pp.id', 'pp.created_at', 'pp.finalizado_at',
                'pp.turno_saindo', 'pp.turno_entrando',
                'pp.km_momento', 'pp.nivel_combustivel',
                'pp.observacoes_gerais',
                'v.placa', 'v.modelo',
                'ms.nome as motorista_saindo',
                'me.nome as motorista_entrando',
                DB::raw('CASE WHEN pp.finalizado_at IS NOT NULL AND pp.created_at IS NOT NULL
                    THEN CAST((julianday(pp.finalizado_at) - julianday(pp.created_at)) * 1440 AS INTEGER)
                    ELSE NULL END as duracao_min')
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

        $rows = $rows->map(function ($p) use ($checklist) {
            $cl = $checklist[$p->id] ?? null;
            $p->itens_ok = $cl->ok ?? 0;
            $p->itens_pendencia = $cl->pendencias ?? 0;

            return $p;
        });

        $totais = [
            'total' => $rows->count(),
            'encerrados' => $rows->whereNotNull('data_encerramento')->count(),
            'com_pendencia' => $rows->where('itens_pendencia', '>', 0)->count(),
            'duracao_media_min' => $rows->whereNotNull('duracao_min')->avg('duracao_min'),
        ];

        return compact('rows', 'totais', 'de', 'ate');
    }

    public function plantao(Request $r)
    {
        return response()->json($this->plantaoData($r));
    }

    public function plantaoPdf(Request $r)
    {
        $dados = $this->plantaoData($r);

        return Pdf::loadView('relatorios.pdf.plantao', $dados)
            ->download('relatorio-plantao.pdf');
    }

    // ── RELATÓRIO: MOTORISTAS ─────────────────────────────────────
    // BUG CONHECIDO: usa IF()/CURDATE()/DATE_ADD (raw SQL exclusivo do MySQL) — quebra em SQLite.
    // Sem cobertura de teste por esse motivo. Ver plano "Fundação de Qualidade".
    private function motoristasData(Request $r)
    {
        return DB::table('motoristas as m')
            ->leftJoin('viagens as vg', 'm.id', '=', 'vg.motorista_id')
            ->leftJoin('abastecimentos as ab', 'm.id', '=', 'ab.motorista_id')
            ->leftJoin('passagens_plantao as pp', 'm.id', '=', 'pp.motorista_entrando_id')
            ->where('m.status', '!=', 'inativo')
            ->groupBy('m.id', 'm.nome', 'm.cnh_numero', 'm.cnh_categoria', 'm.cnh_validade', 'm.turno_padrao', 'm.status')
            ->select(
                'm.id', 'm.nome', 'm.cnh_numero', 'm.cnh_categoria',
                'm.cnh_validade', 'm.turno_padrao', 'm.status',
                DB::raw('COUNT(DISTINCT vg.id) as total_viagens'),
                DB::raw('SUM(IF(vg.km_chegada IS NOT NULL, vg.km_chegada - vg.km_saida, 0)) as km_total'),
                DB::raw('COUNT(DISTINCT ab.id) as total_abastecimentos'),
                DB::raw('COUNT(DISTINCT pp.id) as total_plantoes'),
                DB::raw("IF(m.cnh_validade < CURDATE(), 'vencida', IF(m.cnh_validade < DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'vencendo', 'ok')) as cnh_status")
            )
            ->orderBy('m.nome')
            ->get();
    }

    public function motoristas(Request $r)
    {
        return response()->json($this->motoristasData($r));
    }

    public function motoristasPdf(Request $r)
    {
        $rows = $this->motoristasData($r);

        return Pdf::loadView('relatorios.pdf.motoristas', compact('rows'))
            ->download('relatorio-motoristas.pdf');
    }

    // ── RELATÓRIO: EFICIÊNCIA (custo/km, consumo, ranking de motoristas) ─
    // Nota: em vez de um único SELECT com dois LEFT JOIN independentes
    // (abastecimentos + viagens) — que produziria um produto cartesiano
    // (N abastecimentos × M viagens por veículo) — agregamos cada tabela
    // em subqueries separadas e as unimos por veiculo_id. Isso evita
    // qualquer duplicação/undercount de litros ou km, sem precisar de
    // truques como SUM(DISTINCT ...) que falham quando há valores repetidos
    // (ex.: dois abastecimentos com exatamente os mesmos litros).
    public function eficiencia(Request $r)
    {
        $de = $r->de ?? now()->startOfMonth()->toDateString();
        $ate = $r->ate ?? now()->toDateString();

        $dados = Cache::remember("relatorio.eficiencia.{$de}.{$ate}", now()->addMinutes(5), function () use ($de, $ate) {
            $abastecimentosPorVeiculo = DB::table('abastecimentos')
                ->whereRaw('DATE(COALESCE(abastecido_at, created_at)) BETWEEN ? AND ?', [$de, $ate])
                ->groupBy('veiculo_id')
                ->select(
                    'veiculo_id',
                    DB::raw('SUM(litros) as total_litros'),
                    DB::raw('SUM(litros * valor_litro) as custo_total')
                );

            $viagensPorVeiculo = DB::table('viagens')
                ->whereNotNull('km_chegada')
                ->whereRaw('DATE(saida_at) BETWEEN ? AND ?', [$de, $ate])
                ->groupBy('veiculo_id')
                ->select(
                    'veiculo_id',
                    DB::raw('SUM(km_chegada - km_saida) as km_total')
                );

            $porVeiculo = DB::table('veiculos as v')
                ->leftJoinSub($abastecimentosPorVeiculo, 'a', 'a.veiculo_id', '=', 'v.id')
                ->leftJoinSub($viagensPorVeiculo, 'vg', 'vg.veiculo_id', '=', 'v.id')
                ->select(
                    'v.id as veiculo_id', 'v.placa', 'v.modelo',
                    DB::raw('COALESCE(a.total_litros, 0) as total_litros'),
                    DB::raw('COALESCE(a.custo_total, 0) as custo_total'),
                    DB::raw('COALESCE(vg.km_total, 0) as km_total')
                )
                ->get()
                ->map(function ($row) {
                    $row->custo_por_km = $row->km_total > 0 ? round($row->custo_total / $row->km_total, 3) : null;
                    $row->consumo_km_por_litro = $row->total_litros > 0 ? round($row->km_total / $row->total_litros, 2) : null;

                    return $row;
                });

            $rankingMotoristas = DB::table('motoristas as m')
                ->leftJoin('viagens as vg', function ($j) use ($de, $ate) {
                    $j->on('vg.motorista_id', '=', 'm.id')
                        ->whereNotNull('vg.km_chegada')
                        ->whereRaw('DATE(vg.saida_at) BETWEEN ? AND ?', [$de, $ate]);
                })
                ->groupBy('m.id', 'm.nome')
                ->select('m.id as motorista_id', 'm.nome', DB::raw('COALESCE(SUM(vg.km_chegada - vg.km_saida), 0) as km_total'))
                ->orderByDesc('km_total')
                ->get();

            return compact('porVeiculo', 'rankingMotoristas');
        });

        return response()->json($dados);
    }

    // ── RELATÓRIO: CHECKINS (para dashboard) ─────────────────────
    public function checkins(Request $r)
    {
        $rows = DB::table('checkins as c')
            ->leftJoin('veiculos as v', 'c.veiculo_id', '=', 'v.id')
            ->leftJoin('motoristas as m', 'c.motorista_id', '=', 'm.id')
            ->orderBy('c.checkin_at', 'desc')
            ->limit(50)
            ->select('c.*', 'v.placa as veiculo_placa', 'v.modelo as veiculo_modelo', 'm.nome as motorista_nome')
            ->get();

        return response()->json($rows);
    }

    // ── RELATÓRIO: CHECKLIST DE VEÍCULO ──────────────────────────
    public function checklistVeiculo(Request $r)
    {
        $rows = DB::table('checklists_veiculo as cv')
            ->leftJoin('veiculos as v', 'cv.veiculo_id', '=', 'v.id')
            ->leftJoin('motoristas as m', 'cv.motorista_id', '=', 'm.id')
            ->when($r->veiculo_id, fn ($q, $id) => $q->where('cv.veiculo_id', $id))
            ->when($r->data, fn ($q, $d) => $q->where('cv.data_referencia', $d))
            ->when($r->status, fn ($q, $s) => $q->where('cv.status', $s))
            ->orderByDesc('cv.data_referencia')
            ->select('cv.*', 'v.placa as veiculo_placa', 'v.modelo as veiculo_modelo', 'm.nome as motorista_nome')
            ->limit(200)
            ->get();

        $itensNaoConforme = DB::table('checklist_veiculo_respostas as r')
            ->join('checklist_veiculo_itens_modelo as im', 'r.item_modelo_id', '=', 'im.id')
            ->whereIn('r.checklist_veiculo_id', $rows->pluck('id'))
            ->where('r.conforme', false)
            ->select('r.checklist_veiculo_id', 'im.label', 'r.observacao', 'r.foto_path')
            ->get()
            ->groupBy('checklist_veiculo_id');

        $rows = $rows->map(function ($row) use ($itensNaoConforme) {
            $row->itens_nao_conforme_detalhe = $itensNaoConforme->get($row->id, collect())->values();

            return $row;
        });

        return response()->json($rows);
    }
}
