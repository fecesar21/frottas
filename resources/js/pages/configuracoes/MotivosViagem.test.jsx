import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import MotivosViagem from './MotivosViagem'
import * as motivosApi from '../../api/motivosViagem'

vi.mock('../../api/motivosViagem', () => ({
  listar: vi.fn(),
  criar: vi.fn(),
  atualizar: vi.fn(),
  excluir: vi.fn(),
}))

const dados = [
  { id: '1', codigo: 'tfd', nome: 'TFD', tipo_veiculo: 'ambulancia', disponivel_solicitacao: true, ativo: true, sistema: true, em_uso: true },
  { id: '2', codigo: 'lavar', nome: 'Lavar Veículo', tipo_veiculo: 'ambos', disponivel_solicitacao: false, ativo: true, sistema: false, em_uso: false },
  { id: '3', codigo: 'velho', nome: 'Antigo', tipo_veiculo: 'administrativo', disponivel_solicitacao: false, ativo: false, sistema: false, em_uso: true },
]

const renderPage = () => {
  const qc = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={qc}>
      <MotivosViagem />
    </QueryClientProvider>,
  )
}

describe('MotivosViagem', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    motivosApi.listar.mockResolvedValue({ data: { data: dados } })
  })

  it('lista ativos, mostra selo Sistema e Excluir só no não usado', async () => {
    renderPage()
    expect(await screen.findByText('TFD')).toBeInTheDocument()
    expect(screen.getByText('Sistema')).toBeInTheDocument()
    expect(screen.queryByText('Antigo')).not.toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: /excluir/i })).toHaveLength(1)
  })

  it('mostrar inativos exibe o inativo', async () => {
    renderPage()
    fireEvent.click(await screen.findByLabelText(/mostrar inativos/i))
    expect(screen.getByText('Antigo')).toBeInTheDocument()
  })

  it('exclui com confirmação em dois cliques', async () => {
    motivosApi.excluir.mockResolvedValue({})
    renderPage()
    fireEvent.click(await screen.findByRole('button', { name: /excluir/i }))
    expect(motivosApi.excluir).not.toHaveBeenCalled()
    fireEvent.click(screen.getByRole('button', { name: /confirmar exclusão/i }))
    await waitFor(() => expect(motivosApi.excluir).toHaveBeenCalledWith('2'))
  })
})
