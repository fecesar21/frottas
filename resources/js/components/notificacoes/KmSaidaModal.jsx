import { useState } from 'react'
import { createPortal } from 'react-dom'

export default function KmSaidaModal({ onConfirmar, onCancelar, loading, erro: erroExterno }) {
  const [km, setKm] = useState('')
  const [erro, setErro] = useState('')
  const erroExibido = erro || erroExterno

  const submeter = (e) => {
    e.preventDefault()
    const valor = Number(km)
    if (!km || Number.isNaN(valor) || valor < 0) {
      setErro('Informe um KM válido.')
      return
    }
    onConfirmar(valor)
  }

  return createPortal(
    <>
      <div className="fixed inset-0 bg-black/40 z-[120]" />
      <div className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[121] w-[calc(100%-2rem)] max-w-sm">
        <form onSubmit={submeter} className="bg-white rounded-2xl shadow-xl p-6">
          <h2 className="text-base font-semibold text-navy-900 mb-1">Informe o KM de saída</h2>
          <p className="text-sm text-gray-600 mb-4">Necessário para iniciar a viagem.</p>
          <input
            type="number"
            inputMode="numeric"
            min="0"
            autoFocus
            value={km}
            onChange={e => { setKm(e.target.value); setErro('') }}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="Ex: 12345"
          />
          {erroExibido && <p className="text-xs text-red-600 mt-1">{erroExibido}</p>}
          <div className="flex gap-2 mt-5">
            {onCancelar && (
              <button type="button" onClick={onCancelar} className="flex-1 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">
                Cancelar
              </button>
            )}
            <button type="submit" disabled={loading} className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 disabled:opacity-60">
              {loading ? 'Iniciando...' : 'Iniciar viagem'}
            </button>
          </div>
        </form>
      </div>
    </>,
    document.body
  )
}
