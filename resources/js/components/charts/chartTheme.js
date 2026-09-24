export const CHART_COLORS = [
  '#2a78d6', // azul
  '#eb6834', // laranja
  '#1baf7a', // aqua
  '#eda100', // amarelo
  '#e87ba4', // magenta
  '#008300', // verde
  '#4a3aa7', // violeta
  '#e34948', // vermelho
]

export function fmtNumero(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR')
}

export function fmtBrl(n) {
  if (n == null) return '—'
  return Number(n).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

export function fmtMinutos(min) {
  if (min == null) return '—'
  const horas = Math.floor(min / 60)
  const resto = min % 60
  if (horas === 0) return `${resto}min`
  return `${horas}h ${resto}min`
}

// Duração longa legível: "2d 3h", "5h 20min", "45min".
export function fmtDuracao(min) {
  if (min == null) return '—'
  const d = Math.floor(min / 1440)
  const h = Math.floor((min % 1440) / 60)
  const m = min % 60
  if (d) return h ? `${d}d ${h}h` : `${d}d`
  if (h) return m ? `${h}h ${m}min` : `${h}h`
  return `${m}min`
}
