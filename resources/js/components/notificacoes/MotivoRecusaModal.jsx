import { useState } from 'react'

export default function MotivoRecusaModal({ onConfirmar, onCancelar, loading, erro: erroExterno }) {
  const [motivo, setMotivo] = useState('')
  const [erro, setErro] = useState('')
  const erroExibido = erro || erroExterno

  const submeter = (e) => {
    e.preventDefault()
    if (!motivo.trim()) {
      setErro('Informe o motivo da recusa.')
      return
    }
    onConfirmar(motivo.trim())
  }

  return (
    <div className="fixed inset-0 z-[120] flex items-center justify-center bg-black/40">
      <form onSubmit={submeter} className="bg-white rounded-2xl shadow-xl p-6 w-[calc(100%-2rem)] max-w-sm">
        <h2 className="text-base font-semibold text-navy-900 mb-1">Motivo da recusa</h2>
        <textarea
          autoFocus
          rows={4}
          value={motivo}
          onChange={e => { setMotivo(e.target.value); setErro('') }}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
          placeholder="Descreva o motivo..."
        />
        {erroExibido && <p className="text-xs text-red-600 mt-1">{erroExibido}</p>}
        <div className="flex gap-2 mt-5">
          <button type="button" onClick={onCancelar} className="flex-1 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
            Voltar
          </button>
          <button type="submit" disabled={loading} className="flex-1 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-60">
            {loading ? 'Enviando...' : 'Confirmar recusa'}
          </button>
        </div>
      </form>
    </div>
  )
}
