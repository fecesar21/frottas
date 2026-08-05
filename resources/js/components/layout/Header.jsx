import { useState, useEffect, useRef } from 'react'
import { Menu, Bell } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import { useNotificacoes } from '../../hooks/useNotificacoes'

const perfilLabel = { admin: 'Administrador', gestor: 'Gestor', operador: 'Operador' }

export default function Header({ title, onMenuClick }) {
  const { user } = useAuth()
  const { total, notificacoes } = useNotificacoes()
  const [painelAberto, setPainelAberto] = useState(false)
  const containerRef = useRef(null)

  useEffect(() => {
    if (!painelAberto) return

    const handleClickOutside = (event) => {
      if (containerRef.current && !containerRef.current.contains(event.target)) {
        setPainelAberto(false)
      }
    }

    const handleEscapeKey = (event) => {
      if (event.key === 'Escape') {
        setPainelAberto(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    document.addEventListener('keydown', handleEscapeKey)

    return () => {
      document.removeEventListener('mousedown', handleClickOutside)
      document.removeEventListener('keydown', handleEscapeKey)
    }
  }, [painelAberto])

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
        {(user?.perfil === 'admin' || user?.perfil === 'gestor') && (
          <div className="relative" ref={containerRef}>
            <button
              onClick={() => setPainelAberto(v => !v)}
              aria-label="Notificações"
              className={`relative p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-800 ${total > 0 ? 'animate-pulse text-brand-600' : ''}`}
            >
              <Bell size={20} />
              {total > 0 && (
                <span className="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] leading-none rounded-full w-4 h-4 flex items-center justify-center">
                  {total > 9 ? '9+' : total}
                </span>
              )}
            </button>
            {painelAberto && (
              <div className="absolute right-0 mt-2 w-72 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden z-30">
                <div className="px-4 py-2 text-xs font-semibold text-gray-500 border-b border-gray-100">Notificações</div>
                <div className="max-h-80 overflow-y-auto divide-y divide-gray-100">
                  {notificacoes.length === 0 && (
                    <div className="px-4 py-6 text-center text-sm text-gray-400">Nenhuma notificação nova</div>
                  )}
                  {notificacoes.map(n => (
                    <div key={n.id} className="px-4 py-3 text-sm">
                      <p className="font-medium text-gray-800">{n.data?.solicitante_nome ?? 'Nova solicitação'}</p>
                      <p className="text-gray-500 text-xs">{n.data?.detalhe}</p>
                    </div>
                  ))}
                </div>
                <div className="px-4 py-2 text-xs text-gray-400 border-t border-gray-100">
                  Acesse Solicitações de Transporte para tratar e marcar como lido.
                </div>
              </div>
            )}
          </div>
        )}
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
