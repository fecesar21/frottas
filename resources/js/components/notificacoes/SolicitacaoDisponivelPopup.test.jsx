import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import SolicitacaoDisponivelPopup from './SolicitacaoDisponivelPopup'
import * as solicitacoesApi from '../../api/solicitacoes'

vi.mock('../../api/solicitacoes', () => ({ assumir: vi.fn() }))

function renderPopup({ temViagemAtiva = false, onFechar = () => {} } = {}) {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <SolicitacaoDisponivelPopup
        notificacao={{ id: 'n1', data: { solicitacao_id: 's1', motivo: 'buscar_medico', detalhe: 'Cidade Teste', solicitante_nome: 'Ana' } }}
        temViagemAtiva={temViagemAtiva}
        onFechar={onFechar}
      />
    </QueryClientProvider>
  )
}

describe('SolicitacaoDisponivelPopup', () => {
  it('exibe motivo, detalhe e ações', () => {
    renderPopup()

    expect(screen.getByText('Buscar médico em outra cidade')).toBeInTheDocument()
    expect(screen.getByText('Cidade Teste')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Assumir' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Dispensar' })).toBeInTheDocument()
  })

  it('com viagem ativa assume direto e mostra erro quando outro motorista já assumiu', async () => {
    solicitacoesApi.assumir.mockRejectedValueOnce({
      response: { data: { errors: { status: ['Esta solicitação já foi assumida ou tratada.'] } } },
    })
    renderPopup({ temViagemAtiva: true })

    fireEvent.click(screen.getByRole('button', { name: 'Assumir' }))

    await waitFor(() => expect(screen.getByText('Esta solicitação já foi assumida ou tratada.')).toBeInTheDocument())
    expect(solicitacoesApi.assumir).toHaveBeenCalledWith('s1', undefined)
  })
})
