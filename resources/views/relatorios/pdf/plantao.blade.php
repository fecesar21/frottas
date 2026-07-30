<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    <h1>Relatório de Passagens de Plantão</h1>
    <p class="periodo">Período: {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }} — gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table class="totais">
        <tr>
            <td><span class="label">Total passagens</span><span class="valor">{{ $totais['total'] }}</span></td>
            <td><span class="label">Encerradas</span><span class="valor">{{ $totais['encerrados'] }}</span></td>
            <td><span class="label">Com pendência</span><span class="valor">{{ $totais['com_pendencia'] }}</span></td>
            <td><span class="label">Duração média</span><span class="valor">{{ round($totais['duracao_media_min'] ?? 0) }} min</span></td>
        </tr>
    </table>

    <table class="dados">
        <thead>
            <tr>
                <th>Data</th><th>Placa</th><th>Saindo</th><th>Entrando</th><th>KM</th>
                <th>OK</th><th>Pendência</th><th>Duração</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($r->created_at)->format('d/m H:i') }}</td>
                    <td>{{ $r->placa }}</td>
                    <td>{{ $r->motorista_saindo }}</td>
                    <td>{{ $r->motorista_entrando }}</td>
                    <td>{{ number_format($r->km_momento, 0, ',', '.') }}</td>
                    <td>{{ $r->itens_ok }}</td>
                    <td>{{ $r->itens_pendencia }}</td>
                    <td>{{ $r->duracao_min ? $r->duracao_min.' min' : '—' }}</td>
                    <td>{{ $r->finalizado_at ? 'finalizado' : 'pendente' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
