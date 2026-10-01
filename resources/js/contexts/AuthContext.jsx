import { createContext, useContext, useState, useCallback, useEffect } from 'react'
import * as authApi from '../api/auth'

const AuthContext = createContext(null)

const readStorage = () => {
  try {
    const stored = localStorage.getItem('hd_user')
    return stored ? JSON.parse(stored) : null
  } catch {
    return null
  }
}

// Motoristas com check-in duplo podem ter até 2 check-ins ativos; os demais, 1.
const checkinsDoUsuario = (u) =>
  u?.checkins_ativos ?? (u?.checkin_ativo ? [u.checkin_ativo] : [])

export function AuthProvider({ children }) {
  const [user, setUser] = useState(readStorage)
  const [checkinsAtivos, setCheckinsAtivosState] = useState(() => checkinsDoUsuario(readStorage()))

  const setCheckinsAtivos = useCallback((lista) => {
    setCheckinsAtivosState(lista)
    setUser(prev => {
      const updated = { ...prev, checkin_ativo: lista[0] ?? null, checkins_ativos: lista }
      localStorage.setItem('hd_user', JSON.stringify(updated))
      return updated
    })
  }, [])

  // Adiciona um check-in recém-criado; com null, limpa todos.
  const setCheckinAtivo = useCallback((checkin) => {
    setCheckinsAtivos(checkin
      ? [...checkinsAtivos.filter(c => c.id !== checkin.id), checkin]
      : [])
  }, [checkinsAtivos, setCheckinsAtivos])

  const removerCheckinAtivo = useCallback((id) => {
    setCheckinsAtivos(checkinsAtivos.filter(c => c.id !== id))
  }, [checkinsAtivos, setCheckinsAtivos])

  const refreshUser = useCallback(async () => {
    try {
      const { data } = await authApi.me()
      localStorage.setItem('hd_user', JSON.stringify(data.user))
      setUser(data.user)
      setCheckinsAtivosState(checkinsDoUsuario(data.user))
    } catch {}
  }, [])

  // Sincroniza estado do checkin do operador ao carregar a página
  useEffect(() => {
    const storedUser = readStorage()
    if (storedUser?.perfil === 'operador') {
      refreshUser()
    }
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const login = useCallback(async (credentials) => {
    const { data } = await authApi.login(credentials)
    localStorage.setItem('hd_token', data.token)
    localStorage.setItem('hd_user', JSON.stringify(data.user))
    setUser(data.user)
    setCheckinsAtivosState(checkinsDoUsuario(data.user))
    return data.user
  }, [])

  const logout = useCallback(async () => {
    try { await authApi.logout() } catch { /* ignora erro de rede */ }
    localStorage.removeItem('hd_token')
    localStorage.removeItem('hd_user')
    setUser(null)
    setCheckinsAtivosState([])
  }, [])

  const permiteCheckinDuplo = !!user?.permite_checkin_duplo

  return (
    <AuthContext.Provider value={{
      user,
      login,
      logout,
      isAdmin: user?.perfil === 'admin',
      isGestor: ['admin', 'gestor'].includes(user?.perfil),
      isOperador: user?.perfil === 'operador',
      checkinAtivo: checkinsAtivos[0] ?? null,
      checkinsAtivos,
      permiteCheckinDuplo,
      limiteCheckins: permiteCheckinDuplo ? 2 : 1,
      setCheckinAtivo,
      removerCheckinAtivo,
      refreshUser,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

export const useAuth = () => {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth deve ser usado dentro de AuthProvider')
  return ctx
}
