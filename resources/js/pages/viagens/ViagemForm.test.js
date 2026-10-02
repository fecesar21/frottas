import { describe, it, expect } from 'vitest'
import { motivosDoVeiculo } from './ViagemForm'

const valores = (v) => motivosDoVeiculo(v).map(m => m.value)

describe('motivosDoVeiculo', () => {
  it('ambulância não oferece motivos administrativos', () => {
    expect(valores({ modelo: 'AMBULÂNCIA SPRINTER' })).toEqual(['tfd', 'transferencia_paciente'])
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

describe('ordem dos motivos', () => {
  it('lista os motivos em ordem alfabética', () => {
    const labels = motivosDoVeiculo(null).map(m => m.label)
    expect(labels).toEqual([...labels].sort((a, b) => a.localeCompare(b, 'pt-BR')))
  })
})
