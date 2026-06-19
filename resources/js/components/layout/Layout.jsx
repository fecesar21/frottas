import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useAuth } from '../../contexts/AuthContext'
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

export function PrivateRoute() {
  const { user } = useAuth()
  return user ? <Layout /> : <Navigate to="/login" replace />
}

export function AdminRoute({ children }) {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  if (user.perfil !== 'admin') return <Navigate to="/" replace />
  return children
}

export default function Layout() {
  const location = useLocation()
  const base = '/' + location.pathname.split('/')[1]
  const title = pageTitles[base] ?? 'Health Drive'

  return (
    <div className="flex min-h-screen bg-gray-50">
      <Sidebar />
      <div className="flex-1 flex flex-col min-w-0">
        <Header title={title} />
        <main className="flex-1 p-6 overflow-auto">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
