import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Truck, Users, CalendarDays, LogIn, GitBranch,
  Route, Fuel, Gauge, BarChart3, UserCog, ChevronRight
} from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/veiculos', label: 'Veículos', icon: Truck },
  { to: '/motoristas', label: 'Motoristas', icon: Users },
  { to: '/escalas', label: 'Escalas', icon: CalendarDays },
  { to: '/checkins', label: 'Check-ins', icon: LogIn },
  { to: '/plantao', label: 'Plantão', icon: GitBranch },
  { to: '/viagens', label: 'Viagens', icon: Route },
  { to: '/abastecimentos', label: 'Abastecimentos', icon: Fuel },
  { to: '/relatorios', label: 'Relatórios', icon: BarChart3 },
]

export default function Sidebar() {
  const { isAdmin } = useAuth()

  return (
    <aside className="w-60 min-h-screen bg-gray-900 text-white flex flex-col shrink-0">
      <div className="px-5 py-6 border-b border-gray-700">
        <div className="flex items-center gap-2">
          <Truck size={22} className="text-blue-400" />
          <span className="font-bold text-lg tracking-tight">Health Drive</span>
        </div>
        <p className="text-xs text-gray-400 mt-1">Gestão de Frota</p>
      </div>

      <nav className="flex-1 py-4 px-3 space-y-0.5">
        {navItems.map(({ to, label, icon: Icon }) => (
          <NavLink
            key={to}
            to={to}
            end={to === '/'}
            className={({ isActive }) =>
              `flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                isActive
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-white'
              }`
            }
          >
            <Icon size={16} />
            {label}
          </NavLink>
        ))}

        {isAdmin && (
          <NavLink
            to="/usuarios"
            className={({ isActive }) =>
              `flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                isActive
                  ? 'bg-blue-600 text-white'
                  : 'text-gray-300 hover:bg-gray-800 hover:text-white'
              }`
            }
          >
            <UserCog size={16} />
            Usuários
          </NavLink>
        )}
      </nav>
    </aside>
  )
}
