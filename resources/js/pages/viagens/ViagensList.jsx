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
import ViagemDetalhe from './ViagemDetalhe'
import FilaMotoristaCard from '../../components/notificacoes/FilaMotoristaCard'
import { useAuth } from '../../contexts/AuthContext'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'
const fmtKm = (n) => n != null ? Number(n).toLocaleString('pt-BR') : '—'

export default function ViagensList() {
  const { user, isGestor, isOperador } = useAuth()
  const ehMotorista = isOperador && !!user?.motorista_id
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [chegadaTarget, setChegadaTarget] = useState(null)
  const [chegadaForm, setChegadaForm] = useState({ km_chegada: '', observacoes: '' })
  const [detalhesTarget, setDetalhesTarget] = useState(null)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['viagens', statusFilter],
    queryFn: () => viagensApi.listar(statusFilter ? { status: statusFilter } : undefined).then(r => r.data.data ?? r.data),
  })

  // Rastreamento GPS roda em Layout.jsx (nível global, sobrevive à navegação entre telas)
  const activeTrip = isOperador ? (data ?? []).find(v => v.status === 'em_andamento') : null

  const doChegada = useMutation({
    mutationFn: ({ id, data }) => viagensApi.chegada(id, data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['viagens'] }); setChegadaTarget(null) },
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao registrar chegada'),
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <FilaMotoristaCard ehMotorista={ehMotorista} />

      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          {[{ v: '', l: 'Todas' }, { v: 'em_andamento', l: 'Em andamento' }, { v: 'concluida', l: 'Concluídas' }, { v: 'cancelada', l: 'Canceladas' }].map(({ v, l }) => (
            <button key={v} onClick={() => setStatusFilter(v)}
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

      {/* Cards — telas pequenas */}
      <div className="md:hidden space-y-3">
        {(data ?? []).map((v) => (
          <div key={v.id} className="bg-white border border-gray-200 rounded-xl p-4 text-sm space-y-2">
            <div className="flex items-center justify-between">
              <p className="font-semibold text-gray-800">{v.motorista?.nome?.split(' ')[0] ?? '—'}</p>
              <Badge value={v.status} />
            </div>
            <p className="font-mono text-gray-600">{v.veiculo?.placa ?? '—'}</p>
            <p className="text-gray-600">
              <span className="text-gray-400">{v.origem}</span>
              <span className="mx-1 text-gray-300">→</span>
              <span>{v.destino}</span>
            </p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-500 text-xs">
              <p>Saída: <span className="text-gray-700">{fmtDt(v.saida_at)}</span></p>
              <p>Chegada: <span className="text-gray-700">{fmtDt(v.chegada_at)}</span></p>
              <p>KM: <span className="text-gray-700">{fmtKm(v.km_percorrido)} {v.km_percorrido ? 'km' : ''}</span></p>
            </div>
            <div className="flex items-center gap-2">
              {v.status === 'em_andamento' && (
                <button onClick={() => { setChegadaTarget(v); setChegadaForm({ km_chegada: '', observacoes: '' }) }}
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
              {['Motorista', 'Veículo', 'Origem → Destino', 'Saída', 'Chegada', 'KM percorrido', 'Status', ''].map(h => (
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
                  <span className="text-gray-400">{v.origem}</span>
                  <span className="mx-1 text-gray-300">→</span>
                  <span>{v.destino}</span>
                </td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(v.saida_at)}</td>
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(v.chegada_at)}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtKm(v.km_percorrido)} {v.km_percorrido ? 'km' : ''}</td>
                <td className="px-4 py-3"><Badge value={v.status} /></td>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-2">
                    {v.status === 'em_andamento' && (
                      <button onClick={() => { setChegadaTarget(v); setChegadaForm({ km_chegada: '', observacoes: '' }) }}
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

      <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Nova viagem">
        <ViagemForm onSuccess={() => { setFormOpen(false); qc.invalidateQueries({ queryKey: ['viagens'] }) }} />
      </Modal>

      <Modal open={!!chegadaTarget} onClose={() => setChegadaTarget(null)} title="Registrar chegada">
        <form onSubmit={(e) => { e.preventDefault(); doChegada.mutate({ id: chegadaTarget.id, data: chegadaForm }) }} className="space-y-4">
          {error && <Alert type="error" message={error} />}
          <p className="text-sm text-gray-600">{chegadaTarget?.origem} → <strong>{chegadaTarget?.destino}</strong></p>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">KM chegada *</label>
            <input type="number" min={chegadaTarget?.km_saida ?? 0} required value={chegadaForm.km_chegada}
              onChange={e => setChegadaForm(f => ({ ...f, km_chegada: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
            <textarea rows={3} value={chegadaForm.observacoes} onChange={e => setChegadaForm(f => ({ ...f, observacoes: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={doChegada.isPending} className="bg-green-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-700 disabled:opacity-60">
              {doChegada.isPending ? 'Registrando...' : 'Confirmar chegada'}
            </button>
          </div>
        </form>
      </Modal>

      <Modal open={!!detalhesTarget} onClose={() => setDetalhesTarget(null)}
        title={detalhesTarget ? `Trajeto — ${detalhesTarget.origem} → ${detalhesTarget.destino}` : ''}
        size="lg">
        {detalhesTarget && <ViagemDetalhe viagem={detalhesTarget} />}
      </Modal>
    </div>
  )
}
