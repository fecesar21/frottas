// Rótulos dos motivos de viagem/solicitação — manter igual a Viagem::ROTULOS_MOTIVO (backend).
export const MOTIVOS_SOLICITACAO = {
  alimentacao: 'Alimentação (Levar/Buscar)',
  buscar_material_fornecedor: 'Buscar Materiais em Fornecedor',
  buscar_medico: 'Buscar Médico em Outra Cidade',
  material_outro_hospital: 'Levar Material em Outro Hospital',
  tfd: 'TFD',
  transferencia_paciente: 'Transferência de Paciente',
  servicos_administrativos: 'Serviços Administrativos Diversos',
  transporte_colaborador: 'Transporte de Colaborador(es)',
}

export const rotuloMotivo = (motivo) => MOTIVOS_SOLICITACAO[motivo] ?? motivo ?? 'Viagem'

// Opções de select em ordem alfabética do rótulo; `valores` restringe a quais motivos.
export const opcoesMotivo = (valores = Object.keys(MOTIVOS_SOLICITACAO)) =>
  valores
    .map(value => ({ value, label: MOTIVOS_SOLICITACAO[value] }))
    .sort((a, b) => a.label.localeCompare(b.label, 'pt-BR'))

// Complemento que identifica a solicitação: trajeto quando houver origem/destino,
// senão o campo específico do motivo (cidade, hospital, fornecedor).
export function detalheSolicitacao(s) {
  if (s.origem || s.destino) return `${s.origem ?? '—'} → ${s.destino ?? '—'}`
  return s.cidade ?? s.hospital_destino ?? s.fornecedor_nome ?? null
}
