<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('relatorios.pdf._styles')
</head>
<body>
    <h1>Relatório de Motoristas</h1>
    <p class="periodo">Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    <table class="dados">
        <thead>
            <tr>
                <th>Nome</th><th>CNH</th><th>Cat.</th><th>Validade CNH</th><th>Turno</th>
                <th>Viagens</th><th>KM total</th><th>Abastec.</th><th>Plantões</th><th>Status</th><th>CNH</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td>{{ $m->nome }}</td>
                    <td>{{ $m->cnh_numero }}</td>
                    <td>{{ $m->cnh_categoria }}</td>
                    <td>{{ $m->cnh_validade }}</td>
                    <td>{{ $m->turno_padrao ?? '—' }}</td>
                    <td>{{ $m->total_viagens }}</td>
                    <td>{{ number_format($m->km_total, 0, ',', '.') }} km</td>
                    <td>{{ $m->total_abastecimentos }}</td>
                    <td>{{ $m->total_plantoes }}</td>
                    <td>{{ $m->status }}</td>
                    <td>{{ $m->cnh_status }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="sem-dados">Sem dados</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
