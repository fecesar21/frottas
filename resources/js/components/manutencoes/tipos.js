export const TIPOS_MANUTENCAO = [
  { value: 'troca_pneus', label: 'Troca de pneus' },
  { value: 'troca_oleo', label: 'Troca de óleo do motor' },
  { value: 'revisao', label: 'Revisão' },
  { value: 'mecanica', label: 'Mecânica' },
  { value: 'funilaria_pintura', label: 'Funilaria / Pintura' },
  { value: 'outro', label: 'Outro' },
]

export const rotuloTipo = (tipo) => TIPOS_MANUTENCAO.find(t => t.value === tipo)?.label ?? 'Não informado'

export function tempoDecorrido(isoDate) {
  if (!isoDate) return ''
  const diff = Math.max(0, Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000))
  if (diff < 3600) return `${Math.floor(diff / 60)}min`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h${String(Math.floor((diff % 3600) / 60)).padStart(2, '0')}`
  return `${Math.floor(diff / 86400)}d ${Math.floor((diff % 86400) / 3600)}h`
}

export const primeiroErro = (e, padrao) => {
  const errors = e.response?.data?.errors
  return (errors ? Object.values(errors)[0]?.[0] : undefined) ?? e.response?.data?.message ?? padrao
}
