import { useEffect, useState } from 'react'
import { createPortal } from 'react-dom'
import { Bell, X } from 'lucide-react'

const AUTO_FECHAR_MODAL_MS = 60_000
const AUTO_FECHAR_TOAST_MS = 6_000

export default function NovaSolicitacaoPopup({ notificacao, onFechar }) {
  const [toastVisivel, setToastVisivel] = useState(true)

  useEffect(() => {
    const timer = setTimeout(onFechar, AUTO_FECHAR_MODAL_MS)
    return () => clearTimeout(timer)
  }, [notificacao.id, onFechar])

  useEffect(() => {
    setToastVisivel(true)
    const timer = setTimeout(() => setToastVisivel(false), AUTO_FECHAR_TOAST_MS)
    return () => clearTimeout(timer)
  }, [notificacao.id])

  const titulo = notificacao.data?.solicitante_nome ?? 'Nova solicitação de transporte'
  const detalhe = notificacao.data?.detalhe

  return createPortal(
    <>
      <div className="fixed inset-0 bg-black/40 z-[100] flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 text-center">
          <div className="mx-auto mb-4 w-12 h-12 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center">
            <Bell size={24} />
          </div>
          <h2 className="text-base font-semibold text-navy-900 mb-1">Nova solicitação de transporte</h2>
          <p className="text-sm text-gray-600">{titulo}</p>
          {detalhe && <p className="text-sm text-gray-500 mt-1">{detalhe}</p>}
          <button
            onClick={onFechar}
            className="mt-5 w-full py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700"
          >
            OK
          </button>
        </div>
      </div>

      {toastVisivel && (
        <div className="fixed top-4 right-4 z-[110] w-72 bg-white rounded-xl border border-gray-200 shadow-lg p-4 animate-in fade-in slide-in-from-top-2">
          <div className="flex items-start justify-between gap-2">
            <div>
              <p className="text-sm font-semibold text-navy-900">Nova solicitação de transporte</p>
              <p className="text-sm text-gray-600 mt-0.5">{titulo}</p>
              {detalhe && <p className="text-xs text-gray-400 mt-0.5">{detalhe}</p>}
            </div>
            <button onClick={() => setToastVisivel(false)} className="text-gray-400 hover:text-gray-600 shrink-0">
              <X size={16} />
            </button>
          </div>
        </div>
      )}
    </>,
    document.body
  )
}
