import { useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import { useRastreamento } from '../../hooks/useRastreamento'
import * as viagensApi from '../../api/viagens'
import * as checklistVeiculoApi from '../../api/checklistVeiculo'
import ChecklistVeiculoModal from '../../pages/checkins/ChecklistVeiculoModal'
import Sidebar from './Sidebar'
import Header from './Header'

const pageTitles = {
  '/': 'Dashboard',
  '/veiculos': 'Veículos',
  '/motoristas': 'Motoristas',
  '/escalas': 'Escalas',
  '/checkins': 'Check-ins',
  '/plantao': 'Passagem de Plantão',
  '/viagens': 'Viagens',
  '/solicitacoes': 'Solicitações de Transporte',
  '/abastecimentos': 'Abastecimentos',
  '/relatorios': 'Relatórios',
  '/usuarios': 'Usuários',
}

const ROTAS_OPERADOR_SEM_CHECKIN = ['/checkins']
const ROTAS_OPERADOR_COM_CHECKIN = ['/checkins', '/viagens', '/abastecimentos']

export function PrivateRoute() {
  const { user, isOperador, checkinAtivo } = useAuth()
  const location = useLocation()

  if (!user) return <Navigate to="/login" replace />

  if (isOperador) {
    const rotasPermitidas = checkinAtivo ? ROTAS_OPERADOR_COM_CHECKIN : ROTAS_OPERADOR_SEM_CHECKIN
    const rotaAtual = '/' + location.pathname.split('/')[1]

    if (!rotasPermitidas.includes(rotaAtual)) {
      return <Navigate to={checkinAtivo ? '/viagens' : '/checkins'} replace />
    }
  }

  return <Layout />
}

export function AdminRoute({ children }) {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  if (user.perfil !== 'admin') return <Navigate to="/" replace />
  return children
}

function useChecklistVeiculoBloqueio() {
  const { isOperador, checkinAtivo } = useAuth()

  const { data } = useQuery({
    queryKey: ['checklist-veiculo', 'bloqueio', checkinAtivo?.id],
    queryFn: () => checklistVeiculoApi.pendente().then(r => r.data),
    enabled: isOperador && !!checkinAtivo,
    refetchOnWindowFocus: false,
  })

  return isOperador && !!checkinAtivo && !!data && data.status !== 'enviado'
}

function useRastreamentoGlobal() {
  const { isOperador } = useAuth()

  const { data } = useQuery({
    queryKey: ['viagens', 'em_andamento'],
    queryFn: () => viagensApi.listar({ status: 'em_andamento' }).then(r => r.data.data ?? r.data),
    enabled: isOperador,
    refetchInterval: 30000,
  })

  const activeTrip = isOperador ? (data ?? [])[0] : null
  const rastreamento = useRastreamento(activeTrip?.id ?? null)
  return { ...rastreamento, rastreando: Boolean(activeTrip?.id) }
}

export default function Layout() {
  const location = useLocation()
  const base = '/' + location.pathname.split('/')[1]
  const title = pageTitles[base] ?? 'Health Drive'
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const { erro: erroRastreamento, pendentes, rastreando } = useRastreamentoGlobal()
  const bloqueadoPorChecklist = useChecklistVeiculoBloqueio()
  const qc = useQueryClient()

  if (bloqueadoPorChecklist) {
    return (
      <div className="fixed inset-0 z-[100] flex items-center justify-center bg-navy-950/70 backdrop-blur-sm p-4">
        <div className="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
          <div className="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white rounded-t-2xl">
            <h2 className="text-base font-semibold text-gray-800">Checklist do veículo obrigatório</h2>
            <p className="text-xs text-gray-500 mt-1">Preencha todo o checklist para continuar utilizando o sistema.</p>
          </div>
          <div className="overflow-y-auto flex-1 px-6 py-5">
            <ChecklistVeiculoModal onDone={() => qc.invalidateQueries({ queryKey: ['checklist-veiculo'] })} />
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="flex min-h-screen bg-slate-50">
      <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />
      <div className="flex-1 flex flex-col min-w-0">
        <Header title={title} onMenuClick={() => setSidebarOpen(true)} />
        {rastreando && !erroRastreamento && (
          <div className="bg-emerald-50 border-b border-emerald-200 text-emerald-800 text-xs px-4 py-2 flex items-center gap-2">
            <span className="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
            Rastreando viagem{pendentes > 0 ? ` — ${pendentes} ponto(s) pendente(s) de envio` : ''}
          </div>
        )}
        {erroRastreamento && (
          <div className="bg-amber-50 border-b border-amber-200 text-amber-800 text-xs px-4 py-2">
            GPS: {erroRastreamento}{pendentes > 0 ? ` (${pendentes} ponto(s) pendente(s) de envio)` : ''}
          </div>
        )}
        <main className="flex-1 p-5 md:p-6 overflow-auto animate-fade-in">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
