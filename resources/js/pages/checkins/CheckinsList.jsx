import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, LogOut, Wrench } from 'lucide-react'
import { format } from 'date-fns'
import * as checkinsApi from '../../api/checkins'
import { useAuth } from '../../contexts/AuthContext'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import CheckinForm from './CheckinForm'
import ManutencaoModal from '../../components/manutencoes/ManutencaoModal'
import EncerrarManutencaoModal from '../../components/manutencoes/EncerrarManutencaoModal'
import { rotuloTipo, tempoDecorrido } from '../../components/manutencoes/tipos'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM/yyyy HH:mm') : '—'
const fmtKm = (n) => Number(n ?? 0).toLocaleString('pt-BR')

// KM de retorno com sinal de divergência (KM informado ≠ saída + viagens).
const KmRetorno = ({ c }) => (
  <>
    {c.km_retorno ? fmtKm(c.km_retorno) : '—'}
    {!!c.divergencia_km && (
      <span title={`Esperado ${fmtKm(c.km_retorno_esperado)} — ${c.justificativa_divergencia_km ?? ''}`}
        className="ml-1 text-xs text-red-700 bg-red-50 border border-red-200 rounded px-1">
        {c.divergencia_km > 0 ? '+' : ''}{fmtKm(c.divergencia_km)} km
      </span>
    )}
  </>
)

