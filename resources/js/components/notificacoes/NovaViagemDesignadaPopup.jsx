import { useState } from 'react'
import { createPortal } from 'react-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Truck } from 'lucide-react'
import * as solicitacoesApi from '../../api/solicitacoes'
import KmSaidaModal from './KmSaidaModal'
import MotivoRecusaModal from './MotivoRecusaModal'

export default function NovaViagemDesignadaPopup({ notificacao, temViagemAtiva, onFechar }) {
  const qc = useQueryClient()
  const [tela, setTela] = useState('inicial')
  const ehFila = !!notificacao.data?.fila

  const aceitarMutation = useMutation({
    mutationFn: (kmSaida) => solicitacoesApi.motoristaAceitar(notificacao.data.solicitacao_id, kmSaida),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['viagens'] })
      onFechar()
    },
  })

  const recusarMutation = useMutation({
    mutationFn: (motivo) => solicitacoesApi.motoristaRecusar(notificacao.data.solicitacao_id, motivo),
    onSuccess: onFechar,
  })

  if (tela === 'km' || (ehFila && tela !== 'recusar')) {
    return (
      <KmSaidaModal
        loading={aceitarMutation.isPending}
        onCancelar={ehFila ? undefined : () => setTela('inicial')}
        onConfirmar={(km) => aceitarMutation.mutate(km)}
      />
    )
  }

  if (tela === 'recusar') {
    return (
      <MotivoRecusaModal
        loading={recusarMutation.isPending}
        onCancelar={() => setTela('inicial')}
        onConfirmar={(motivo) => recusarMutation.mutate(motivo)}
      />
    )
  }

  const detalhe = notificacao.data?.detalhe

  return createPortal(
    <>
      <div className="fixed inset-0 bg-black/40 z-[100]" />
      <div className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] w-[calc(100%-2rem)] max-w-sm">
        <div className="bg-white rounded-2xl shadow-xl p-6 text-center">
          <div className="mx-auto mb-4 w-12 h-12 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center">
            <Truck size={24} />
          </div>
          <h2 className="text-base font-semibold text-navy-900 mb-1">Nova Viagem Designada pelo Gestor</h2>
          {detalhe && <p className="text-sm text-gray-500 mt-1">{detalhe}</p>}
          <div className="flex gap-2 mt-5">
            <button
              onClick={() => setTela('recusar')}
              className="flex-1 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-medium hover:bg-red-50"
            >
              Recusar
            </button>
            <button
              onClick={() => temViagemAtiva ? aceitarMutation.mutate(undefined) : setTela('km')}
              disabled={aceitarMutation.isPending}
              className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 disabled:opacity-60"
            >
              {aceitarMutation.isPending ? 'Confirmando...' : 'Aceitar'}
            </button>
          </div>
        </div>
      </div>
    </>,
    document.body
  )
}
