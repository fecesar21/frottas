// Nome de exibição do motivo: `motivo_nome` vem da API (cadastro de motivos); cai para o código.
export const rotuloMotivo = (item) => item?.motivo_nome ?? item?.motivo ?? 'Viagem'

// Complemento que identifica a solicitação: trajeto quando houver origem/destino,
// senão o campo específico do motivo (cidade, hospital, fornecedor).
export function detalheSolicitacao(s) {
  if (s.origem || s.destino) return `${s.origem ?? '—'} → ${s.destino ?? '—'}`
  return s.cidade ?? s.hospital_destino ?? s.fornecedor_nome ?? null
}
