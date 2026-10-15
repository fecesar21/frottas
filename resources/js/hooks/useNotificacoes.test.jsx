import { describe, it, expect, vi, beforeEach } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useNotificacoes } from './useNotificacoes'
import * as notificacoesApi from '../api/notificacoes'

vi.mock('../contexts/AuthContext', () => ({
  useAuth: () => ({ user: { id: 'u1', perfil: 'operador', motorista_id: 'm1' } }),
}))
vi.mock('../api/notificacoes')

const convite = { id: 'n1', type: 'App\\Notifications\\NovaViagemDesignada', data: { solicitacao_id: 's1' } }
const aviso = { id: 'n2', type: 'App\\Notifications\\SolicitacaoCancelada', data: { solicitacao_id: 's2' } }

function renderizar() {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const wrapper = ({ children }) => <QueryClientProvider client={qc}>{children}</QueryClientProvider>
  return renderHook(() => useNotificacoes(), { wrapper })
}

describe('useNotificacoes', () => {
  beforeEach(() => {
    window.localStorage.clear()
    notificacoesApi.naoLidas.mockResolvedValue({ data: { total: 2, notificacoes: [convite, aviso] } })
  })

  it('reexibe o convite ao motorista após recarregar a página, mas não avisos comuns', async () => {
    const primeira = renderizar()
    await waitFor(() => expect(primeira.result.current.pendentes).toHaveLength(2))
    primeira.unmount()

    const recarregada = renderizar()
    await waitFor(() => expect(recarregada.result.current.pendentes.map(n => n.id)).toEqual(['n1']))
  })
})
