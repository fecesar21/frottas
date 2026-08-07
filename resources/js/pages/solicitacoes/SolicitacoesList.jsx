import { useState, useEffect } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { format } from 'date-fns'
import * as solicitacoesApi from '../../api/solicitacoes'
import * as motoristasApi from '../../api/motoristas'
import * as veiculosApi from '../../api/veiculos'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import { useNotificacoes } from '../../hooks/useNotificacoes'

const MOTIVOS = {
  transferencia_paciente: 'Transferência de Paciente',
  buscar_medico: 'Buscar médico em outra cidade',
  material_outro_hospital: 'Levar Material em outro Hospital',
  transporte_colaborador: 'Transporte de Colaborador(es)',
  buscar_material_fornecedor: 'Buscar materiais em fornecedor',
  tfd: 'TFD',
}

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'

const detalheMotivo = (s) => {
  if (s.origem || s.destino) return `${s.origem ?? '—'} → ${s.destino ?? '—'}`
  if (s.cidade) return s.cidade
  if (s.hospital_destino) return s.hospital_destino
  if (s.fornecedor_nome) return s.fornecedor_nome
  return s.numero_atendimento ? `Atendimento #${s.numero_atendimento}` : '—'
}

export default function SolicitacoesList() {
  const qc = useQueryClient()
  const [aceitarTarget, setAceitarTarget] = useState(null)
  const [aceitarForm, setAceitarForm] = useState({ motorista_id: '', veiculo_id: '' })
  const [error, setError] = useState('')

  const { marcarTodasLidas } = useNotificacoes()

  useEffect(() => {
    marcarTodasLidas()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const { data, isLoading } = useQuery({
    queryKey: ['solicitacoes'],
    queryFn: () => solicitacoesApi.listar().then(r => r.data.data ?? r.data),
    refetchInterval: 10_000,
  })

  const { data: motoristas } = useQuery({
    queryKey: ['motoristas-disponiveis'],
    queryFn: () => motoristasApi.listarDisponiveis().then(r => r.data.data ?? r.data),
    enabled: !!aceitarTarget,
  })

  const { data: veiculos } = useQuery({
    queryKey: ['veiculos'],
    queryFn: () => veiculosApi.listar().then(r => r.data.data ?? r.data),
    enabled: !!aceitarTarget,
  })

  const doAceitar = useMutation({
    mutationFn: ({ id, data }) => solicitacoesApi.aceitar(id, data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      setAceitarTarget(null)
      setError('')
    },
    onError: (e) => setError(e.response?.data?.error ?? 'Erro ao aceitar solicitação'),
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-lg font-semibold text-gray-800">Solicitações de Transporte</h1>
        <span className="flex items-center gap-1.5 text-xs text-gray-400">
          <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
          Atualização automática
        </span>
      </div>

      <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Data', 'Solicitante', 'Motivo', 'Detalhe', 'Saída', 'Chegada', 'Status', 'Motorista', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((s) => (
              <tr key={s.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(s.criado_em)}</td>
                <td className="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">{s.usuario_nome ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{MOTIVOS[s.motivo] ?? s.motivo}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{detalheMotivo(s)}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(s.saida_at)}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(s.chegada_at)}</td>
                <td className="px-4 py-3">
                  <Badge value={s.status} />
                  {s.status === 'recusada' && (
                    <p className="text-xs text-red-600 mt-1" title={s.motivo_recusa}>
                      Recusada: {s.motivo_recusa}
                    </p>
                  )}
                </td>
                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{s.motorista_nome ?? '—'}</td>
                <td className="px-4 py-3">
                  {(s.status === 'aberto' || s.status === 'recusada') && (
                    <button
                      onClick={() => { setAceitarTarget(s); setAceitarForm({ motorista_id: '', veiculo_id: '' }); setError('') }}
                      className="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors"
                    >
                      {s.status === 'recusada' ? 'Redesignar' : 'Aceitar'}
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Nenhuma solicitação encontrada</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={!!aceitarTarget} onClose={() => setAceitarTarget(null)} title="Aceitar solicitação">
        <form
          onSubmit={(e) => { e.preventDefault(); doAceitar.mutate({ id: aceitarTarget.id, data: aceitarForm }) }}
          className="space-y-4"
        >
          {error && <Alert type="error" message={error} />}
          <p className="text-sm text-gray-600">
            {MOTIVOS[aceitarTarget?.motivo] ?? aceitarTarget?.motivo} — {aceitarTarget && detalheMotivo(aceitarTarget)}
          </p>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Motorista *</label>
            <select required value={aceitarForm.motorista_id}
              onChange={e => setAceitarForm(f => ({ ...f, motorista_id: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione...</option>
              {(motoristas ?? []).map(m => <option key={m.id} value={m.id}>{m.nome}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
            <select required value={aceitarForm.veiculo_id}
              onChange={e => setAceitarForm(f => ({ ...f, veiculo_id: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione...</option>
              {(veiculos ?? []).map(v => <option key={v.id} value={v.id}>{v.placa} — {v.modelo}</option>)}
            </select>
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={doAceitar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
              {doAceitar.isPending ? 'Confirmando...' : 'Confirmar e despachar'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
