import { useState } from 'react'
import { NavLink, Navigate, Outlet, useNavigate } from 'react-router-dom'
import { Truck, ClipboardPlus, ClipboardList, LogOut, Menu, X } from 'lucide-react'
import { useAuth } from '../contexts/AuthContext'

export function PrivateRoute() {
  const { user } = useAuth()
  if (!user) return <Navigate to="/login" replace />
  return <Outlet />
}

const items = [
  { to: '/', label: 'Nova Solicitação', icon: ClipboardPlus },
  { to: '/minhas-solicitacoes', label: 'Minhas Solicitações', icon: ClipboardList },
]

export default function Layout({ children }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const [open, setOpen] = useState(false)

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="min-h-screen bg-gray-50 md:flex">
      {open && (
        <div
          className="fixed inset-0 z-30 bg-navy-950/70 backdrop-blur-sm md:hidden"
          onClick={() => setOpen(false)}
        />
      )}

      <aside
        className={`
          fixed top-0 left-0 z-40 h-screen w-60 flex flex-col
          bg-gradient-to-b from-navy-950 to-navy-800
          transform transition-transform duration-300 ease-in-out
          md:sticky md:top-0 md:translate-x-0 md:z-auto md:shrink-0
          ${open ? 'translate-x-0' : '-translate-x-full'}
        `}
      >
        <div className="px-5 py-5 border-b border-white/10">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="bg-brand-500/20 border border-brand-400/30 rounded-xl p-2">
                <Truck size={20} className="text-brand-300" />
              </div>
              <div>
                <span className="font-bold text-base text-white tracking-tight">Health Drive</span>
                <p className="text-[10px] text-brand-400 font-medium uppercase tracking-widest">Solicitar Transporte</p>
              </div>
            </div>
            <button
              onClick={() => setOpen(false)}
              className="md:hidden text-white/50 hover:text-white p-1 rounded-lg hover:bg-white/10"
            >
              <X size={18} />
            </button>
          </div>
        </div>

        <nav className="flex-1 py-4 px-3 space-y-0.5">
          {items.map(({ to, label, icon: Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/'}
              onClick={() => setOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150
                ${isActive
                  ? 'bg-brand-500/15 text-brand-300 border-l-2 border-brand-400 pl-[10px]'
                  : 'text-white/60 hover:bg-white/10 hover:text-white/90 border-l-2 border-transparent'
                }`
              }
            >
              <Icon size={16} className="shrink-0" />
              {label}
            </NavLink>
          ))}
        </nav>

        <div className="px-3 py-4 border-t border-white/10">
          <p className="text-sm font-medium text-white/90 truncate px-2 mb-2">{user?.nome}</p>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm text-white/50 hover:text-red-400 hover:bg-red-500/10 transition-all duration-150"
          >
            <LogOut size={15} />
            Sair
          </button>
        </div>
      </aside>

      <div className="flex-1 min-w-0 flex flex-col">
        <div className="md:hidden flex items-center gap-3 px-4 py-3 bg-white border-b border-gray-100 sticky top-0 z-20">
          <button
            onClick={() => setOpen(true)}
            className="text-gray-500 hover:text-gray-800 p-1.5 rounded-lg hover:bg-gray-100"
          >
            <Menu size={20} />
          </button>
          <span className="font-semibold text-sm text-gray-800">Health Drive</span>
        </div>

        <main className="flex-1 p-6 md:p-8 max-w-3xl mx-auto w-full">{children}</main>
      </div>
    </div>
  )
}
