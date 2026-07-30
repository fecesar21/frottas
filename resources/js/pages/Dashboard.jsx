import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Truck, Users, LogIn, Fuel, Route, AlertTriangle, Activity } from 'lucide-react'
import * as relatoriosApi from '../api/relatorios'
import Card from '../components/ui/Card'
import LoadingSpinner from '../components/ui/LoadingSpinner'
import { useAuth } from '../contexts/AuthContext'
import MonthYearFilter from '../components/charts/MonthYearFilter'
import LineChartCard from '../components/charts/LineChartCard'
import BarChartCard from '../components/charts/BarChartCard'
import PieChartCard from '../components/charts/PieChartCard'
import { fmtBrl as fmtBrlChart, fmtNumero, fmtMinutos, CHART_COLORS } from '../components/charts/chartTheme'

function fmt(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR')
}

function fmtBrl(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

const hoje = new Date()

export default function Dashboard() {
  const { user } = useAuth()
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => relatoriosApi.dashboard().then(r => r.data),
    refetchInterval: 60_000,
  })

  const [periodo, setPeriodo] = useState({ mes: hoje.getMonth() + 1, ano: hoje.getFullYear() })

  const { data: graficos, isLoading: loadingGraficos } = useQuery({
    queryKey: ['dashboard-graficos', periodo.mes, periodo.ano],
    queryFn: () => relatoriosApi.dashboardGraficos(periodo).then(r => r.data),
    refetchInterval: 60_000,
  })

  if (isLoading) return <LoadingSpinner />

  const v = data?.veiculos ?? {}
  const firstName = user?.nome?.split(' ')[0] ?? 'usuário'

  return (
    <div className="space-y-6">
      {/* Welcome banner */}
      <div className="relative bg-gradient-to-r from-brand-600 via-brand-700 to-navy-800 rounded-2xl p-6 text-white overflow-hidden">
        <div className="absolute right-0 top-0 w-64 h-full opacity-10">
          <Truck size={200} className="absolute -right-8 -top-8 rotate-12" />
        </div>
        <div className="relative">
          <p className="text-brand-200 text-sm font-medium mb-1">Bem-vindo de volta</p>
          <h2 className="text-2xl font-bold tracking-tight">{firstName}</h2>
          <p className="text-brand-200/80 text-sm mt-1">
            Aqui está um resumo da frota hoje.
          </p>
        </div>
        <div className="relative mt-4 flex items-center gap-2 text-xs text-brand-200/70">
          <Activity size={13} />
          <span>Atualizado a cada minuto</span>
        </div>
      </div>

      {/* KPI grid */}
      <div>
        <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Veículos</h3>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Card title="Disponíveis"    value={fmt(v.disponiveis)}        icon={Truck}  color="green" />
          <Card title="Em uso"         value={fmt(v.em_uso)}             icon={Truck}  color="blue" />
          <Card title="Manutenção"     value={fmt(v.manutencao)}         icon={Truck}  color="yellow" />
          <Card title="Total veículos" value={fmt(v.total)}              icon={Truck}  color="gray" />
        </div>
      </div>

      <div>
        <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Operações</h3>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <Card title="Checkins ativos"   value={fmt(data?.checkins_ativos)}           icon={LogIn}  color="blue" />
          <Card title="Motoristas ativos" value={fmt(data?.motoristas?.ativos)}        icon={Users}  color="green"
            sub={`Total: ${fmt(data?.motoristas?.total)}`} />
          <Card title="KM no mês"         value={fmt(data?.km_mes)}                   icon={Route}  color="gray" />
          <Card title="Combustível (mês)" value={fmtBrl(data?.custo_combustivel_mes)} icon={Fuel}   color="yellow" />
        </div>
      </div>

      {/* Gráficos interativos */}
      <div className="space-y-4">
        <div className="flex items-center justify-between flex-wrap gap-3">
          <h3 className="text-xs font-semibold text-gray-400 uppercase tracking-wider">Análise gráfica</h3>
          <MonthYearFilter mes={periodo.mes} ano={periodo.ano} onChange={setPeriodo} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <LineChartCard
            title="Total de Abastecimento (Mês a Mês)"
            data={graficos?.abastecimento_mensal}
            xKey="label"
            valueKey="total"
            valueFormatter={fmtBrlChart}
            loading={loadingGraficos}
            color={CHART_COLORS[0]}
          />
          <BarChartCard
            title="Total de KM (Mês a Mês)"
            data={graficos?.km_mensal}
            xKey="label"
            valueKey="total"
            valueFormatter={fmtNumero}
            layout="vertical"
            loading={loadingGraficos}
            color={CHART_COLORS[1]}
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <PieChartCard
            title="Viagens por Motivo (no mês)"
            data={graficos?.viagens_por_motivo}
            nameKey="motivo"
            valueKey="total"
            pctKey="percentual"
            loading={loadingGraficos}
          />
          <PieChartCard
            title="KM por Motorista (no mês)"
            data={graficos?.km_por_motorista}
            nameKey="nome"
            valueKey="km_total"
            pctKey="percentual"
            loading={loadingGraficos}
          />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <BarChartCard
            title="Quantidade de Viagens por Motorista (no mês)"
            data={graficos?.viagens_por_motorista}
            xKey="nome"
            valueKey="total"
            valueFormatter={fmtNumero}
            layout="horizontal"
            loading={loadingGraficos}
            colorByItem
          />
          <BarChartCard
            title="Tempo Médio de Viagem por Motorista (no mês)"
            data={graficos?.tempo_medio_motorista}
            xKey="nome"
            valueKey="tempo_medio_minutos"
            valueFormatter={fmtMinutos}
            layout="horizontal"
            loading={loadingGraficos}
            colorByItem
          />
        </div>
      </div>

      {/* CNH alert */}
      {data?.cnh_vencendo?.length > 0 && (
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-5">
          <div className="flex items-center gap-2.5 mb-3">
            <div className="bg-amber-100 rounded-lg p-1.5">
              <AlertTriangle size={16} className="text-amber-600" />
            </div>
            <h3 className="font-semibold text-amber-800 text-sm">
              CNH vencendo nos próximos 30 dias
            </h3>
            <span className="ml-auto text-xs font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
              {data.cnh_vencendo.length}
            </span>
          </div>
          <div className="flex flex-wrap gap-2">
            {data.cnh_vencendo.map((nome, i) => (
              <span key={i} className="bg-amber-100 text-amber-800 text-sm px-3 py-1 rounded-full font-medium border border-amber-200">
                {nome}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
