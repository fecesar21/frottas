import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { Truck, Lock, ArrowLeft } from 'lucide-react'
import Alert from '../components/ui/Alert'
import { redefinirSenha } from '../api/auth'

export default function RedefinirSenha() {
  const [searchParams] = useSearchParams()
  const navigate = useNavigate()
  const email = searchParams.get('email') ?? ''
  const token = searchParams.get('token') ?? ''

  const [senha, setSenha] = useState('')
  const [confirmacao, setConfirmacao] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [sucesso, setSucesso] = useState(false)

  const linkInvalido = !email || !token

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')

    if (senha !== confirmacao) {
      setError('As senhas não coincidem.')
      return
    }

    setLoading(true)
    try {
      await redefinirSenha({ email, token, senha })
      setSucesso(true)
      setTimeout(() => navigate('/login'), 2500)
    } catch (err) {
      setError(err.response?.data?.message ?? 'Não foi possível redefinir a senha. Tente novamente.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-navy-950 via-navy-900 to-brand-950 flex items-center justify-center p-4 bg-dot-pattern">
      <div className="absolute top-0 left-1/4 w-96 h-96 bg-brand-500/10 rounded-full blur-3xl pointer-events-none" />
      <div className="absolute bottom-0 right-1/4 w-80 h-80 bg-navy-700/40 rounded-full blur-3xl pointer-events-none" />

      <div className="relative w-full max-w-sm animate-fade-in">
        <div className="bg-white/95 backdrop-blur-md rounded-3xl shadow-2xl overflow-hidden">
          <div className="h-1.5 bg-gradient-to-r from-brand-400 via-brand-500 to-brand-600" />

          <div className="p-8">
            <div className="flex flex-col items-center mb-8">
              <div className="bg-gradient-to-br from-brand-400 to-brand-600 rounded-2xl p-4 shadow-lg shadow-brand-500/30 mb-4">
                <Truck size={32} className="text-white" />
              </div>
              <h1 className="text-2xl font-bold text-gray-900 tracking-tight">Nova senha</h1>
              <p className="text-sm text-gray-400 mt-1 text-center">
                Defina a nova senha de acesso.
              </p>
            </div>

            {linkInvalido ? (
              <Alert type="error" message="Link de redefinição inválido ou incompleto. Solicite um novo link." />
            ) : sucesso ? (
              <Alert type="success" message="Senha redefinida com sucesso! Redirecionando para o login..." />
            ) : (
              <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">
                    Nova senha
                  </label>
                  <div className="relative">
                    <Lock size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                      type="password"
                      required
                      autoFocus
                      inputMode="numeric"
                      pattern="[0-9]*"
                      minLength={6}
                      value={senha}
                      onChange={(e) => setSenha(e.target.value)}
                      className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400 placeholder-gray-300"
                      placeholder="••••••"
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1.5">
                    Confirmar nova senha
                  </label>
                  <div className="relative">
                    <Lock size={16} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input
                      type="password"
                      required
                      inputMode="numeric"
                      pattern="[0-9]*"
                      minLength={6}
                      value={confirmacao}
                      onChange={(e) => setConfirmacao(e.target.value)}
                      className="w-full border border-gray-200 rounded-xl pl-10 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400/40 focus:border-brand-400 placeholder-gray-300"
                      placeholder="••••••"
                    />
                  </div>
                </div>

                {error && <Alert type="error" message={error} />}

                <button
                  type="submit"
                  disabled={loading}
                  className="w-full bg-gradient-to-r from-brand-500 to-brand-700 hover:brightness-110 disabled:opacity-60 text-white font-semibold py-3 rounded-xl transition-all duration-150 text-sm mt-2 shadow-md shadow-brand-500/25"
                >
                  {loading ? 'Salvando...' : 'Redefinir senha'}
                </button>
              </form>
            )}

            <Link
              to="/login"
              className="mt-6 flex items-center justify-center gap-1.5 text-sm text-gray-500 hover:text-gray-700"
            >
              <ArrowLeft size={14} />
              Voltar para o login
            </Link>
          </div>
        </div>

        <p className="text-center text-xs text-white/20 mt-6">
          © {new Date().getFullYear()} Health Drive
        </p>
      </div>
    </div>
  )
}
