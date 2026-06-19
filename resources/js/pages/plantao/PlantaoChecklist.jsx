import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { CheckCircle, XCircle, MinusCircle, Check } from 'lucide-react'
import * as plantaoApi from '../../api/plantao'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import { useState } from 'react'

const resultadoMap = {
  ok:        { icon: CheckCircle, color: 'text-green-600 bg-green-50 border-green-200', label: 'OK' },
  pendencia: { icon: XCircle,     color: 'text-red-600 bg-red-50 border-red-200', label: 'Pendência' },
  na:        { icon: MinusCircle, color: 'text-gray-500 bg-gray-50 border-gray-200', label: 'N/A' },
}

export default function PlantaoChecklist({ plantaoId, onClose }) {
  const qc = useQueryClient()
  const [error, setError] = useState('')

  const { data: plantao, isLoading } = useQuery({
    queryKey: ['plantao', plantaoId],
    queryFn: () => plantaoApi.buscar(plantaoId).then(r => r.data.data ?? r.data),
  })

  const atualizarItem = useMutation({
    mutationFn: ({ itemModeloId, resultado }) => plantaoApi.atualizarItem(plantaoId, { item_modelo_id: itemModeloId, resultado }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['plantao', plantaoId] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao salvar item'),
  })

  const finalizar = useMutation({
    mutationFn: () => plantaoApi.finalizar(plantaoId, {}),
    onSuccess: onClose,
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao finalizar'),
  })

  if (isLoading) return <LoadingSpinner />

  const respostas = plantao?.respostas ?? []
  const isFinalizado = !!plantao?.finalizado_at

  const getResposta = (itemId) => respostas.find(r => r.item_modelo_id === itemId)

  const categorias = {}
  for (const r of respostas) {
    const cat = r.item_modelo?.categoria?.nome ?? 'Geral'
    if (!categorias[cat]) categorias[cat] = []
    categorias[cat].push(r)
  }

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex items-center justify-between text-sm text-gray-600 bg-gray-50 rounded-lg p-3">
        <span>Veículo: <strong>{plantao?.veiculo?.placa}</strong></span>
        <span>OK: <strong className="text-green-600">{plantao?.itens_ok}</strong> | Pendências: <strong className="text-red-600">{plantao?.itens_pendencia}</strong></span>
        {isFinalizado && <span className="text-green-700 font-medium">✓ Finalizado</span>}
      </div>

      {Object.entries(categorias).map(([cat, itens]) => (
        <div key={cat}>
          <h4 className="text-sm font-semibold text-gray-700 mb-2 uppercase tracking-wide">{cat}</h4>
          <div className="space-y-2">
            {itens.map((r) => {
              const atual = r.resultado ?? 'na'
              return (
                <div key={r.id} className="flex items-center justify-between bg-white border rounded-lg px-4 py-3">
                  <span className="text-sm text-gray-700">{r.item_modelo?.titulo ?? '—'}</span>
                  <div className="flex items-center gap-2">
                    {Object.entries(resultadoMap).map(([val, { icon: Icon, color, label }]) => (
                      <button
                        key={val}
                        disabled={isFinalizado}
                        onClick={() => atualizarItem.mutate({ itemModeloId: r.item_modelo_id, resultado: val })}
                        className={`flex items-center gap-1 px-2.5 py-1 rounded-lg border text-xs font-medium transition-colors disabled:cursor-not-allowed ${
                          atual === val ? color : 'text-gray-400 border-gray-200 hover:bg-gray-50'
                        }`}
                      >
                        <Icon size={13} />
                        {label}
                      </button>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      ))}

      {!isFinalizado && (
        <div className="flex justify-end pt-2">
          <button onClick={() => finalizar.mutate()} disabled={finalizar.isPending}
            className="flex items-center gap-2 bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60">
            <Check size={16} />
            {finalizar.isPending ? 'Finalizando...' : 'Finalizar plantão'}
          </button>
        </div>
      )}
    </div>
  )
}
