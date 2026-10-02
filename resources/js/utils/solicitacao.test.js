import { describe, it, expect } from 'vitest'
import { rotuloMotivo, detalheSolicitacao } from './solicitacao'

describe('utils de solicitação', () => {
  it('traduz o motivo e mantém valores desconhecidos', () => {
    expect(rotuloMotivo('tfd')).toBe('TFD')
    expect(rotuloMotivo('buscar_medico')).toBe('Buscar Médico em Outra Cidade')
    expect(rotuloMotivo('outro')).toBe('outro')
  })

  it('prefere o trajeto e cai para o campo específico do motivo', () => {
    expect(detalheSolicitacao({ origem: 'UPA', destino: 'HRA' })).toBe('UPA → HRA')
    expect(detalheSolicitacao({ cidade: 'Cidade Teste' })).toBe('Cidade Teste')
    expect(detalheSolicitacao({ fornecedor_nome: 'Fornecedor X' })).toBe('Fornecedor X')
    expect(detalheSolicitacao({ motivo: 'tfd' })).toBeNull()
  })
})
