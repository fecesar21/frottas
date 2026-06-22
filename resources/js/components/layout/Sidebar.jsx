import { NavLink } from 'react-router-dom'
import {
  LayoutDashboard, Truck, Users, CalendarDays, LogIn, GitBranch,
  Route, Fuel, Gauge, BarChart3, UserCog, LogOut, Menu, X
} from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import { useNavigate } from 'react-router-dom'

const sections = [
  {
    label: 'PRINCIPAL',
    items: [
      { to: '/', label: 'Dashboard', icon: LayoutDashboard },
    ],
  },
  {
    label: 'FROTA',
    items: [
      { to: '/veiculos', label: 'Veículos', icon: Truck },
      { to: '/motoristas', label: 'Motoristas', icon: Users },
      { to: '/escalas', label: 'Escalas', icon: CalendarDays },
    ],
  },
  {
    label: 'OPERAÇÕES',
    items: [
      { to: '/checkins', label: 'Check-ins', icon: LogIn },
      { to: '/plantao', label: 'Plantão', icon: GitBranch },
      { to: '/viagens', label: 'Viagens', icon: Route },
      { to: '/abastecimentos', label: 'Abastecimentos', icon: Fuel },
    ],
  },
  {
    label: 'ANÁLISES',
    items: [
      { to: '/relatorios', label: 'Relatórios', icon: BarChart3 },
    ],
  },
]

const perfil = { admin: 'Admin', gestor: 'Gestor', operador: 'Operador' }

export default function Sidebar({ open, onClose }) {
  const { user, isAdmin, isOperador, checkinAtivo, logout } = useAuth()
  const navigate = useNavigate()

  // Rotas visíveis para operadores dependem do estado do checkin
  const rotasPermitidas = isOperador
    ? (checkinAtivo ? ['/checkins', '/viagens', '/abastecimentos'] : ['/checkins'])
    : null

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  const initials = user?.nome
    ? user.nome.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
    : '?'

  return (
    <>
      {/* Mobile overlay */}
      {open && (
        <div
          className="fixed inset-0 z-30 bg-navy-950/70 backdrop-blur-sm md:hidden"
          onClick={onClose}
        />
      )}

      <aside
        className={`
          fixed top-0 left-0 z-40 h-screen w-60 flex flex-col
          bg-gradient-to-b from-navy-950 to-navy-800
          transform transition-transform duration-300 ease-in-out
          md:static md:translate-x-0 md:z-auto md:shrink-0
          ${open ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        {/* Logo */}
        <div className="px-5 py-5 border-b border-white/10">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="bg-brand-500/20 border border-brand-400/30 rounded-xl p-2 sidebar-glow">
                <Truck size={20} className="text-brand-300" />
              </div>
              <div>
                <span className="font-bold text-base text-white tracking-tight">Health Drive</span>
                <p className="text-[10px] text-brand-400 font-medium uppercase tracking-widest">Gestão de Frota</p>
              </div>
            </div>
            <button
              onClick={onClose}
              className="md:hidden text-white/50 hover:text-white p-1 rounded-lg hover:bg-white/10"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        {/* Nav */}
        <nav className="flex-1 overflow-y-auto py-4 px-3 space-y-5">
          {sections.map((section) => {
            const itensVisiveis = rotasPermitidas
              ? section.items.filter(item => rotasPermitidas.includes(item.to))
              : section.items
            if (itensVisiveis.length === 0) return null
            return (
              <div key={section.label}>
                <p className="text-[10px] font-semibold text-white/30 tracking-widest px-3 mb-1.5 uppercase">
                  {section.label}
                </p>
                <div className="space-y-0.5">
                  {itensVisiveis.map(({ to, label, icon: Icon }) => (
                    <NavLink
                      key={to}
                      to={to}
                      end={to === '/'}
                      onClick={onClose}
                      className={({ isActive }) =>
                        `flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group
                        ${isActive
                          ? 'bg-brand-500/15 text-brand-300 border-l-2 border-brand-400 pl-[10px]'
                          : 'text-white/60 hover:bg-white/10 hover:text-white/90 border-l-2 border-transparent hover:translate-x-0.5'
                        }`
                      }
                    >
                      <Icon size={16} className="shrink-0" />
                      {label}
                    </NavLink>
                  ))}
                </div>
              </div>
            )
          })}

          {isAdmin && (
            <div>
              <p className="text-[10px] font-semibold text-white/30 tracking-widest px-3 mb-1.5 uppercase">
                ADMIN
              </p>
              <NavLink
                to="/usuarios"
                onClick={onClose}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                  ${isActive
                    ? 'bg-brand-500/15 text-brand-300 border-l-2 border-brand-400 pl-[10px]'
                    : 'text-white/60 hover:bg-white/10 hover:text-white/90 border-l-2 border-transparent hover:translate-x-0.5'
                  }`
                }
              >
                <UserCog size={16} className="shrink-0" />
                Usuários
              </NavLink>
            </div>
          )}
        </nav>

        {/* User footer */}
        <div className="px-3 py-4 border-t border-white/10">
          <div className="flex items-center gap-3 px-2 mb-2">
            <div className="w-8 h-8 rounded-full bg-brand-500/30 border border-brand-400/40 flex items-center justify-center text-brand-300 text-xs font-bold shrink-0">
              {initials}
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium text-white/90 truncate">{user?.nome}</p>
              <p className="text-[10px] text-white/40">{perfil[user?.perfil] ?? user?.perfil}</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-white/50 hover:text-red-400 hover:bg-red-500/10 transition-all duration-150"
          >
            <LogOut size={15} />
            Sair
          </button>
        </div>
      </aside>
    </>
  )
}
