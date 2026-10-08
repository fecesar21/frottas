import { describe, it, expect } from 'vitest'
import { motivosDoVeiculo } from './ViagemForm'

const motivos = [
  { codigo: 'tfd', nome: 'TFD', tipo_veiculo: 'ambulancia' },
  { codigo: 'alimentacao', nome: 'Alimentação', tipo_veiculo: 'administrativo' },
  { codigo: 'lavar', nome: 'Lavar', tipo_veiculo: 'ambos' },
]
const codigos = (v) => motivosDoVeiculo(motivos, v).map(m => m.codigo)

describe('motivosDoVeiculo', () => {
  it('ambulância vê ambulância + ambos', () => {
    expect(codigos({ modelo: 'AMBULÂNCIA SPRINTER' })).toEqual(['tfd', 'lavar'])
  })
  it('administrativo vê administrativo + ambos', () => {
    expect(codigos({ modelo: 'STRADA' })).toEqual(['alimentacao', 'lavar'])
  })
  it('sem veículo mostra todos', () => {
    expect(codigos(null)).toHaveLength(3)
  })
})
