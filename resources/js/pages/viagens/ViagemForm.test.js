import { describe, it, expect } from 'vitest'
import { motivosDoVeiculo } from './ViagemForm'

const valores = (v) => motivosDoVeiculo(v).map(m => m.value)

describe('motivosDoVeiculo', () => {
  it('ambulância não oferece motivos administrativos', () => {
    expect(valores({ modelo: 'AMBULÂNCIA SPRINTER' })).toEqual(['transferencia_paciente', 'tfd'])
  })
  it('administrativo troca paciente/TFD por alimentação', () => {
    const v = valores({ modelo: 'STRADA' })
    expect(v).not.toContain('transferencia_paciente')
    expect(v).not.toContain('tfd')
    expect(v).toContain('alimentacao')
  })
  it('sem veículo mostra todos', () => {
    expect(valores(null)).toHaveLength(7)
  })
})
