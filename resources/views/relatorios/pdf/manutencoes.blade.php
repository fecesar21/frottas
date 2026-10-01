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

    @if (count($por_tipo))
    <table class="dados">
        <thead><tr><th>Tipo</th><th>Manutenções</th><th>Tempo no período</th></tr></thead>
        <tbody>
            @foreach ($por_tipo as $t)
                <tr><td>{{ $t['tipo_label'] }}</td><td>{{ $t['manutencoes'] }}</td><td>{{ $fmt($t['tempo_total_min']) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <br>
    @endif

    <table class="dados">
        <thead>
            <tr>
                <th>Placa</th><th>Modelo</th><th>Tipo</th><th>Motivo</th><th>Início</th><th>Fim</th><th>Aberta por</th><th>Fechada por</th><th>Duração total</th><th>No período</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td>{{ $m->placa }}</td>
                    <td>{{ $m->modelo }}</td>
                    <td>{{ $m->tipo_label }}</td>
                    <td>{{ $m->motivo ?? '—' }}</td>
                    <td>{{ \Carbon\Carbon::parse($m->inicio)->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->fim ? \Carbon\Carbon::parse($m->fim)->format('d/m/Y H:i') : 'Em andamento' }}</td>
                    <td>{{ $m->aberta_por ?? '—' }}{{ $m->origem === 'motorista' ? ' (motorista)' : '' }}</td>
                    <td>{{ $m->fechada_por ?? '—' }}</td>
                    <td>{{ $fmt($m->duracao_min) }}</td>
                    <td>{{ $fmt($m->duracao_periodo_min) }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
