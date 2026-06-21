import { useState } from 'react'
import { useNavigate, Navigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'
import { Truck, User, Lock } from 'lucide-react'
import Alert from '../components/ui/Alert'

export default function Login() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ usuario: '', senha: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (user) return <Navigate to="/" replace />

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      await login(form)
     // redirecionamento feito pela guarda if(user) no topo do componente
    } catch (err) {
      setError(err.response?.data?.error ?? 'Usuário ou senha inválidos.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-navy-950 via-navy-900 to-brand-950 flex items-center justify-center p-4 bg-dot-pattern">
      {/* Decorative blobs */}
      <div className="absolute top-0 left-1/4 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-0 right-1/4 w-80 h-80 bg-navy-700/40 rounded-full blur-3xl pointer-events-none" />

      <div className="relative w-full max-w-sm animate-fade-in">
        {/* Card */}
        <div className="bg-white/95 backdrop-blur-md rounded-3xl shadow-2xl overflow-hidden">
          {/* Top accent */}
          <div className="h-1.5 bg-gradient-to-r from-brand-400 via-brand-500 to-brand-600" />

          <div className="p-8">
            {/* Logo */}
            <div className="flex flex-col items-center mb-8">
              <div className="bg-gradient-to-br from-brand-400 to-brand-600 rounded-2xl p-4 shadow-lg shadow-brand-500/30 mb-4">
                <Truck size={32} className="text-white" />
              </div>
              <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Health Drive</h1>
              <p className="text-sm text-gray-400 mt-1">Sistema de Gestão de Frota</p>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                  Usuário ou e-mail
                </label>
                <div className="relative">
                  <User size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="text"
                    required
                    autoFocus
                    value={form.usuario}
                    onChange={(e) => setForm(f => ({ ...f, usuario: e.target.value }))}
                    className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400 placeholder-gray-300"
                    placeholder="admin"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1.5">
                  Senha
                </label>
                <div className="relative">
                  <Lock size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                  <input
                    type="password"
                    required
                    value={form.senha}
                    onChange={(e) => setForm(f => ({ ...f, senha: e.target.value }))}
                    className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400 placeholder-gray-300"
                    placeholder="••••••••"
                  />
                </div>
              </div>

              {error && <Alert type="error" message={error} />}

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-gradient-to-r from-brand-500 to-brand-700 hover:brightness-110 disabled:opacity-60 text-white font-semibold py-3 rounded-xl transition-all duration-150 text-sm mt-2 shadow-md shadow-brand-500/25"
              >
                {loading ? 'Entrando...' : 'Entrar'}
              </button>
            </form>
          </div>
        </div>

        <p className="text-center text-xs text-white/20 mt-6">
          © {new Date().getFullYear()} Health Drive
        </p>
      </div>
    </div>
  )
}
