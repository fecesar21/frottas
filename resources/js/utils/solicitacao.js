export const MOTIVOS_SOLICITACAO = {
  transferencia_paciente: 'Transferência de Paciente',
  buscar_medico: 'Buscar médico em outra cidade',
  material_outro_hospital: 'Levar material em outro hospital',
  transporte_colaborador: 'Transporte de colaborador(es)',
  buscar_material_fornecedor: 'Buscar materiais em fornecedor',
  tfd: 'TFD',
  alimentacao: 'Alimentação (Levar/Buscar)',
}

export const rotuloMotivo = (motivo) => MOTIVOS_SOLICITACAO[motivo] ?? motivo ?? 'Viagem'

// Complemento que identifica a solicitação: trajeto quando houver origem/destino,
// senão o campo específico do motivo (cidade, hospital, fornecedor).
export function detalheSolicitacao(s) {
  if (s.origem || s.destino) return `${s.origem ?? '—'} → ${s.destino ?? '—'}`
  return s.cidade ?? s.hospital_destino ?? s.fornecedor_nome ?? null
}
