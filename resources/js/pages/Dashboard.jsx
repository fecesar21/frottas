import { useQuery } from '@tanstack/react-query'
import { Truck, Users, LogIn, Fuel, Route, AlertTriangle } from 'lucide-react'
import * as relatoriosApi from '../api/relatorios'
import Card from '../components/ui/Card'
import LoadingSpinner from '../components/ui/LoadingSpinner'
import Badge from '../components/ui/Badge'

function fmt(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR')
}

function fmtBrl(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export default function Dashboard() {
  const { data, isLoading } = useQuery({
    queryKey: ['dashboard'],
    queryFn: () => relatoriosApi.dashboard().then(r => r.data),
    refetchInterval: 60_000,
  })

  if (isLoading) return <LoadingSpinner />

  const v = data?.veiculos ?? {}

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card title="Disponíveis" value={fmt(v.disponiveis)} icon={Truck} color="green" />
        <Card title="Em uso" value={fmt(v.em_uso)} icon={Truck} color="blue" />
        <Card title="Manutenção" value={fmt(v.manutencao)} icon={Truck} color="yellow" />
        <Card title="Checkins ativos" value={fmt(data?.checkins_ativos)} icon={LogIn} color="blue" />
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card title="Motoristas ativos" value={fmt(data?.motoristas?.ativos)} icon={Users} color="green"
          sub={`Total: ${fmt(data?.motoristas?.total)}`} />
        <Card title="KM no mês" value={fmt(data?.km_mes)} icon={Route} color="gray" />
        <Card title="Combustível (mês)" value={fmtBrl(data?.custo_combustivel_mes)} icon={Fuel} color="yellow" />
        <Card title="Total veículos" value={fmt(v.total)} icon={Truck} color="gray" />
      </div>

      {data?.cnh_vencendo?.length > 0 && (
        <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-5">
          <div className="flex items-center gap-2 mb-3">
            <AlertTriangle size={18} className="text-yellow-600" />
            <h3 className="font-semibold text-yellow-800">CNH vencendo nos próximos 30 dias</h3>
          </div>
          <div className="flex flex-wrap gap-2">
            {data.cnh_vencendo.map((nome, i) => (
              <span key={i} className="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full">
                {nome}
              </span>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
