<p>Olá,</p>
<p>Segue em anexo o <strong>{{ $titulo }}</strong> ({{ $periodo }}).</p>
<ul>
    <li>Viagens: <strong>{{ $totais['viagens'] }}</strong></li>
    <li>KM rodados: <strong>{{ number_format($totais['km'], 1, ',', '.') }}</strong></li>
    <li>Tempo em manutenção: <strong>{{ intdiv($totais['manutencao_minutos'], 60) }}h {{ $totais['manutencao_minutos'] % 60 }}min</strong></li>
</ul>
<p>Mensagem automática do Health Drive.</p>
