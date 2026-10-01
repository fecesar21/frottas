// Cabeçalho, título e detalhe de uma notificação para o sino e o popup genérico.
export function descreverNotificacao(n) {
  const d = n?.data ?? {}

  if (d.tipo === 'veiculo_entrou_manutencao' || d.tipo === 'veiculo_saiu_manutencao') {
    return {
      cabecalho: d.tipo === 'veiculo_entrou_manutencao' ? 'Veículo em manutenção' : 'Veículo saiu da manutenção',
      titulo: `${d.placa ?? 'Veículo'} – ${d.tipo_manutencao_label ?? 'Manutenção'}`,
      detalhe: [d.motivo, d.responsavel && `por ${d.responsavel}`].filter(Boolean).join(' · '),
    }
  }

  if (d.tipo === 'solicitacao_recusada_gestao') {
    return {
      cabecalho: 'Solicitação recusada',
      titulo: `Recusada por ${d.gestor_nome ?? 'gestão'}`,
      detalhe: d.motivo_recusa,
    }
  }

  return {
    cabecalho: 'Nova solicitação de transporte',
    titulo: d.solicitante_nome ?? 'Nova solicitação de transporte',
    detalhe: d.detalhe,
  }
}
