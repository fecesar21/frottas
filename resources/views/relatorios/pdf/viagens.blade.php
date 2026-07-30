<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    <h1>Relatório de Viagens</h1>
    <p class="periodo">Período: {{ \Carbon\Carbon::parse($de)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ate)->format('d/m/Y') }} — gerado em {{ now()->format('d/m/Y H:i') }}</p>

    @php
        $motivos = [
            'transferencia_paciente' => 'Transferência de Paciente',
            'buscar_medico' => 'Buscar médico em outra cidade',
        ];
    @endphp

    <table class="totais">
        <tr>
            <td><span class="label">Total viagens</span><span class="valor">{{ $totais['total_viagens'] }}</span></td>
            <td><span class="label">Concluídas</span><span class="valor">{{ $totais['viagens_concluidas'] }}</span></td>
            <td><span class="label">KM total</span><span class="valor">{{ number_format($totais['km_total'] ?? 0, 0, ',', '.') }} km</span></td>
            <td><span class="label">Duração média</span><span class="valor">{{ round($totais['duracao_media_min'] ?? 0) }} min</span></td>
        </tr>
    </table>

    <table class="dados">
        <thead>
            <tr>
                <th>Saída</th><th>Chegada</th><th>Placa</th><th>Motorista</th><th>Origem → Destino</th>
                <th>Motivo</th><th>Nº Atend.</th><th>KM perc.</th><th>Duração</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->saida_at ? \Carbon\Carbon::parse($r->saida_at)->format('d/m H:i') : '—' }}</td>
                    <td>{{ $r->chegada_at ? \Carbon\Carbon::parse($r->chegada_at)->format('d/m H:i') : '—' }}</td>
                    <td>{{ $r->placa }}</td>
                    <td>{{ $r->motorista_nome }}</td>
                    <td>{{ $r->origem }} → {{ $r->destino }}</td>
                    <td>{{ $motivos[$r->motivo_viagem] ?? '—' }}</td>
                    <td>{{ $r->numero_atendimento ?? '—' }}</td>
                    <td>{{ $r->km_percorrido !== null ? number_format($r->km_percorrido, 0, ',', '.') : '—' }}</td>
                    <td>{{ $r->duracao_min ? $r->duracao_min.' min' : '—' }}</td>
                    <td>{{ $r->status }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
