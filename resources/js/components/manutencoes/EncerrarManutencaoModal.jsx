import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as veiculosApi from '../../api/veiculos'
import Modal from '../ui/Modal'
import Alert from '../ui/Alert'
import { invalidarManutencao } from './ManutencaoModal'
import { primeiroErro, rotuloTipo, tempoDecorrido } from './tipos'

const input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400'

export default function EncerrarManutencaoModal({ veiculo, onClose }) {
  const qc = useQueryClient()
  const [kmSaida, setKmSaida] = useState('')
  const [observacao, setObservacao] = useState('')
  const [error, setError] = useState('')

  useEffect(() => {
    setKmSaida('')
    setObservacao('')
    setError('')
  }, [veiculo?.id])

  const encerrar = useMutation({
    mutationFn: (data) => veiculosApi.encerrarManutencao(veiculo.id, data),
    onSuccess: () => {
      invalidarManutencao(qc)
      onClose()
    },
    onError: (e) => setError(primeiroErro(e, 'Erro ao retirar da manutenção')),
  })

  const atual = veiculo?.manutencao_atual

  return (
    <Modal open={!!veiculo} onClose={onClose} title="Retirar da manutenção" size="sm">
      <form
        onSubmit={(e) => {
          e.preventDefault()
          encerrar.mutate({
            km_saida: kmSaida === '' ? undefined : Number(kmSaida),
            observacao_saida: observacao.trim() || undefined,
          })
        }}
        className="space-y-4"
      >
        <p className="text-sm text-gray-600">
          Veículo: <strong className="font-mono">{veiculo?.placa}</strong>
          {atual && <> — {rotuloTipo(atual.tipo)} há {tempoDecorrido(atual.inicio)}</>}
        </p>
        <div>
          <label htmlFor="manutencao-km-saida" className="block text-sm font-medium text-gray-700 mb-1">KM na saída</label>
          <input id="manutencao-km-saida" type="number" min={atual?.km_entrada ?? veiculo?.km_atual ?? 0} value={kmSaida}
            onChange={(e) => setKmSaida(e.target.value)} placeholder={String(veiculo?.km_atual ?? '')} className={input} />
        </div>
        <div>
          <label htmlFor="manutencao-obs" className="block text-sm font-medium text-gray-700 mb-1">O que foi feito (opcional)</label>
          <textarea id="manutencao-obs" rows={3} maxLength={255} value={observacao} onChange={(e) => setObservacao(e.target.value)} className={input} />
        </div>
        {error && <Alert type="error" message={error} />}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
            Cancelar
          </button>
          <button type="submit" disabled={encerrar.isPending} className="px-4 py-2 text-sm rounded-lg bg-green-600 text-white hover:bg-green-700 disabled:opacity-60">
            {encerrar.isPending ? 'Salvando...' : 'Retirar da manutenção'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
