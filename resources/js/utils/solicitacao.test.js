import { describe, it, expect } from 'vitest'
import { rotuloMotivo, detalheSolicitacao } from './solicitacao'

describe('utils de solicitação', () => {
  it('usa o nome do motivo vindo da API e cai para o código', () => {
    expect(rotuloMotivo({ motivo: 'tfd', motivo_nome: 'TFD' })).toBe('TFD')
    expect(rotuloMotivo({ motivo: 'outro' })).toBe('outro')
    expect(rotuloMotivo({})).toBe('Viagem')
    expect(rotuloMotivo(undefined)).toBe('Viagem')
  })

  it('prefere o trajeto e cai para o campo específico do motivo', () => {
    expect(detalheSolicitacao({ origem: 'UPA', destino: 'HRA' })).toBe('UPA → HRA')
    expect(detalheSolicitacao({ cidade: 'Cidade Teste' })).toBe('Cidade Teste')
    expect(detalheSolicitacao({ fornecedor_nome: 'Fornecedor X' })).toBe('Fornecedor X')
    expect(detalheSolicitacao({ motivo: 'tfd' })).toBeNull()
  })
})
