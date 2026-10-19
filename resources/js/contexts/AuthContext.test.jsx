import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AuthProvider, useAuth } from './AuthContext'
import * as authApi from '../api/auth'

vi.mock('../api/auth', () => ({
  login: vi.fn(),
  logout: vi.fn(),
}))

const NOTIF = ['notificacoes', 'nao-lidas']

function Botoes() {
  const { login, logout } = useAuth()
  return (
    <>
      <button onClick={() => login({ usuario: '2', senha: '1' })}>entrar</button>
      <button onClick={() => logout()}>sair</button>
    </>
  )
}

function renderComCache() {
  const qc = new QueryClient()
  qc.setQueryData(NOTIF, { total: 1, notificacoes: [{ id: 'convite-do-motorista-anterior' }] })
  render(
    <QueryClientProvider client={qc}>
      <AuthProvider><Botoes /></AuthProvider>
    </QueryClientProvider>
  )
  return qc
}

describe('AuthContext', () => {
  beforeEach(() => {
    localStorage.clear()
    authApi.logout.mockResolvedValue({})
    authApi.login.mockResolvedValue({ data: { token: 't', user: { id: 'u2', perfil: 'operador' } } })
  })

  it('logout descarta o cache do usuário anterior', async () => {
    const qc = renderComCache()

    fireEvent.click(screen.getByText('sair'))

    await waitFor(() => expect(qc.getQueryData(NOTIF)).toBeUndefined())
  })

  it('login descarta notificações em cache de outra sessão', async () => {
    const qc = renderComCache()

    fireEvent.click(screen.getByText('entrar'))

    await waitFor(() => expect(localStorage.getItem('hd_token')).toBe('t'))
    expect(qc.getQueryData(NOTIF)).toBeUndefined()
  })
})
