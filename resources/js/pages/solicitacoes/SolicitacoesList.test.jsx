import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import SolicitacoesList from './SolicitacoesList'
import * as solicitacoesApi from '../../api/solicitacoes'

vi.mock('../../api/solicitacoes')
vi.mock('../../hooks/useNotificacoes', () => ({ useNotificacoes: () => ({ marcarTodasLidas: () => {} }) }))

const solicitacao = { id: 's1', status: 'aberto', motivo: 'tfd', usuario_nome: 'Maria', criado_em: '2026-10-01T10:00:00' }

function renderList() {
  return render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <SolicitacoesList />
    </QueryClientProvider>
  )
}

describe('SolicitacoesList — recusa pela gestão', () => {
  beforeEach(() => {
    solicitacoesApi.listar.mockResolvedValue({ data: { data: [solicitacao] } })
    solicitacoesApi.recusar.mockResolvedValue({ data: {} })
  })

  it('abre o modal e envia o motivo da recusa', async () => {
    renderList()

    fireEvent.click((await screen.findAllByRole('button', { name: 'Recusar' }))[0])
    const confirmar = screen.getByRole('button', { name: 'Confirmar recusa' })
    expect(confirmar).toBeDisabled()

    fireEvent.change(screen.getByLabelText('Motivo da recusa *'), { target: { value: 'Sem veículo disponível' } })
    fireEvent.click(confirmar)

    await waitFor(() => expect(solicitacoesApi.recusar).toHaveBeenCalledWith('s1', 'Sem veículo disponível'))
  })
})
