import api from '../api/axios'

// Web Push: avisa o motorista de novas solicitações mesmo com o app fechado.
// Exige HTTPS (ou localhost) e, no iPhone, o app instalado na tela inicial.

export const pushSuportado = () =>
  typeof window !== 'undefined' &&
  'serviceWorker' in navigator &&
  'PushManager' in window &&
  'Notification' in window

const base64UrlParaUint8 = (base64) => {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4)
  const bruto = atob((base64 + padding).replace(/-/g, '+').replace(/_/g, '/'))
  return Uint8Array.from(bruto, c => c.charCodeAt(0))
}

const inscricaoAtual = async () => {
  const reg = await navigator.serviceWorker.ready
  return { reg, inscricao: await reg.pushManager.getSubscription() }
}

// Pede permissão (deve ser chamado a partir de um toque do usuário) e
// registra o aparelho no servidor. Retorna a permissão final.
export async function ativarPush() {
  if (!pushSuportado()) return 'unsupported'
  const permissao = await Notification.requestPermission()
  if (permissao !== 'granted') return permissao
  await sincronizarPush()
  return permissao
}

// Com permissão já concedida, garante que a inscrição existe e está gravada
// para o usuário logado (o mesmo aparelho pode trocar de motorista).
export async function sincronizarPush() {
  if (!pushSuportado() || Notification.permission !== 'granted') return
  let { reg, inscricao } = await inscricaoAtual()
  if (!inscricao) {
    const { data } = await api.get('/push/chave-publica')
    if (!data?.chave) return
    inscricao = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: base64UrlParaUint8(data.chave),
    })
  }
  await api.post('/push/inscricao', inscricao.toJSON())
}

// No logout: desvincula o aparelho do usuário, para que avisos dele não
// cheguem a quem usar o celular depois.
export async function desativarPush() {
  if (!pushSuportado()) return
  try {
    const { inscricao } = await inscricaoAtual()
    if (!inscricao) return
    await api.delete('/push/inscricao', { data: { endpoint: inscricao.endpoint } })
    await inscricao.unsubscribe()
  } catch { /* sem rede: o servidor descarta endpoints inválidos sozinho */ }
}