export default function CheckinsList() {
  const qc = useQueryClient()
  const { isOperador, checkinAtivo, checkinsAtivos, limiteCheckins, removerCheckinAtivo } = useAuth()
  const [statusFilter, setStatusFilter] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [checkoutTarget, setCheckoutTarget] = useState(null)
  const [checkoutForm, setCheckoutForm] = useState({ km_retorno: '', nivel_combustivel_retorno: '', ocorrencias: '', justificativa_divergencia_km: '', km_chegada_viagem: '' })
  const viagemAberta = !isOperador ? checkoutTarget?.viagem_em_andamento : null
  // Com viagem aberta, o KM esperado soma o trecho que será encerrado agora.
  const kmEsperado = checkoutTarget?.km_retorno_esperado == null ? null
    : viagemAberta
      ? (checkoutForm.km_chegada_viagem === '' ? null
        : checkoutTarget.km_retorno_esperado + Number(checkoutForm.km_chegada_viagem) - viagemAberta.km_saida)
      : checkoutTarget.km_retorno_esperado
  const kmDivergente = checkoutTarget?.km_retorno_fixo == null && kmEsperado != null
    && checkoutForm.km_retorno !== '' && Number(checkoutForm.km_retorno) !== kmEsperado
  const [error, setError] = useState('')
  const [manutencaoVeiculo, setManutencaoVeiculo] = useState(null)
  const [encerrarVeiculo, setEncerrarVeiculo] = useState(null)

  // Sem viagem desde o check-in o KM de retorno é fixo (km_retorno_fixo).
  const abrirCheckout = (c) => {
    setCheckoutTarget(c)
    setCheckoutForm({ km_retorno: c.km_retorno_fixo ?? '', nivel_combustivel_retorno: '', ocorrencias: '', justificativa_divergencia_km: '', km_chegada_viagem: '' })
  }

  // Botão de manutenção do veículo do check-in ativo (motorista ou gestão).
  const acaoManutencao = (c) => {
    if (c.status !== 'ativo' || !c.veiculo) return null
    return c.veiculo.status === 'manutencao' ? (
      <button onClick={() => setEncerrarVeiculo(c.veiculo)} title="Retirar da manutenção"
        className="flex items-center gap-1 text-xs text-green-700 hover:text-green-900 border border-green-300 rounded px-2 py-1 hover:bg-green-50 transition-colors w-fit whitespace-nowrap">
        <Wrench size={12} /> Liberar
      </button>
    ) : (
      <button onClick={() => setManutencaoVeiculo(c.veiculo)}
        className="flex items-center gap-1 text-xs text-yellow-700 hover:text-yellow-900 border border-yellow-300 rounded px-2 py-1 hover:bg-yellow-50 transition-colors w-fit whitespace-nowrap">
        <Wrench size={12} /> Manutenção
      </button>
    )
  }

  const avisoManutencao = (c) => c.status === 'ativo' && c.veiculo?.status === 'manutencao' && (
    <p className="text-xs text-yellow-800 bg-yellow-50 border border-yellow-200 rounded px-2 py-1">
      Em manutenção há {tempoDecorrido(c.veiculo.manutencao_atual?.inicio ?? c.veiculo.manutencao_inicio)}
      {' – '}{rotuloTipo(c.veiculo.manutencao_atual?.tipo)}
      {c.veiculo.manutencao_atual?.motivo ? ` (${c.veiculo.manutencao_atual.motivo})` : ''}
    </p>
  )

  const { data, isLoading } = useQuery({
    queryKey: ['checkins', statusFilter],
    queryFn: () => checkinsApi.listar(statusFilter ? { status: statusFilter } : undefined).then(r => r.data.data ?? r.data),
  })

  // A API já retorna apenas os checkins do próprio operador quando aplicável
  const checkinsFiltrados = data ?? []

  const doCheckout = useMutation({
    mutationFn: ({ id, data }) => checkinsApi.checkout(id, data),
    onSuccess: (_res, { id }) => {
      qc.invalidateQueries({ queryKey: ['checkins'] })
      qc.invalidateQueries({ queryKey: ['veiculos'] })
      if (isOperador) removerCheckinAtivo(id)
      setCheckoutTarget(null)
    },
    onError: (e) => {
      const errors = e.response?.data?.errors
      const primeiraMensagem = errors ? Object.values(errors)[0]?.[0] : undefined
      setError(primeiraMensagem ?? e.response?.data?.message ?? 'Erro ao encerrar')
    },
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      {isOperador && !checkinAtivo && (
        <div className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-sm text-amber-800">
          Você ainda não possui check-in ativo. Registre seu check-in para iniciar as operações.
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap gap-2">
          {[{ v: '', l: 'Todos' }, { v: 'ativo', l: 'Ativos' }, { v: 'finalizado', l: 'Finalizados' }].map(({ v, l }) => (
            <button key={v} onClick={() => setStatusFilter(v)}
              className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${statusFilter === v ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:border-blue-400'}`}>
              {l}
            </button>
          ))}
        </div>
        {(!isOperador || checkinsAtivos.length < limiteCheckins) && (
          <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
            <Plus size={16} /> {isOperador && checkinsAtivos.length > 0 ? 'Adicionar 2º veículo' : 'Novo check-in'}
          </button>
        )}
      </div>

      {/* Cards — telas pequenas */}
      <div className="md:hidden space-y-3">
        {checkinsFiltrados.map((c) => (
          <div key={c.id} className="bg-white border border-gray-200 rounded-xl p-4 text-sm space-y-2">
            <div className="flex items-center justify-between">
              <p className="font-semibold text-gray-800">{c.motorista?.nome ?? '—'}</p>
              <Badge value={c.status} />
            </div>
            <p className="font-mono text-gray-600">{c.veiculo?.placa ?? '—'} <span className="text-gray-400 capitalize">· {c.turno}</span></p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-500 text-xs">
              <p>KM saída: <span className="text-gray-700">{fmtKm(c.km_saida)}</span></p>
              <p>KM retorno: <span className="text-gray-700"><KmRetorno c={c} /></span></p>
              <p>Check-in: <span className="text-gray-700">{fmtDt(c.checkin_at)}</span></p>
              <p>Check-out: <span className="text-gray-700">{fmtDt(c.checkout_at)}</span></p>
            </div>
            {avisoManutencao(c)}
            {c.status === 'ativo' && (
              <div className="flex flex-wrap gap-2">
                <button onClick={() => abrirCheckout(c)}
                  className="flex items-center gap-1 text-xs text-orange-600 hover:text-orange-800 border border-orange-300 rounded px-2 py-1 hover:bg-orange-50 transition-colors w-fit">
                  <LogOut size={12} /> Checkout
                </button>
                {acaoManutencao(c)}
              </div>
            )}
          </div>
        ))}
        {checkinsFiltrados.length === 0 && (
          <p className="text-center text-gray-400 py-8 text-sm">Nenhum check-in encontrado</p>
        )}
      </div>

      {/* Tabela — telas médias e maiores */}
      <div className="hidden md:block bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Motorista', 'Veículo', 'Turno', 'KM saída', 'KM retorno', 'Check-in', 'Check-out', 'Status', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {checkinsFiltrados.map((c) => (
              <tr key={c.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">{c.motorista?.nome ?? '—'}</td>
                <td className="px-4 py-3 font-mono text-gray-600 whitespace-nowrap">
                  {c.veiculo?.placa ?? '—'}
                  {c.status === 'ativo' && c.veiculo?.status === 'manutencao' && (
                    <span className="ml-2 font-sans text-xs text-yellow-700" title={rotuloTipo(c.veiculo.manutencao_atual?.tipo)}>
                      em manutenção · {tempoDecorrido(c.veiculo.manutencao_atual?.inicio ?? c.veiculo.manutencao_inicio)}
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 capitalize text-gray-500 whitespace-nowrap">{c.turno}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtKm(c.km_saida)}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap"><KmRetorno c={c} /></td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(c.checkin_at)}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(c.checkout_at)}</td>
                <td className="px-4 py-3"><Badge value={c.status} /></td>
                <td className="px-4 py-3">
                  {c.status === 'ativo' && (
                    <div className="flex gap-2">
                      <button onClick={() => abrirCheckout(c)}
                        className="flex items-center gap-1 text-xs text-orange-600 hover:text-orange-800 border border-orange-300 rounded px-2 py-1 hover:bg-orange-50 transition-colors">
                        <LogOut size={12} /> Checkout
                      </button>
                      {acaoManutencao(c)}
                    </div>
                  )}
                </td>
              </tr>
            ))}
            {checkinsFiltrados.length === 0 && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Nenhum check-in encontrado</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Novo check-in">
        <CheckinForm onSuccess={() => {
          setFormOpen(false)
          qc.invalidateQueries({ queryKey: ['checkins'] })
          qc.invalidateQueries({ queryKey: ['checklist-veiculo'] })
        }} />
      </Modal>

      <ManutencaoModal veiculo={manutencaoVeiculo} onClose={() => setManutencaoVeiculo(null)} />
      <EncerrarManutencaoModal veiculo={encerrarVeiculo} onClose={() => setEncerrarVeiculo(null)} />

      <Modal open={!!checkoutTarget} onClose={() => setCheckoutTarget(null)} title="Encerrar check-in">
        <form onSubmit={(e) => { e.preventDefault(); doCheckout.mutate({ id: checkoutTarget.id, data: checkoutForm }) }} className="space-y-4">
          {error && <Alert type="error" message={error} />}
          <p className="text-sm text-gray-600">Motorista: <strong>{checkoutTarget?.motorista?.nome}</strong> — Veículo: <strong>{checkoutTarget?.veiculo?.placa}</strong></p>
          {viagemAberta && (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 space-y-2">
              <p className="text-xs text-yellow-800">
                Há uma viagem em andamento com este veículo (saída {fmtKm(viagemAberta.km_saida)}{viagemAberta.destino ? ` → ${viagemAberta.destino}` : ''}). Ela será encerrada junto com o check-out.
              </p>
              <label className="block text-sm font-medium text-gray-700">KM de chegada da viagem *</label>
              <input type="number" required min={viagemAberta.km_saida} value={checkoutForm.km_chegada_viagem}
                onChange={e => setCheckoutForm(f => ({ ...f, km_chegada_viagem: e.target.value }))}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">KM retorno</label>
            <input type="number" min={checkoutTarget?.km_saida ?? 0} value={checkoutForm.km_retorno}
              readOnly={checkoutTarget?.km_retorno_fixo != null}
              onChange={e => setCheckoutForm(f => ({ ...f, km_retorno: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 read-only:bg-gray-100 read-only:text-gray-500" />
            {checkoutTarget?.km_retorno_fixo != null && (
              <p className="text-xs text-gray-500 mt-1">Nenhuma viagem registrada após o check-in: o KM de retorno é o da saída{checkoutTarget.km_retorno_fixo !== checkoutTarget.km_saida ? ' somado ao KM rodado em manutenção' : ''}.</p>
            )}
            {checkoutTarget?.km_retorno_fixo == null && kmEsperado != null && (
              <p className="text-xs text-gray-500 mt-1">KM esperado: <strong>{fmtKm(kmEsperado)}</strong> (KM de saída + viagens registradas).</p>
            )}
          </div>
          {kmDivergente && (
            <div>
              <label className="block text-sm font-medium text-red-700 mb-1">
                Justificativa da diferença de {fmtKm(Number(checkoutForm.km_retorno) - kmEsperado)} km *
              </label>
              <textarea required rows={2} maxLength={500} value={checkoutForm.justificativa_divergencia_km}
                onChange={e => setCheckoutForm(f => ({ ...f, justificativa_divergencia_km: e.target.value }))}
                className="w-full border border-red-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500" />
            </div>
          )}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nível combustível retorno (%)</label>
            <input type="number" min={0} max={100} value={checkoutForm.nivel_combustivel_retorno}
              onChange={e => setCheckoutForm(f => ({ ...f, nivel_combustivel_retorno: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Ocorrências</label>
            <textarea value={checkoutForm.ocorrencias} onChange={e => setCheckoutForm(f => ({ ...f, ocorrencias: e.target.value }))} rows={3}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={doCheckout.isPending} className="bg-orange-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-700 disabled:opacity-60">
              {doCheckout.isPending ? 'Encerrando...' : 'Encerrar check-in'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
