<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    @php
        $fmt = function ($min) {
            if ($min === null) return '—';
            $d = intdiv($min, 1440); $h = intdiv($min % 1440, 60); $m = $min % 60;
            return trim(($d ? "{$d}d " : '') . ($h ? "{$h}h " : '') . ((! $d && ! $h) || $m ? "{$m}min" : ''));
        };
    @endphp

    <h1>Relatório de Manutenções</h1>
    <p class="periodo">Período: {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }} — gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <p>
        Manutenções: <strong>{{ $totais['total_manutencoes'] }}</strong> —
        Tempo total no período: <strong>{{ $fmt($totais['tempo_total_min']) }}</strong> —
        Tempo médio: <strong>{{ $fmt($totais['tempo_medio_min']) }}</strong> —
        Em manutenção agora: <strong>{{ $totais['em_manutencao_agora'] }}</strong>
    </p>

    <table class="dados">
        <thead>
            <tr>
                <th>Placa</th><th>Modelo</th><th>Início</th><th>Fim</th><th>Motivo</th><th>Duração total</th><th>No período</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td>{{ $m->placa }}</td>
                    <td>{{ $m->modelo }}</td>
                    <td>{{ \Carbon\Carbon::parse($m->inicio)->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->fim ? \Carbon\Carbon::parse($m->fim)->format('d/m/Y H:i') : 'Em andamento' }}</td>
                    <td>{{ $m->motivo ?? '—' }}</td>
                    <td>{{ $fmt($m->duracao_min) }}</td>
                    <td>{{ $fmt($m->duracao_periodo_min) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
