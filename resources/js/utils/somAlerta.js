// Sons de alerta gerados via Web Audio API (sem arquivos de áudio).

let ctxCompartilhado = null

function obterContexto() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext
  if (!AudioContextClass) return null
  if (!ctxCompartilhado || ctxCompartilhado.state === 'closed') {
    ctxCompartilhado = new AudioContextClass()
  }
  if (ctxCompartilhado.state === 'suspended') {
    ctxCompartilhado.resume().catch(() => {})
  }
  return ctxCompartilhado
}

function tocarNota(ctx, frequencia, inicio, duracao, volume) {
  // Fundamental + oitava dão um timbre de "sino" mais cheio que uma senoide pura.
  ;[[frequencia, 1], [frequencia * 2, 0.35]].forEach(([freq, peso]) => {
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.type = 'triangle'
    osc.frequency.value = freq
    gain.gain.setValueAtTime(0.0001, inicio)
    gain.gain.exponentialRampToValueAtTime(volume * peso, inicio + 0.02)
    gain.gain.exponentialRampToValueAtTime(0.0001, inicio + duracao)
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.start(inicio)
    osc.stop(inicio + duracao + 0.05)
  })
}

export function vibrar(padrao) {
  try {
    navigator.vibrate?.(padrao)
  } catch {
    // Vibração indisponível (desktop, iOS) — ignora.
  }
}

/** Bipe curto para notificações informativas. */
export function tocarBeep() {
  try {
    const ctx = obterContexto()
    if (!ctx) return
    tocarNota(ctx, 880, ctx.currentTime, 0.3, 0.15)
  } catch {
    // Ambiente sem suporte a Web Audio API (ex: alguns navegadores headless) — falha silenciosamente.
  }
}

/** Toque de chamada (arpejo ascendente, tocado duas vezes) + vibração, para convites ao motorista. */
export function tocarChamada() {
  vibrar([400, 150, 400, 150, 600])
  try {
    const ctx = obterContexto()
    if (!ctx) return
    const notas = [523.25, 659.25, 783.99, 1046.5] // Dó5, Mi5, Sol5, Dó6
    const t0 = ctx.currentTime
    for (let rep = 0; rep < 2; rep++) {
      notas.forEach((freq, i) => {
        const ultima = i === notas.length - 1
        tocarNota(ctx, freq, t0 + rep * 0.9 + i * 0.12, ultima ? 0.5 : 0.18, 0.35)
      })
    }
  } catch {
    // Ambiente sem suporte a Web Audio API — falha silenciosamente.
  }
}
