import { useNavigate } from 'react-router-dom'
import { ShieldCheck, ChevronRight } from 'lucide-react'

const secoes = [
  {
    to: '/configuracoes/ldap',
    titulo: 'LDAP por Unidade',
    descricao: 'Configure a conexão com o Active Directory de cada unidade para permitir login de solicitantes via rede.',
    icon: ShieldCheck,
  },
]

export default function ConfiguracoesHub() {
  const navigate = useNavigate()

  return (
    <div className="space-y-3">
      {secoes.map(({ to, titulo, descricao, icon: Icon }) => (
        <div
          key={to}
          onClick={() => navigate(to)}
          className="flex items-center justify-between bg-white rounded-xl border border-gray-200 px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
        >
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-lg bg-brand-50 flex items-center justify-center">
              <Icon size={18} className="text-brand-600" />
            </div>
            <div>
              <p className="text-sm font-medium text-gray-800">{titulo}</p>
              <p className="text-xs text-gray-400 mt-0.5">{descricao}</p>
            </div>
          </div>
          <ChevronRight size={16} className="text-gray-300 shrink-0" />
        </div>
      ))}
    </div>
  )
}
