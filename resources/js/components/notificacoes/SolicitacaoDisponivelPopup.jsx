import { useState } from 'react'
import { createPortal } from 'react-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Truck } from 'lucide-react'
import * as solicitacoesApi from '../../api/solicitacoes'
import KmSaidaModal from './KmSaidaModal'
import { rotuloMotivo } from '../../utils/solicitacao'

// Solicitação aberta enviada a motoristas em atividade elegíveis: o primeiro que assumir fica com ela.
export default function SolicitacaoDisponivelPopup({ notificacao, temViagemAtiva, onFechar }) {
  const qc = useQueryClient()
  const [tela, setTela] = useState('inicial')
  const [erro, setErro] = useState('')

  const extrairErro = (e) => Object.values(e.response?.data?.errors ?? {}).flat()[0]
    ?? e.response?.data?.message
    ?? e.response?.data?.error
    ?? 'Ocorreu um erro. Tente novamente.'

  const assumirMutation = useMutation({
    mutationFn: (kmSaida) => solicitacoesApi.assumir(notificacao.data.solicitacao_id, kmSaida),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['viagens'] })
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      onFechar()
    },
    onError: (e) => {
      setErro(extrairErro(e))
      setTela('inicial')
    },
  })

  if (tela === 'km') {
    return (
      <KmSaidaModal
        loading={assumirMutation.isPending}
        erro={erro}
        onCancelar={() => setTela('inicial')}
        onConfirmar={(km) => assumirMutation.mutate(km)}
      />
    )
  }

  const detalhe = notificacao.data?.detalhe === 'Sem detalhe' ? null : notificacao.data?.detalhe

  return createPortal(
    <>
      <div className="fixed inset-0 bg-black/40 z-[100]" />
      <div className="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-[101] w-[calc(100%-2rem)] max-w-sm">
        <div className="bg-white rounded-2xl shadow-xl p-6 text-center">
          <div className="mx-auto mb-4 w-12 h-12 rounded-full bg-brand-100 text-brand-600 flex items-center justify-center">
            <Truck size={24} />
          </div>
          <h2 className="text-base font-semibold text-navy-900 mb-1">Nova Solicitação Disponível</h2>
          <p className="text-sm font-medium text-gray-700 mt-2">{rotuloMotivo(notificacao.data)}</p>
          {detalhe && <p className="text-sm text-gray-500">{detalhe}</p>}
          {notificacao.data?.solicitante_nome && (
            <p className="text-xs text-gray-400 mt-1">Solicitante: {notificacao.data.solicitante_nome}</p>
          )}
          {temViagemAtiva && (
            <p className="text-xs text-amber-600 mt-2">Você está em viagem: ao assumir, ela entra na sua fila.</p>
          )}
          {erro && <p className="text-sm text-red-600 mt-2">{erro}</p>}
          <div className="flex gap-2 mt-5">
            <button
              onClick={onFechar}
              className="flex-1 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-50"
            >
              Dispensar
            </button>
            <button
              onClick={() => { setErro(''); temViagemAtiva ? assumirMutation.mutate(undefined) : setTela('km') }}
              disabled={assumirMutation.isPending}
              className="flex-1 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 disabled:opacity-60"
            >
              {assumirMutation.isPending ? 'Assumindo...' : 'Assumir'}
            </button>
          </div>
        </div>
      </div>
    </>,
    document.body
  )
}
