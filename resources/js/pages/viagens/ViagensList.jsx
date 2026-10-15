import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, MapPin, Eye } from 'lucide-react'
import { format } from 'date-fns'
import * as viagensApi from '../../api/viagens'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import ViagemForm from './ViagemForm'
import KmConfirmacao, { KmEco, ehAlertaKm, camposConfirmacaoKm } from '../../components/shared/KmConfirmacao'
import ViagemDetalhe from './ViagemDetalhe'
import ParadaControle from './ParadaControle'
import FilaMotoristaCard from '../../components/notificacoes/FilaMotoristaCard'
import { useAuth } from '../../contexts/AuthContext'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'
const fmtKm = (n) => n != null ? Number(n).toLocaleString('pt-BR') : '—'

export default function ViagensList() {
  const { user, isGestor, isOperador } = useAuth()
  const ehMotorista = isOperador && !!user?.motorista_id
  const qc = useQueryClient()
  // Motorista abre já em "Em andamento" (tela enxuta); demais perfis começam em "Todas".
  const [statusFilter, setStatusFilter] = useState(() => (isOperador ? 'em_andamento' : ''))
  const [formOpen, setFormOpen] = useState(false)
  const [chegadaTarget, setChegadaTarget] = useState(null)
  const [chegadaForm, setChegadaForm] = useState({ km_chegada: '', observacoes: '' })
  const [perguntaRetorno, setPerguntaRetorno] = useState(false)
  const [kmAlerta, setKmAlerta] = useState(null)
  const [justificativaKm, setJustificativaKm] = useState('')
  const [retornarEscolhido, setRetornarEscolhido] = useState(false)
  const [detalhesTarget, setDetalhesTarget] = useState(null)
  const [error, setError] = useState('')
  const [page, setPage] = useState(1)
  const paginado = !isOperador

  const { data: resp, isLoading } = useQuery({
    queryKey: ['viagens', statusFilter, paginado ? page : null],
    queryFn: () => viagensApi.listar({ ...(statusFilter ? { status: statusFilter } : {}), ...(paginado ? { page } : {}) }).then(r => r.data),
  })
  const data = resp?.data ?? resp
  const lastPage = resp?.meta?.last_page ?? 1
  const total = resp?.meta?.total

  // Rastreamento GPS roda em Layout.jsx (nível global, sobrevive à navegação entre telas)
  const activeTrip = isOperador ? (data ?? []).find(v => v.status === 'em_andamento') : null

  const doChegada = useMutation({
    mutationFn: ({ id, data }) => viagensApi.chegada(id, data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['viagens'] }); setChegadaTarget(null); setPerguntaRetorno(false); setKmAlerta(null); setJustificativaKm('') },
    onError: (e) => {
      setPerguntaRetorno(false)
      if (ehAlertaKm(e)) { setError(''); setKmAlerta(e.response.data); return }
      setError(e.response?.data?.errors?.km_chegada?.[0] ?? e.response?.data?.message ?? 'Erro ao registrar chegada')
    },
  })

  // Transferência, TFD e Exames: ao chegar, o motorista decide se já volta à origem.
  // A própria viagem de retorno não pergunta de novo (permite_retorno vem falso do backend).
  const podeRetornar = !!chegadaTarget?.permite_retorno

  const enviarChegada = (retornar) => {
    setRetornarEscolhido(retornar)
    doChegada.mutate({ id: chegadaTarget.id, data: { ...chegadaForm, ...camposConfirmacaoKm(kmAlerta, justificativaKm), retornar_origem: retornar } })
  }
  const abrirChegada = (v) => { setChegadaTarget(v); setChegadaForm({ km_chegada: '', observacoes: '' }); setKmAlerta(null); setJustificativaKm(''); setError('') }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <FilaMotoristaCard ehMotorista={ehMotorista} />

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex flex-wrap items-center gap-2">
          {[{ v: '', l: 'Todas' }, { v: 'em_andamento', l: 'Em andamento' }, { v: 'concluida', l: 'Concluídas' }, { v: 'cancelada', l: 'Canceladas' }].map(({ v, l }) => (
            <button key={v} onClick={() => { setStatusFilter(v); setPage(1) }}
              className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${statusFilter === v ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:border-blue-400'}`}>
              {l}
            </button>
          ))}
          {activeTrip && (
            <span className="flex items-center gap-1.5 text-xs text-green-600 font-medium ml-2">
              <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
              Rastreando...
            </span>
          )}
        </div>
        <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
          <Plus size={16} /> Nova viagem
        </button>
      </div>

      {/* Motorista no celular: ações da viagem ativa em botões redondos fixos no rodapé */}
      {activeTrip && (
        <div className="md:hidden fixed bottom-0 inset-x-0 z-30 flex justify-around items-center pb-6 pt-2 pointer-events-none">
          <div className="pointer-events-auto"><ParadaControle viagem={activeTrip} circular /></div>
          <button onClick={() => abrirChegada(activeTrip)}
            className="pointer-events-auto w-36 h-36 rounded-full shadow-lg flex flex-col items-center justify-center gap-1 bg-green-600 active:bg-green-700 text-white text-sm font-semibold">
            <MapPin size={26} /> Chegada
          </button>
        </div>
      )}

      {isOperador && (data ?? []).length === 0 && (
        <p className="text-sm text-gray-500 text-center py-10">{statusFilter === 'em_andamento' ? 'Nenhuma viagem em andamento.' : 'Nenhuma viagem encontrada.'}</p>
      )}

      {/* Cards — telas pequenas */}
      <div className={`md:hidden space-y-3 ${activeTrip ? 'pb-44' : ''}`}>
        {(data ?? []).map((v) => (
          <div key={v.id} className="bg-white border border-gray-200 rounded-xl p-4 text-sm space-y-2">
            <div className="flex items-center justify-between">
              <p className="font-semibold text-gray-800">{v.motorista?.nome?.split(' ')[0] ?? '—'}</p>
              <Badge value={v.status} />
            </div>
            <p className="font-mono text-gray-600">{v.veiculo?.placa ?? '—'}</p>
            <p className="text-gray-600">
              <span className="text-gray-400">{v.origem || '—'}</span>
              <span className="mx-1 text-gray-300">→</span>
              <span>{v.destino || '—'}</span>
            </p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-500 text-xs">
              <p>Saída: <span className="text-gray-700">{fmtDt(v.saida_at)}</span></p>
              <p>Chegada: <span className="text-gray-700">{fmtDt(v.chegada_at)}</span></p>
              <p>KM saída: <span className="text-gray-700">{fmtKm(v.km_saida)}</span></p>
              <p>KM chegada: <span className="text-gray-700">{fmtKm(v.km_chegada)}</span></p>
              <p>KM: <span className="text-gray-700">{fmtKm(v.km_percorrido)} {v.km_percorrido ? 'km' : ''}</span></p>
            </div>
            {v.status === 'em_andamento' && v.id !== activeTrip?.id && <ParadaControle viagem={v} />}
            {v.id === activeTrip?.id && (v.paradas ?? []).filter(p => !p.saida_at).map(p => (
              <p key={p.id} className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                Parado em {p.local} desde {format(new Date(p.chegada_at), 'HH:mm')}
              </p>
            ))}
            <div className="flex items-center gap-2">
              {v.status === 'em_andamento' && v.id !== activeTrip?.id && (
                <button onClick={() => abrirChegada(v)}
                  className="flex items-center gap-1 text-xs text-green-600 hover:text-green-800 border border-green-300 rounded px-2 py-1 hover:bg-green-50 transition-colors">
                  <MapPin size={12} /> Chegada
                </button>
              )}
              {isGestor && (
                <button onClick={() => setDetalhesTarget(v)}
                  className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors">
                  <Eye size={12} /> Trajeto
                </button>
              )}
            </div>
          </div>
        ))}
        {(data ?? []).length === 0 && (
          <p className="text-center text-gray-400 py-8 text-sm">Nenhuma viagem encontrada</p>
        )}
      </div>

      {/* Tabela — telas médias e maiores */}
      <div className="hidden md:block bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Motorista', 'Veículo', 'Origem → Destino', 'Saída', 'KM saída', 'Chegada', 'KM chegada', 'KM percorrido', 'Status', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((v) => (
              <tr key={v.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 font-medium text-gray-800 whitespace-nowrap">{v.motorista?.nome?.split(' ')[0] ?? '—'}</td>
                <td className="px-4 py-3 font-mono text-gray-600 whitespace-nowrap">{v.veiculo?.placa ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">
                  <span className="text-gray-400">{v.origem || '—'}</span>
                  <span className="mx-1 text-gray-300">→</span>
                  <span>{v.destino || '—'}</span>
                  {v.status === 'em_andamento' && <div className="mt-1.5"><ParadaControle viagem={v} /></div>}
                </td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(v.saida_at)}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtKm(v.km_saida)}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(v.chegada_at)}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtKm(v.km_chegada)}{v.km_suspeito && <span className="ml-1 text-amber-600" title={`KM fora do esperado: ${v.justificativa_km ?? ''}`}>⚠</span>}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtKm(v.km_percorrido)} {v.km_percorrido ? 'km' : ''}</td>
                <td className="px-4 py-3"><Badge value={v.status} /></td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    {v.status === 'em_andamento' && (
                      <button onClick={() => abrirChegada(v)}
                        className="flex items-center gap-1 text-xs text-green-600 hover:text-green-800 border border-green-300 rounded px-2 py-1 hover:bg-green-50 transition-colors">
                        <MapPin size={12} /> Chegada
                      </button>
                    )}
                    {isGestor && (
                      <button onClick={() => setDetalhesTarget(v)}
                        className="text-gray-400 hover:text-blue-600 transition-colors"
                        title="Ver trajeto">
                        <Eye size={15} />
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Nenhuma viagem encontrada</td></tr>
            )}
          </tbody>
        </table>
      </div>

      {paginado && lastPage > 1 && (
        <div className="flex items-center justify-between text-sm text-gray-600">
          <span>{total} viagens · página {page} de {lastPage}</span>
          <div className="flex gap-2">
            <button disabled={page <= 1} onClick={() => setPage(p => p - 1)}
              className="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40 hover:border-blue-400">Anterior</button>
            <button disabled={page >= lastPage} onClick={() => setPage(p => p + 1)}
              className="px-3 py-1.5 rounded-lg border border-gray-300 disabled:opacity-40 hover:border-blue-400">Próxima</button>
          </div>
        </div>
      )}

      <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Nova viagem">
        <ViagemForm onSuccess={() => { setFormOpen(false); qc.invalidateQueries({ queryKey: ['viagens'] }) }} />
      </Modal>

      <Modal open={!!chegadaTarget} onClose={() => { setChegadaTarget(null); setPerguntaRetorno(false) }} title="Registrar chegada">
        <form onSubmit={(e) => { e.preventDefault(); kmAlerta ? enviarChegada(retornarEscolhido) : podeRetornar ? setPerguntaRetorno(true) : enviarChegada(false) }} className="space-y-4">
          {error && <Alert type="error" message={error} />}
          <p className="text-sm text-gray-600">{chegadaTarget?.origem} → <strong>{chegadaTarget?.destino}</strong></p>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">KM chegada *</label>
            <input type="number" min={chegadaTarget?.km_saida ?? 0} required value={chegadaForm.km_chegada}
              onChange={e => { setKmAlerta(null); setChegadaForm(f => ({ ...f, km_chegada: e.target.value })) }}
              inputMode="numeric"
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
            <p className="text-xs text-gray-500 mt-1">KM saída: {fmtKm(chegadaTarget?.km_saida)}</p>
            <KmEco valor={chegadaForm.km_chegada} referencia={chegadaTarget?.km_saida} />
          </div>
          <KmConfirmacao alerta={kmAlerta} justificativa={justificativaKm} onJustificativa={setJustificativaKm} />
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
            <textarea rows={3} value={chegadaForm.observacoes} onChange={e => setChegadaForm(f => ({ ...f, observacoes: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={doChegada.isPending} className="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60">
              {doChegada.isPending ? 'Registrando...' : kmAlerta ? 'Confirmar KM e chegada' : 'Confirmar chegada'}
            </button>
          </div>
        </form>
      </Modal>

      <Modal open={perguntaRetorno && !!chegadaTarget} onClose={() => !doChegada.isPending && setPerguntaRetorno(false)} title="Deseja retornar à origem?">
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Se sim, uma nova viagem será iniciada agora de <strong>{chegadaTarget?.destino}</strong> para <strong>{chegadaTarget?.origem}</strong>, com KM inicial {chegadaForm.km_chegada}.
          </p>
          <div className="grid grid-cols-2 gap-3">
            <button type="button" disabled={doChegada.isPending} onClick={() => enviarChegada(true)}
              className="bg-green-600 text-white font-semibold py-3 rounded-lg hover:bg-green-700 disabled:opacity-60">
              SIM
            </button>
            <button type="button" disabled={doChegada.isPending} onClick={() => enviarChegada(false)}
              className="bg-red-600 text-white font-semibold py-3 rounded-lg hover:bg-red-700 disabled:opacity-60">
              NÃO
            </button>
          </div>
        </div>
      </Modal>

      <Modal open={!!detalhesTarget} onClose={() => setDetalhesTarget(null)}
        title={detalhesTarget ? `Trajeto — ${detalhesTarget.origem} → ${detalhesTarget.destino}` : ''}
        size="lg">
        {detalhesTarget && <ViagemDetalhe viagem={detalhesTarget} />}
      </Modal>
    </div>
  )
}
