// Modo kiosk do Dashboard Gerencial: tela cheia via Fullscreen API.
// Navegadores só permitem entrar em tela cheia a partir de um gesto do
// usuário (ex.: o clique em "Entrar"), por isso é chamado no submit do login.
export function entrarModoKiosk() {
  const el = document.documentElement
  if (document.fullscreenElement || !el.requestFullscreen) return
  el.requestFullscreen().catch(() => { /* navegador recusou; o painel oferece botão */ })
}

export function sairModoKiosk() {
  if (document.fullscreenElement && document.exitFullscreen) {
    document.exitFullscreen().catch(() => {})
  }
}

// Cor do tempo médio autorização → fim da viagem frente ao acordo de 30 min:
// verde < 25, laranja entre 25 e 30, vermelho > 30.
export function corDoTempo(minutos, acordo = 30) {
  if (minutos == null) return 'neutro'
  if (minutos > acordo) return 'vermelho'
  if (minutos >= acordo - 5) return 'laranja'
  return 'verde'
}

export function formatarMinutos(minutos) {
  if (minutos == null) return '—'
  const total = Math.round(minutos)
  if (total < 60) return `${total} min`
  const h = Math.floor(total / 60)
  const m = total % 60
  return m ? `${h}h ${String(m).padStart(2, '0')}min` : `${h}h`
}
