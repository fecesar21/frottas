import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import NovaViagemDesignadaPopup from './NovaViagemDesignadaPopup'

function renderPopup(data) {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <NovaViagemDesignadaPopup notificacao={{ id: 'n1', data }} temViagemAtiva={false} onFechar={() => {}} />
    </QueryClientProvider>
  )
}

describe('NovaViagemDesignadaPopup', () => {
  it('exibe o motivo da viagem e o detalhe', () => {
    renderPopup({ solicitacao_id: 's1', motivo: 'buscar_medico', detalhe: 'Cidade Teste' })

    expect(screen.getByText('Buscar Médico em Outra Cidade')).toBeInTheDocument()
    expect(screen.getByText('Cidade Teste')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Aceitar' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Recusar' })).toBeInTheDocument()
  })

  it('não mostra o texto genérico "Sem detalhe"', () => {
    renderPopup({ solicitacao_id: 's2', motivo: 'tfd', detalhe: 'Sem detalhe' })

    expect(screen.getByText('TFD')).toBeInTheDocument()
    expect(screen.queryByText('Sem detalhe')).not.toBeInTheDocument()
  })
})
