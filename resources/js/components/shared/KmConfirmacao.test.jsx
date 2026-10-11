import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import KmConfirmacao, { KmEco, ehAlertaKm, camposConfirmacaoKm } from './KmConfirmacao'

describe('KmConfirmacao', () => {
  it('ecoa o KM com separador de milhar e a distância', () => {
    render(<KmEco valor="654320" referencia={65432} />)
    expect(screen.getByText(/654\.320 km/)).toBeTruthy()
    expect(screen.getByText(/Distância: 588\.888 km/)).toBeTruthy()
  })

  it('só aparece com alerta e exibe a mensagem do backend', () => {
    const { container } = render(<KmConfirmacao alerta={null} justificativa="" onJustificativa={() => {}} />)
    expect(container.innerHTML).toBe('')
    render(<KmConfirmacao alerta={{ message: 'KM fora do esperado' }} justificativa="" onJustificativa={() => {}} />)
    expect(screen.getByText('KM fora do esperado')).toBeTruthy()
  })

  it('identifica a resposta de confirmação e monta os campos', () => {
    expect(ehAlertaKm({ response: { status: 422, data: { km_confirmacao_necessaria: true } } })).toBe(true)
    expect(ehAlertaKm({ response: { status: 422, data: {} } })).toBe(false)
    expect(camposConfirmacaoKm(null, 'x')).toEqual({})
    expect(camposConfirmacaoKm({}, ' ok ')).toEqual({ confirmar_km: true, justificativa_km: 'ok' })
  })
})
