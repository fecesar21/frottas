// Proteção contra KM digitado errado (dígito a mais / trocado).
// O backend responde 422 com `km_confirmacao_necessaria` quando o KM está
// fora do esperado; o motorista confere e reenvia com justificativa.

export const fmtKm = (n) => (n === '' || n == null || Number.isNaN(Number(n)) ? '' : Number(n).toLocaleString('pt-BR'))

export const ehAlertaKm = (e) => e?.response?.status === 422 && !!e.response.data?.km_confirmacao_necessaria

export const camposConfirmacaoKm = (alerta, justificativa) =>
  alerta ? { confirmar_km: true, justificativa_km: justificativa.trim() } : {}

/** Eco do valor digitado com separador de milhar — torna visível um dígito a mais. */
export function KmEco({ valor, referencia, rotuloReferencia = 'Distância' }) {
  if (valor === '' || valor == null) return null
  const delta = referencia != null ? Number(valor) - Number(referencia) : null
  return (
    <p className="text-xs text-gray-500 mt-1">
      <span className="font-semibold text-gray-700">{fmtKm(valor)} km</span>
      {delta != null && delta >= 0 && <> · {rotuloReferencia}: {fmtKm(delta)} km</>}
    </p>
  )
}

export default function KmConfirmacao({ alerta, justificativa, onJustificativa }) {
  if (!alerta) return null
  return (
    <div className="rounded-lg border border-amber-300 bg-amber-50 p-3 space-y-2" role="alert">
      <p className="text-sm text-amber-800 font-medium">{alerta.message}</p>
      <label className="block text-xs font-medium text-amber-900">Justificativa *</label>
      <textarea rows={2} required minLength={5} maxLength={250} value={justificativa}
        onChange={e => onJustificativa(e.target.value)}
        placeholder="Ex.: confirmei no odômetro; viagem longa registrada depois"
        className="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500" />
    </div>
  )
}
