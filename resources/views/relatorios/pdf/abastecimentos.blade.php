<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    <h1>Relatório de Abastecimentos</h1>
    <p class="periodo">Período: {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }} — gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table class="totais">
        <tr>
            <td><span class="label">Total litros</span><span class="valor">{{ number_format($totais['total_litros'], 1, ',', '.') }} L</span></td>
            <td><span class="label">Total gasto</span><span class="valor">R$ {{ number_format($totais['total_valor'], 2, ',', '.') }}</span></td>
            <td><span class="label">Preço médio/L</span><span class="valor">R$ {{ number_format($totais['preco_medio'], 3, ',', '.') }}</span></td>
            <td><span class="label">Registros</span><span class="valor">{{ $totais['total_registros'] }}</span></td>
        </tr>
    </table>

    <table class="dados">
        <thead>
            <tr>
                <th>Data</th><th>Placa</th><th>Motorista</th><th>Posto</th><th>Combustível</th>
                <th>Litros</th><th>R$/L</th><th>Total</th><th>KM</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($r->data)->format('d/m/Y H:i') }}</td>
                    <td>{{ $r->placa }}</td>
                    <td>{{ $r->motorista_nome }}</td>
                    <td>{{ $r->posto ?? '—' }}</td>
                    <td>{{ str_replace('_', ' ', $r->combustivel) }}</td>
                    <td>{{ number_format($r->litros, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($r->valor_litro, 2, ',', '.') }}</td>
                    <td>R$ {{ number_format($r->valor_total, 2, ',', '.') }}</td>
                    <td>{{ number_format($r->km_momento, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
