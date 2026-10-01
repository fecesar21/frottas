import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import MinhasSolicitacoes from './MinhasSolicitacoes'
import * as solicitacoesApi from '../api/solicitacoes'

vi.mock('../api/solicitacoes')
vi.mock('../components/Layout', () => ({ default: ({ children }) => <div>{children}</div> }))

describe('MinhasSolicitacoes', () => {
  it('mostra a recusa pela gestão com o motivo', async () => {
    solicitacoesApi.listar.mockResolvedValue({ data: { data: [
      { id: 's1', status: 'recusada_gestao', motivo: 'tfd', motivo_recusa: 'Fora do horário', criado_em: '2026-10-01T10:00:00' },
    ] } })

    render(<MinhasSolicitacoes />)

    expect(await screen.findByText('Recusada')).toBeInTheDocument()
    expect(screen.getByText('Motivo: Fora do horário')).toBeInTheDocument()
  })
})
