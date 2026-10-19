import { useEffect, useState } from 'react'
import { useAuth } from '../../contexts/AuthContext'
import { ativarPush, pushSuportado, sincronizarPush } from '../../lib/push'

// Faixa que convida o motorista a ativar as notificações no celular. Some
// quando a permissão já foi concedida (aí só sincroniza em silêncio) ou negada.
export default function AvisoAtivarPush() {
  const { isOperador } = useAuth()
  const [permissao, setPermissao] = useState(() => (pushSuportado() ? Notification.permission : 'unsupported'))
  const [ativando, setAtivando] = useState(false)

  useEffect(() => {
    if (isOperador && permissao === 'granted') sincronizarPush().catch(() => {})
  }, [isOperador, permissao])

  if (!isOperador) return null

  // Bloqueado: o navegador não deixa pedir de novo, só o motorista pode
  // liberar nas configurações do site. Sem este aviso ele fica sem push sem saber.
  if (permissao === 'denied') {
    return (
      <div className="bg-amber-50 border-b border-amber-200 text-amber-800 text-xs px-4 py-2">
        As notificações estão bloqueadas neste celular e você não será avisado de novas viagens com o app fechado.
        Para liberar: toque no cadeado ao lado do endereço → <strong>Permissões</strong> → <strong>Notificações</strong> → <strong>Permitir</strong>, e recarregue a página.
      </div>
    )
  }

  if (permissao !== 'default') return null

  const ativar = async () => {
    setAtivando(true)
    try {
      setPermissao(await ativarPush())
    } catch {
      setPermissao(Notification.permission)
    } finally {
      setAtivando(false)
    }
  }

  return (
    <div className="bg-sky-50 border-b border-sky-200 text-sky-800 text-xs px-4 py-2 flex items-center justify-between gap-3">
      <span>Ative as notificações para ser avisado de novas viagens mesmo com o app fechado.</span>
      <button
        type="button"
        onClick={ativar}
        disabled={ativando}
        className="shrink-0 rounded-lg bg-sky-600 text-white px-3 py-1.5 font-medium disabled:opacity-60"
      >
        {ativando ? 'Ativando…' : 'Ativar'}
      </button>
    </div>
  )
}
