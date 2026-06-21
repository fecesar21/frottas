import { Menu } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'

const perfilLabel = { admin: 'Administrador', gestor: 'Gestor', operador: 'Operador' }

export default function Header({ title, onMenuClick }) {
  const { user } = useAuth()

  return (
    <header className="h-14 bg-white/80 backdrop-blur-sm border-b border-gray-200/80 flex items-center justify-between px-5 shrink-0 shadow-sm sticky top-0 z-20">
      <div className="flex items-center gap-3">
        <button
          onClick={onMenuClick}
          className="md:hidden p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800"
        >
          <Menu size={20} />
        </button>
        <h1 className="text-base font-semibold text-navy-900 tracking-tight">{title}</h1>
      </div>

      <div className="flex items-center gap-2">
        <div className="hidden sm:flex items-center gap-2 text-sm text-gray-600">
          <div className="w-7 h-7 rounded-full bg-brand-100 text-brand-700 flex items-center justify-center text-xs font-bold">
            {user?.nome?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?'}
          </div>
          <span className="font-medium text-gray-700">{user?.nome?.split(' ')[0]}</span>
          <span className="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">
            {perfilLabel[user?.perfil] ?? user?.perfil}
          </span>
        </div>
      </div>
    </header>
  )
}
