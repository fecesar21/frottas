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

export function AuthProvider({ children }) {
  const [user, setUser] = useState(readStorage)
  const [checkinAtivo, setCheckinAtivoState] = useState(() => readStorage()?.checkin_ativo ?? null)

  const setCheckinAtivo = useCallback((checkin) => {
    setCheckinAtivoState(checkin)
    setUser(prev => {
      const updated = { ...prev, checkin_ativo: checkin }
      localStorage.setItem('hd_user', JSON.stringify(updated))
      return updated
    })
  }, [])

  const refreshUser = useCallback(async () => {
    try {
      const { data } = await authApi.me()
      localStorage.setItem('hd_user', JSON.stringify(data.user))
      setUser(data.user)
      setCheckinAtivoState(data.user.checkin_ativo ?? null)
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
    setCheckinAtivoState(data.user.checkin_ativo ?? null)
    return data.user
  }, [])

  const logout = useCallback(async () => {
    try { await authApi.logout() } catch { /* ignora erro de rede */ }
    localStorage.removeItem('hd_token')
    localStorage.removeItem('hd_user')
    setUser(null)
    setCheckinAtivoState(null)
  }, [])

  return (
    <AuthContext.Provider value={{
      user,
      login,
      logout,
      isAdmin: user?.perfil === 'admin',
      isGestor: ['admin', 'gestor'].includes(user?.perfil),
      isOperador: user?.perfil === 'operador',
      checkinAtivo,
      setCheckinAtivo,
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
