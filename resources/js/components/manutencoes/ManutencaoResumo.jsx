import { Wrench, Clock, Timer, AlertTriangle } from 'lucide-react'
import Card from '../ui/Card'
import BarChartCard from '../charts/BarChartCard'
import { fmtDuracao, CHART_COLORS } from '../charts/chartTheme'

const fmtData = (iso) => new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })

// Indicadores, gráfico por veículo e lista "em manutenção agora".
// Usado no Dashboard (mês selecionado) e na aba de Relatórios (período livre).
export default function ManutencaoResumo({ data, loading }) {
  const t = data?.totais ?? {}
  const emAndamento = data?.em_andamento ?? []
  const porVeiculo = (data?.por_veiculo ?? []).map(v => ({ ...v, horas: Math.round((v.tempo_total_min / 60) * 10) / 10 }))

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <Card title="Manutenções" value={loading ? '…' : (t.total_manutencoes ?? 0)} icon={Wrench} color="yellow" />
        <Card title="Tempo total" value={loading ? '…' : fmtDuracao(t.tempo_total_min ?? 0)} icon={Clock} color="gray" />
        <Card title="Tempo médio" value={loading ? '…' : fmtDuracao(t.tempo_medio_min)} icon={Timer} color="blue" />
        <Card title="Em manutenção agora" value={loading ? '…' : (t.em_manutencao_agora ?? 0)} icon={AlertTriangle}
          color={t.em_manutencao_agora ? 'red' : 'green'} />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <BarChartCard
          title="Tempo em manutenção por veículo (horas)"
          data={porVeiculo}
          xKey="placa"
          valueKey="horas"
          valueFormatter={(h) => fmtDuracao(Math.round(h * 60))}
          layout="horizontal"
          loading={loading}
          color={CHART_COLORS[3]}
        />

        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
          <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4">Em manutenção agora</h3>
          {loading ? (
            <div className="h-40 animate-pulse bg-gray-100 rounded-lg" />
          ) : emAndamento.length === 0 ? (
            <div className="h-40 flex items-center justify-center text-sm text-gray-400">Nenhum veículo em manutenção</div>
          ) : (
            <ul className="divide-y divide-gray-100">
              {emAndamento.map((m, i) => (
                <li key={i} className="py-2.5 flex items-start justify-between gap-3 text-sm">
                  <div className="min-w-0">
                    <p className="font-mono font-semibold text-gray-800">{m.placa} <span className="font-sans font-normal text-gray-500">— {m.modelo}</span></p>
                    <p className="text-xs text-gray-500 truncate">{m.motivo || 'Sem motivo informado'} · desde {fmtData(m.inicio)}</p>
                  </div>
                  <span className="shrink-0 text-xs font-semibold text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-full px-2 py-0.5">
                    {fmtDuracao(m.duracao_min)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>
    </div>
  )
}
