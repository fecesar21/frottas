import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as veiculosApi from '../../api/veiculos'
import Modal from '../ui/Modal'
import Alert from '../ui/Alert'
import { TIPOS_MANUTENCAO, primeiroErro } from './tipos'

const VAZIO = { tipo: '', motivo: '', km_entrada: '', km_chegada: '', local: '' }
const input = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400'

export const invalidarManutencao = (qc) =>
  ['veiculos', 'checkins', 'viagens', 'manutencoes', 'solicitacoes', 'dashboard']
    .forEach((k) => qc.invalidateQueries({ queryKey: [k] }))

/**
 * Entrada em manutenção (motorista ou gestor). Se o veículo tiver viagem em
 * andamento, a API pede o KM de chegada para finalizá-la antes.
 */
export default function ManutencaoModal({ veiculo, onClose, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState(VAZIO)
  const [pedirKmChegada, setPedirKmChegada] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    setForm(VAZIO)
    setPedirKmChegada(false)
    setError('')
  }, [veiculo?.id])

  const set = (campo) => (e) => setForm((f) => ({ ...f, [campo]: e.target.value }))

  const iniciar = useMutation({
    mutationFn: (data) => veiculosApi.iniciarManutencao(veiculo.id, data),
    onSuccess: (res) => {
      invalidarManutencao(qc)
      onSuccess?.(res.data?.data ?? res.data)
      onClose()
    },
    onError: (e) => {
      if (e.response?.data?.errors?.km_chegada) setPedirKmChegada(true)
      setError(primeiroErro(e, 'Erro ao registrar manutenção'))
    },
  })

  const enviar = (e) => {
    e.preventDefault()
    setError('')
    if (!form.tipo) {
      setError('Selecione o tipo de manutenção.')
      return
    }
    const num = (v) => (v === '' ? undefined : Number(v))
    iniciar.mutate({
      tipo: form.tipo,
      motivo: form.motivo.trim() || undefined,
      local: form.local.trim() || undefined,
      km_entrada: num(form.km_entrada),
      km_chegada: pedirKmChegada ? num(form.km_chegada) : undefined,
    })
  }

  return (
    <Modal open={!!veiculo} onClose={onClose} title="Levar para manutenção">
      <form onSubmit={enviar} className="space-y-4">
        <p className="text-sm text-gray-600">
          Veículo: <strong className="font-mono">{veiculo?.placa}</strong> — {veiculo?.modelo}
        </p>

        <fieldset>
          <legend className="block text-sm font-medium text-gray-700 mb-2">Tipo de manutenção</legend>
          <div className="grid grid-cols-2 gap-2">
            {TIPOS_MANUTENCAO.map((t) => (
              <button
                key={t.value}
                type="button"
                aria-pressed={form.tipo === t.value}
                onClick={() => setForm((f) => ({ ...f, tipo: t.value }))}
                className={`px-3 py-3 text-sm rounded-lg border text-left transition-colors ${form.tipo === t.value ? 'bg-yellow-500 border-yellow-500 text-white font-medium' : 'border-gray-300 text-gray-700 hover:border-yellow-400'}`}
              >
                {t.label}
              </button>
            ))}
          </div>
        </fieldset>

        <div>
          <label htmlFor="manutencao-motivo" className="block text-sm font-medium text-gray-700 mb-1">
            Descrição {form.tipo === 'outro' ? '' : '(opcional)'}
          </label>
          <input id="manutencao-motivo" type="text" maxLength={255} value={form.motivo} onChange={set('motivo')}
            required={form.tipo === 'outro'} placeholder="Ex.: barulho na suspensão dianteira" className={input} />
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <label htmlFor="manutencao-km" className="block text-sm font-medium text-gray-700 mb-1">KM atual</label>
            <input id="manutencao-km" type="number" min={veiculo?.km_atual ?? 0} value={form.km_entrada} onChange={set('km_entrada')}
              placeholder={veiculo?.km_atual != null ? String(veiculo.km_atual) : ''} className={input} />
          </div>
          <div>
            <label htmlFor="manutencao-local" className="block text-sm font-medium text-gray-700 mb-1">Oficina / local</label>
            <input id="manutencao-local" type="text" maxLength={150} value={form.local} onChange={set('local')} className={input} />
          </div>
        </div>

        {pedirKmChegada && (
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 space-y-2">
            <p className="text-sm text-amber-800">Há uma viagem em andamento com este veículo. Ela será finalizada.</p>
            <label htmlFor="manutencao-km-chegada" className="block text-sm font-medium text-gray-700">KM de chegada da viagem</label>
            <input id="manutencao-km-chegada" type="number" min={0} required value={form.km_chegada} onChange={set('km_chegada')} className={input} />
          </div>
        )}

        {error && <Alert type="error" message={error} />}

        <p className="text-xs text-gray-500">
          Enquanto estiver em manutenção, o veículo não recebe solicitações de transporte nem registra viagens.
        </p>

        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
            Cancelar
          </button>
          <button type="submit" disabled={iniciar.isPending} className="px-4 py-2 text-sm rounded-lg bg-yellow-500 text-white hover:bg-yellow-600 disabled:opacity-60">
            {iniciar.isPending ? 'Salvando...' : 'Confirmar manutenção'}
          </button>
        </div>
      </form>
    </Modal>
  )
}
