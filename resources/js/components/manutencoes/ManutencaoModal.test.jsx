import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import ManutencaoModal from './ManutencaoModal'
import * as veiculosApi from '../../api/veiculos'

vi.mock('../../api/veiculos', () => ({ iniciarManutencao: vi.fn() }))

const veiculo = { id: 'v1', placa: 'ABC-1234', modelo: 'Spin', km_atual: 1000 }

const renderModal = (props = {}) => {
  const qc = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
  return render(
    <QueryClientProvider client={qc}>
      <ManutencaoModal veiculo={veiculo} onClose={vi.fn()} {...props} />
    </QueryClientProvider>,
  )
}

describe('ManutencaoModal', () => {
  beforeEach(() => vi.clearAllMocks())

  it('exige o tipo de manutenção antes de enviar', () => {
    renderModal()

    fireEvent.click(screen.getByText('Confirmar manutenção'))

    expect(screen.getByText('Selecione o tipo de manutenção.')).toBeInTheDocument()
    expect(veiculosApi.iniciarManutencao).not.toHaveBeenCalled()
  })

  it('envia o tipo selecionado e o KM informado', async () => {
    veiculosApi.iniciarManutencao.mockResolvedValue({ data: { data: veiculo } })
    const onClose = vi.fn()
    renderModal({ onClose })

    fireEvent.click(screen.getByText('Troca de pneus'))
    fireEvent.change(screen.getByLabelText('KM atual'), { target: { value: '1050' } })
    fireEvent.click(screen.getByText('Confirmar manutenção'))

    await waitFor(() => expect(onClose).toHaveBeenCalled())
    expect(veiculosApi.iniciarManutencao).toHaveBeenCalledWith('v1', expect.objectContaining({ tipo: 'troca_pneus', km_entrada: 1050 }))
  })

  it('pede o KM de chegada quando há viagem em andamento', async () => {
    veiculosApi.iniciarManutencao.mockRejectedValueOnce({
      response: { data: { errors: { km_chegada: ['Há uma viagem em andamento com este veículo. Informe o KM de chegada para finalizá-la.'] } } },
    })
    renderModal()

    fireEvent.click(screen.getByText('Mecânica'))
    fireEvent.click(screen.getByText('Confirmar manutenção'))

    expect(await screen.findByLabelText('KM de chegada da viagem')).toBeInTheDocument()

    veiculosApi.iniciarManutencao.mockResolvedValue({ data: { data: veiculo } })
    fireEvent.change(screen.getByLabelText('KM de chegada da viagem'), { target: { value: '1080' } })
    fireEvent.click(screen.getByText('Confirmar manutenção'))

    await waitFor(() => expect(veiculosApi.iniciarManutencao).toHaveBeenLastCalledWith('v1', expect.objectContaining({ km_chegada: 1080 })))
  })
})
