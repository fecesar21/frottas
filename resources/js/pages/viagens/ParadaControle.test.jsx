import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import ParadaControle from './ParadaControle'
import * as viagensApi from '../../api/viagens'

vi.mock('../../api/viagens', () => ({ registrarParada: vi.fn(), retomarViagem: vi.fn() }))
vi.mock('../../api/localidades', () => ({ listar: vi.fn(() => Promise.resolve({ data: [] })) }))

const renderControle = (viagem) => render(
  <QueryClientProvider client={new QueryClient({ defaultOptions: { mutations: { retry: false } } })}>
    <ParadaControle viagem={viagem} />
  </QueryClientProvider>,
)

describe('ParadaControle', () => {
  beforeEach(() => vi.clearAllMocks())

  it('registra parada com local e motivo', async () => {
    viagensApi.registrarParada.mockResolvedValue({ data: {} })
    renderControle({ id: 'v1', paradas: [] })

    fireEvent.click(screen.getByRole('button', { name: /Parada/ }))
    fireEvent.change(screen.getByLabelText('Local *'), { target: { value: 'Farmácia' } })
    fireEvent.change(screen.getByLabelText('Motivo'), { target: { value: 'Buscar remédio' } })
    fireEvent.click(screen.getByRole('button', { name: 'Registrar parada' }))

    await waitFor(() => expect(viagensApi.registrarParada).toHaveBeenCalledWith('v1', { local: 'Farmácia', motivo: 'Buscar remédio' }))
  })

  it('com parada aberta mostra aviso e retoma a viagem', async () => {
    viagensApi.retomarViagem.mockResolvedValue({ data: {} })
    renderControle({ id: 'v1', paradas: [{ id: 'p1', local: 'FARMÁCIA', chegada_at: '2026-10-14T10:00:00', saida_at: null }] })

    expect(screen.getByText(/Parado em FARMÁCIA/)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /Retomar viagem/ }))

    await waitFor(() => expect(viagensApi.retomarViagem).toHaveBeenCalledWith('v1', 'p1'))
  })
})
