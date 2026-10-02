<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    @php
        $fmt = function ($min) {
            $d = intdiv($min, 1440); $h = intdiv($min % 1440, 60); $m = $min % 60;
            return trim(($d ? "{$d}d " : '') . ($h ? "{$h}h " : '') . ((! $d && ! $h) || $m ? "{$m}min" : ''));
        };
        $km = fn ($v) => number_format($v, 1, ',', '.');
    @endphp

    <h1>{{ $titulo }}</h1>
    <p class="periodo">Período: {{ $periodo }} — gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <p>
        Viagens: <strong>{{ $totais['viagens'] }}</strong> —
        KM rodados: <strong>{{ $km($totais['km']) }}</strong> —
        Tempo em manutenção: <strong>{{ $fmt($totais['manutencao_minutos']) }}</strong>
    </p>

    <h2>KM rodados por veículo</h2>
    <table class="dados">
        <thead><tr><th>Placa</th><th>Modelo</th><th>Viagens</th><th>KM</th></tr></thead>
        <tbody>
            @forelse ($km_por_veiculo as $r)
                <tr><td>{{ $r['placa'] }}</td><td>{{ $r['modelo'] }}</td><td>{{ $r['viagens'] }}</td><td>{{ $km($r['km']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Viagens por motorista</h2>
    <table class="dados">
        <thead><tr><th>Motorista</th><th>Viagens</th></tr></thead>
        <tbody>
            @forelse ($viagens_por_motorista as $r)
                <tr><td>{{ $r['nome'] }}</td><td>{{ $r['viagens'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>KM rodados por motorista</h2>
    <table class="dados">
        <thead><tr><th>Motorista</th><th>KM</th></tr></thead>
        <tbody>
            @forelse ($km_por_motorista as $r)
                <tr><td>{{ $r['nome'] }}</td><td>{{ $km($r['km']) }}</td></tr>
            @empty
                <tr><td colspan="2" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Tempo em manutenção por veículo</h2>
    <table class="dados">
        <thead><tr><th>Placa</th><th>Modelo</th><th>Manutenções</th><th>Tempo no período</th></tr></thead>
        <tbody>
            @forelse ($manutencao_por_veiculo as $r)
                <tr><td>{{ $r['placa'] }}</td><td>{{ $r['modelo'] }}</td><td>{{ $r['manutencoes'] }}</td><td>{{ $fmt($r['minutos']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Viagens por motivo</h2>
    <table class="dados">
        <thead><tr><th>Motivo</th><th>Viagens</th></tr></thead>
        <tbody>
            @forelse ($viagens_por_motivo as $r)
                <tr><td>{{ $r['motivo'] }}</td><td>{{ $r['viagens'] }}</td></tr>
            @empty
                <tr><td colspan="2" class="sem-dados">Sem dados no período</td></tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($observacoes))
        <h2>Observações</h2>
        <ul>
            @foreach ($observacoes as $obs)
                <li>{{ $obs }}</li>
            @endforeach
        </ul>
    @endif
</body>
</html>
