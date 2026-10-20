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
import { rotuloMotivo } from '../../utils/solicitacao'


const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'

// Espelha SolicitacaoService::STATUS_CANCELAVEIS: viagem iniciada ou realizada não cancela.
const CANCELAVEIS = ['aberto', 'recusada', 'pendente_motorista', 'aguardando_finalizacao_trajeto']
const podeCancelar = (s) => CANCELAVEIS.includes(s.status) && !s.viagem_id
const podeVincular = (s) => s.status === 'aberto' || s.status === 'recusada'

const detalheMotivo = (s) => {
  if (s.origem || s.destino) {
    const rota = `${s.origem ?? '—'} → ${s.destino ?? '—'}`
    return s.autorizacao_referencia_em ? `${rota} · Ref. autorizada ${fmtDt(s.autorizacao_referencia_em)}` : rota
  }
  if (s.cidade) return s.cidade
  if (s.hospital_destino) return s.hospital_destino
  if (s.fornecedor_nome) return s.fornecedor_nome
  return '—'
}

export default function SolicitacoesList() {
  const qc = useQueryClient()
  const [aceitarTarget, setAceitarTarget] = useState(null)
  const [aceitarForm, setAceitarForm] = useState({ motorista_id: '', veiculo_id: '' })
  const [error, setError] = useState('')
  const [recusarTarget, setRecusarTarget] = useState(null)
  const [motivoRecusa, setMotivoRecusa] = useState('')
  const [erroRecusa, setErroRecusa] = useState('')
  const [vincularTarget, setVincularTarget] = useState(null)
  const [viagemVinculo, setViagemVinculo] = useState('')
  const [erroVinculo, setErroVinculo] = useState('')
  const [cancelarTarget, setCancelarTarget] = useState(null)
  const [erroCancelar, setErroCancelar] = useState('')

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

  const doRecusar = useMutation({
    mutationFn: ({ id, motivo }) => solicitacoesApi.recusar(id, motivo),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      setRecusarTarget(null)
    },
    onError: (e) => setErroRecusa(e.response?.data?.message ?? e.response?.data?.error ?? 'Erro ao recusar solicitação'),
  })

  const { data: candidatas, isLoading: carregandoCandidatas } = useQuery({
    queryKey: ['viagens-candidatas', vincularTarget?.id],
    queryFn: () => solicitacoesApi.viagensCandidatas(vincularTarget.id).then(r => r.data.data ?? r.data),
    enabled: !!vincularTarget,
  })

  const doVincular = useMutation({
    mutationFn: ({ id, viagemId }) => solicitacoesApi.vincularViagem(id, viagemId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      setVincularTarget(null)
    },
    onError: (e) => setErroVinculo(e.response?.data?.message ?? e.response?.data?.error ?? 'Erro ao vincular viagem'),
  })

  const doCancelar = useMutation({
    mutationFn: (id) => solicitacoesApi.cancelar(id),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      setCancelarTarget(null)
    },
    onError: (e) => setErroCancelar(e.response?.data?.message ?? e.response?.data?.error ?? 'Erro ao cancelar solicitação'),
  })

  const abrirVinculo = (s) => { setVincularTarget(s); setViagemVinculo(''); setErroVinculo('') }
  const abrirCancelar = (s) => { setCancelarTarget(s); setErroCancelar('') }

  const abrirRecusa = (s) => { setRecusarTarget(s); setMotivoRecusa(''); setErroRecusa('') }

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
            <p className="text-sm text-gray-600">{rotuloMotivo(s)}</p>
            {s.numero_atendimento && <p className="text-sm text-gray-600">Atendimento #{s.numero_atendimento}</p>}
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
            {(s.status === 'recusada' || s.status === 'recusada_gestao') && (
              <p className="text-xs text-red-600" title={s.motivo_recusa}>
                Motivo: {s.motivo_recusa}
              </p>
            )}
            {(s.status === 'aberto' || s.status === 'recusada') && (
              <div className="flex gap-2">
                <button
                  onClick={() => { setAceitarTarget(s); setAceitarForm({ motorista_id: '', veiculo_id: '' }); setError('') }}
                  className="flex-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded-sm px-2 py-2 hover:bg-blue-50 transition-colors"
                >
                  {s.status === 'recusada' ? 'Redesignar' : 'Aceitar'}
                </button>
                <button
                  onClick={() => abrirRecusa(s)}
                  className="flex-1 text-xs text-red-600 hover:text-red-800 border border-red-200 rounded-sm px-2 py-2 hover:bg-red-50 transition-colors"
                >
                  Recusar
                </button>
              </div>
            )}
            {(podeVincular(s) || podeCancelar(s)) && (
              <div className="flex gap-2">
                {podeVincular(s) && (
                  <button
                    onClick={() => abrirVinculo(s)}
                    className="flex-1 text-xs text-gray-700 hover:text-gray-900 border border-gray-300 rounded-sm px-2 py-2 hover:bg-gray-50 transition-colors"
                  >
                    Vincular viagem
                  </button>
                )}
                {podeCancelar(s) && (
                  <button
                    onClick={() => abrirCancelar(s)}
                    className="flex-1 text-xs text-gray-700 hover:text-gray-900 border border-gray-300 rounded-sm px-2 py-2 hover:bg-gray-50 transition-colors"
                  >
                    Cancelar
                  </button>
                )}
              </div>
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
      <div className="hidden md:block bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table className="w-full min-w-[900px] text-sm table-fixed">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              <th className="px-3 py-3 text-left font-medium w-[8%]">Data</th>
              <th className="px-3 py-3 text-left font-medium w-[12%]">Solicitante</th>
              <th className="px-3 py-3 text-left font-medium w-[8%]">Nº Atend.</th>
              <th className="px-3 py-3 text-left font-medium w-[13%]">Motivo</th>
              <th className="px-3 py-3 text-left font-medium w-[14%]">Detalhe</th>
              <th className="px-3 py-3 text-left font-medium w-[8%]">Saída</th>
              <th className="px-3 py-3 text-left font-medium w-[8%]">Chegada</th>
              <th className="px-3 py-3 text-left font-medium w-[12%]">Status</th>
              <th className="px-3 py-3 text-left font-medium w-[9%]">Motorista</th>
              <th className="px-3 py-3 text-left font-medium w-[8%]"></th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((s) => (
              <tr key={s.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.criado_em)}</td>
                <td className="px-3 py-3 font-medium text-gray-800 whitespace-normal break-words">{s.usuario_nome ?? '—'}</td>
                <td className="px-3 py-3 text-gray-600 truncate">{s.numero_atendimento ?? '—'}</td>
                <td className="px-3 py-3 text-gray-600 whitespace-normal break-words">{rotuloMotivo(s)}</td>
                <td className="px-3 py-3 text-gray-500 whitespace-normal break-words">{detalheMotivo(s)}</td>
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.saida_at)}</td>
                <td className="px-3 py-3 text-gray-500 truncate">{fmtDt(s.chegada_at)}</td>
                <td className="px-3 py-3">
                  <Badge value={s.status} />
                  {(s.status === 'recusada' || s.status === 'recusada_gestao') && (
                    <p className="text-xs text-red-600 mt-1 truncate" title={s.motivo_recusa}>
                      Motivo: {s.motivo_recusa}
                    </p>
                  )}
                </td>
                <td className="px-3 py-3 text-gray-600 truncate" title={s.motorista_nome}>{s.motorista_nome ?? '—'}</td>
                <td className="px-3 py-3">
                  {(s.status === 'aberto' || s.status === 'recusada') && (
                    <div className="flex flex-col gap-1">
                      <button
                        onClick={() => { setAceitarTarget(s); setAceitarForm({ motorista_id: '', veiculo_id: '' }); setError('') }}
                        className="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded-sm px-2 py-1 hover:bg-blue-50 transition-colors whitespace-nowrap"
                      >
                        {s.status === 'recusada' ? 'Redesignar' : 'Aceitar'}
                      </button>
                      <button
                        onClick={() => abrirRecusa(s)}
                        className="text-xs text-red-600 hover:text-red-800 border border-red-200 rounded-sm px-2 py-1 hover:bg-red-50 transition-colors whitespace-nowrap"
                      >
                        Recusar
                      </button>
                    </div>
                  )}
                  {(podeVincular(s) || podeCancelar(s)) && (
                    <div className="flex flex-col gap-1 mt-1">
                      {podeVincular(s) && (
                        <button
                          onClick={() => abrirVinculo(s)}
                          className="text-xs text-gray-700 hover:text-gray-900 border border-gray-300 rounded-sm px-2 py-1 hover:bg-gray-50 transition-colors whitespace-nowrap"
                          title="Vincular a uma viagem já iniciada ou realizada pelo motorista"
                        >
                          Vincular
                        </button>
                      )}
                      {podeCancelar(s) && (
                        <button
                          onClick={() => abrirCancelar(s)}
                          className="text-xs text-gray-700 hover:text-gray-900 border border-gray-300 rounded-sm px-2 py-1 hover:bg-gray-50 transition-colors whitespace-nowrap"
                        >
                          Cancelar
                        </button>
                      )}
                    </div>
                  )}
                </td>
              </tr>
            ))}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Nenhuma solicitação encontrada</td></tr>
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
            {rotuloMotivo(aceitarTarget)} — {aceitarTarget && detalheMotivo(aceitarTarget)}
          </p>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Motorista *</label>
            <select required value={aceitarForm.motorista_id}
              onChange={e => setAceitarForm(f => ({ ...f, motorista_id: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione...</option>
              {(motoristas ?? []).map(m => <option key={m.id} value={m.id}>{m.nome}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
            <select required value={aceitarForm.veiculo_id}
              onChange={e => setAceitarForm(f => ({ ...f, veiculo_id: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-blue-500">
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

      <Modal open={!!vincularTarget} onClose={() => setVincularTarget(null)} title="Vincular a viagem existente">
        <form
          onSubmit={(e) => { e.preventDefault(); doVincular.mutate({ id: vincularTarget.id, viagemId: viagemVinculo }) }}
          className="space-y-4"
        >
          {erroVinculo && <Alert type="error" message={erroVinculo} />}
          <p className="text-sm text-gray-600">
            {vincularTarget?.usuario_nome ?? '—'} · {rotuloMotivo(vincularTarget)}
            {vincularTarget?.numero_atendimento && <> · Atendimento #{vincularTarget.numero_atendimento}</>}
          </p>
          {vincularTarget?.motivo === 'transferencia_paciente' && (
            <p className="text-xs text-gray-500">Só aparecem viagens de transferência com o mesmo nº do atendimento.</p>
          )}
          {carregandoCandidatas ? <LoadingSpinner /> : (candidatas ?? []).length === 0 ? (
            <p className="text-sm text-gray-500">Nenhuma viagem compatível sem solicitação vinculada.</p>
          ) : (
            <div className="space-y-2 max-h-80 overflow-y-auto">
              {candidatas.map(v => (
                <label key={v.id} className={`flex items-start gap-3 border rounded-lg p-3 cursor-pointer ${viagemVinculo === v.id ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:bg-gray-50'}`}>
                  <input type="radio" name="viagem" value={v.id} checked={viagemVinculo === v.id}
                    onChange={() => setViagemVinculo(v.id)} className="mt-1" />
                  <span className="text-sm text-gray-700">
                    <span className="block font-medium">{fmtDt(v.saida_at)} · {v.motorista?.nome ?? '—'} · {v.veiculo?.placa ?? '—'}</span>
                    <span className="block text-xs text-gray-500">
                      {v.origem || '—'} → {v.destino || '—'}
                      {v.numero_atendimento && <> · Atend. #{v.numero_atendimento}</>}
                      {' · '}{v.status === 'concluida' ? 'Concluída' : 'Em andamento'}
                    </span>
                  </span>
                </label>
              ))}
            </div>
          )}
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setVincularTarget(null)} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100">
              Voltar
            </button>
            <button type="submit" disabled={doVincular.isPending || !viagemVinculo} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
              {doVincular.isPending ? 'Vinculando...' : 'Confirmar vínculo'}
            </button>
          </div>
        </form>
      </Modal>

      <Modal open={!!cancelarTarget} onClose={() => setCancelarTarget(null)} title="Cancelar solicitação">
        <div className="space-y-4">
          {erroCancelar && <Alert type="error" message={erroCancelar} />}
          <p className="text-sm text-gray-600">
            Cancelar a solicitação de {cancelarTarget?.usuario_nome ?? '—'} · {rotuloMotivo(cancelarTarget)}?
          </p>
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setCancelarTarget(null)} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100">
              Voltar
            </button>
            <button type="button" disabled={doCancelar.isPending} onClick={() => doCancelar.mutate(cancelarTarget.id)} className="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-60">
              {doCancelar.isPending ? 'Cancelando...' : 'Confirmar cancelamento'}
            </button>
          </div>
        </div>
      </Modal>

      <Modal open={!!recusarTarget} onClose={() => setRecusarTarget(null)} title="Recusar solicitação">
        <form
          onSubmit={(e) => { e.preventDefault(); doRecusar.mutate({ id: recusarTarget.id, motivo: motivoRecusa.trim() }) }}
          className="space-y-4"
        >
          {erroRecusa && <Alert type="error" message={erroRecusa} />}
          <p className="text-sm text-gray-600">
            {recusarTarget?.usuario_nome ?? '—'} · {rotuloMotivo(recusarTarget)} — {recusarTarget && detalheMotivo(recusarTarget)}
          </p>
          <div>
            <label htmlFor="motivo-recusa" className="block text-sm font-medium text-gray-700 mb-1">Motivo da recusa *</label>
            <textarea id="motivo-recusa" required maxLength={500} rows={3} value={motivoRecusa}
              onChange={e => setMotivoRecusa(e.target.value)}
              placeholder="Explique ao solicitante por que a solicitação foi recusada"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-red-500" />
            <p className="text-xs text-gray-400 text-right">{motivoRecusa.length}/500</p>
          </div>
          <div className="flex justify-end gap-2">
            <button type="button" onClick={() => setRecusarTarget(null)} className="px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100">
              Voltar
            </button>
            <button type="submit" disabled={doRecusar.isPending || !motivoRecusa.trim()} className="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-60">
              {doRecusar.isPending ? 'Recusando...' : 'Confirmar recusa'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
