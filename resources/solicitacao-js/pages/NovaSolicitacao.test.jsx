import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import NovaSolicitacao from './NovaSolicitacao'
import * as motivosApi from '../api/motivosViagem'
import * as pontosViagemApi from '../api/pontosViagem'

vi.mock('../api/motivosViagem')
vi.mock('../api/pontosViagem')
vi.mock('../api/solicitacoes')
vi.mock('../api/unidades')
vi.mock('../contexts/AuthContext', () => ({ useAuth: () => ({ user: { unidade_id: null } }) }))
vi.mock('react-router-dom', () => ({ useNavigate: () => vi.fn() }))
vi.mock('../components/Layout', () => ({ default: ({ children }) => <div>{children}</div> }))
vi.mock('../components/PainelFrota', () => ({ default: () => null }))

describe('NovaSolicitacao — motivos', () => {
  beforeEach(() => {
    pontosViagemApi.listar.mockResolvedValue({ data: { data: [] } })
  })

  it('lista os motivos liberados para solicitação', async () => {
    motivosApi.listar.mockResolvedValue({ data: { data: [{ codigo: 'tfd', nome: 'TFD' }] } })

    render(<NovaSolicitacao />)

    expect(await screen.findByText('TFD')).toBeInTheDocument()
    expect(motivosApi.listar).toHaveBeenCalledWith({ solicitacao: 1 })
  })

  it('avisa quando não consegue carregar os motivos', async () => {
    motivosApi.listar.mockRejectedValue(new Error('rede'))

    render(<NovaSolicitacao />)

    expect(await screen.findByText(/Não foi possível carregar os motivos/)).toBeInTheDocument()
  })

  it('avisa quando não há motivos disponíveis', async () => {
    motivosApi.listar.mockResolvedValue({ data: { data: [] } })

    render(<NovaSolicitacao />)

    expect(await screen.findByText(/Nenhum motivo disponível/)).toBeInTheDocument()
  })
})

describe('NovaSolicitacao — Exames', () => {
  it('mostra Origem e Destino para "Exames - Levar Paciente" mesmo com código diferente e sem unidade', async () => {
    pontosViagemApi.listar.mockResolvedValue({ data: [{ tipo: 'localidade', id: 'l1', nome: 'Clínica' }] })
    motivosApi.listar.mockResolvedValue({ data: { data: [{ codigo: 'levar_paciente', nome: 'Exames - Levar Paciente' }] } })

    render(<NovaSolicitacao />)
    fireEvent.click(await screen.findByLabelText('Exames - Levar Paciente'))

    expect(screen.getByText('Origem *')).toBeInTheDocument()
    expect(screen.getByText('Destino *')).toBeInTheDocument()
  })
})
