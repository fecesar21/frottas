import { useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { useAuth } from '../../contexts/AuthContext'
import { useRastreamento } from '../../hooks/useRastreamento'
import * as viagensApi from '../../api/viagens'
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
