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

      {/* Cards empilhados: mobile e tablet */}
      <div className="space-y-3 md:hidden">
        {(data ?? []).map((s) => (
          <div key={s.id} className="bg-white rounded-xl border border-gray-200 p-4 space-y-2">
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className="font-medium text-gray-800">{s.usuario_nome ?? '—'}</p>
                <p className="text-xs text-gray-500">{fmtDt(s.criado_em)}</p>
              </div>
              <Badge value={s.status} />
            </div>
            <p className="text-sm text-gray-600">{MOTIVOS[s.motivo] ?? s.motivo}</p>
            <p className="text-sm text-gray-500 break-words">{detalheMotivo(s)}</p>
            <div className="grid grid-cols-2 gap-2 text-xs text-gray-500">
              <div>
                <span className="block text-gray-400">Saída</span>
                {fmtDt(s.saida_at)}
              </div>
              <div>
                <span className="block text-gray-400">Chegada</span>
                {fmtDt(s.chegada_at)}
              </div>
            </div>
            <p className="text-sm text-gray-600">
              <span className="text-gray-400 text-xs block">Motorista</span>
              {s.motorista_nome ?? '—'}
            </p>
            {s.status === 'recusada' && (
              <p className="text-xs text-red-600" title={s.motivo_recusa}>
                Recusada: {s.motivo_recusa}
              </p>
            )}
            {(s.status === 'aberto' || s.status === 'recusada') && (
              <button
                onClick={() => { setAceitarTarget(s); setAceitarForm({ motorista_id: '', veiculo_id: '' }); setError('') }}
                className="w-full text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-2 hover:bg-blue-50 transition-colors"
              >
                {s.status === 'recusada' ? 'Redesignar' : 'Aceitar'}
              </button>
            )}
          </div>
        ))}
        {(data ?? []).length === 0 && (
          <div className="bg-white rounded-xl border border-gray-200 px-4 py-8 text-center text-gray-400">
            Nenhuma solicitação encontrada
          </div>
        )}
      </div>

      {/* Tabela: telas médias e grandes */}
      <div className="hidden md:block bg-white rounded-xl border border-gray-200">
        <table className="w-full text-sm table-fixed">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              <th className="px-3 py-3 text-left font-medium w-[9%]">Data</th>
              <th className="px-3 py-3 text-left font-medium w-[13%]">Solicitante</th>
              <th className="px-3 py-3 text-left font-medium w-[14%]">Motivo</th>
              <th className="px-3 py-3 text-left font-medium w-[18%]">Detalhe</th>
              <th className="px-3 py-3 text-left font-medium w-[9%]">Saída</th>
              <th className="px-3 py-3 text-left font-medium w-[9%]">Chegada</th>
              <th className="px-3 py-3 text-left font-medium w-[13%]">Status</th>
              <th className="px-3 py-3 text-left font-medium w-[10%]">Motorista</th>
              <th className="px-3 py-3 text-left font-medium w-[5%]"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((s) => (
              <tr key={s.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.criado_em)}</td>
                <td className="px-3 py-3 font-medium text-gray-800 truncate" title={s.usuario_nome}>{s.usuario_nome ?? '—'}</td>
                <td className="px-3 py-3 text-gray-600 truncate" title={MOTIVOS[s.motivo] ?? s.motivo}>{MOTIVOS[s.motivo] ?? s.motivo}</td>
                <td className="px-3 py-3 text-gray-500 truncate" title={detalheMotivo(s)}>{detalheMotivo(s)}</td>
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.saida_at)}</td>
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.chegada_at)}</td>
                <td className="px-3 py-3">
                  <Badge value={s.status} />
                  {s.status === 'recusada' && (
                    <p className="text-xs text-red-600 mt-1 truncate" title={s.motivo_recusa}>
                      Recusada: {s.motivo_recusa}
                    </p>
                  )}
                </td>
                <td className="px-3 py-3 text-gray-600 truncate" title={s.motorista_nome}>{s.motorista_nome ?? '—'}</td>
                <td className="px-3 py-3">
                  {(s.status === 'aberto' || s.status === 'recusada') && (
                    <button
                      onClick={() => { setAceitarTarget(s); setAceitarForm({ motorista_id: '', veiculo_id: '' }); setError('') }}
                      className="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors whitespace-nowrap"
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
