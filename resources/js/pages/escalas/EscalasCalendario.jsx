import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { ChevronLeft, ChevronRight, Plus, Trash2, RefreshCw } from 'lucide-react'
import { addDays, format, startOfWeek, isSameDay } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import * as escalasApi from '../../api/escalas'
import * as motoristasApi from '../../api/motoristas'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Modal from '../../components/ui/Modal'
import Alert from '../../components/ui/Alert'
import { useAuth } from '../../contexts/AuthContext'

const fmt = (d) => format(d, 'yyyy-MM-dd')
const fmtLabel = (d) => format(d, "EEE dd/MM", { locale: ptBR })

export default function EscalasCalendario() {
  const { isGestor } = useAuth()
  const qc = useQueryClient()
  const [semanaBase, setSemanaBase] = useState(() => startOfWeek(new Date(), { weekStartsOn: 1 }))
  const [modalOpen, setModalOpen] = useState(false)
  const [novaEscala, setNovaEscala] = useState({ motorista_id: '', data: '', turno: 'dia', veiculo_id: '' })
  const [error, setError] = useState('')

  const dias = Array.from({ length: 7 }, (_, i) => addDays(semanaBase, i))
  const de = fmt(dias[0])
  const ate = fmt(dias[6])

  const { data: escalas, isLoading } = useQuery({
    queryKey: ['escalas', de, ate],
    queryFn: () => escalasApi.listar({ de, ate }).then(r => r.data.data ?? r.data),
  })

  const { data: motoristas } = useQuery({
    queryKey: ['motoristas', 'ativos'],
    queryFn: () => motoristasApi.listar({ status: 'ativo' }).then(r => r.data.data ?? r.data),
  })

  const remover = useMutation({
    mutationFn: (id) => escalasApi.remover(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['escalas'] }),
  })

  const criar = useMutation({
    mutationFn: (data) => escalasApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['escalas'] }); setModalOpen(false) },
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao salvar'),
  })

  const escalasNoDia = (dia, turno) =>
    (escalas ?? []).filter(e => e.data === fmt(dia) && e.turno === turno)

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <button onClick={() => setSemanaBase(d => addDays(d, -7))} className="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-50">
            <ChevronLeft size={16} />
          </button>
          <span className="text-sm font-medium text-gray-700">
            {format(dias[0], "dd/MM")} – {format(dias[6], "dd/MM/yyyy", { locale: ptBR })}
          </span>
          <button onClick={() => setSemanaBase(d => addDays(d, 7))} className="p-1.5 rounded-lg border border-gray-300 hover:bg-gray-50">
            <ChevronRight size={16} />
          </button>
        </div>
        {isGestor && (
          <button onClick={() => setModalOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
            <Plus size={16} /> Adicionar escala
          </button>
        )}
      </div>

      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div className="grid grid-cols-8 border-b bg-gray-50 text-xs font-medium text-gray-500">
          <div className="px-3 py-3">Turno</div>
          {dias.map(d => (
            <div key={fmt(d)} className={`px-3 py-3 text-center ${isSameDay(d, new Date()) ? 'text-blue-600' : ''}`}>
              {fmtLabel(d)}
            </div>
          ))}
        </div>
        {['dia', 'noite'].map(turno => (
          <div key={turno} className="grid grid-cols-8 border-b last:border-0 min-h-[80px]">
            <div className="px-3 py-3 text-sm font-medium text-gray-600 capitalize border-r bg-gray-50">{turno}</div>
            {dias.map(dia => (
              <div key={fmt(dia)} className={`px-2 py-2 border-r last:border-0 space-y-1 ${isSameDay(dia, new Date()) ? 'bg-blue-50/30' : ''}`}>
                {escalasNoDia(dia, turno).map(e => (
                  <div key={e.id} className="flex items-center justify-between bg-blue-100 text-blue-800 rounded px-2 py-0.5 text-xs">
                    <span className="truncate">{e.motorista?.nome?.split(' ')[0] ?? '?'}</span>
                    {isGestor && (
                      <button onClick={() => remover.mutate(e.id)} className="text-blue-400 hover:text-red-600 ml-1 shrink-0">
                        <Trash2 size={11} />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            ))}
          </div>
        ))}
      </div>

      <Modal open={modalOpen} onClose={() => setModalOpen(false)} title="Adicionar escala">
        <form onSubmit={(e) => { e.preventDefault(); criar.mutate(novaEscala) }} className="space-y-4">
          {error && <Alert type="error" message={error} />}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Motorista *</label>
            <select required value={novaEscala.motorista_id} onChange={e => setNovaEscala(f => ({ ...f, motorista_id: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione</option>
              {(motoristas ?? []).map(m => <option key={m.id} value={m.id}>{m.nome}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data *</label>
            <input type="date" required value={novaEscala.data} onChange={e => setNovaEscala(f => ({ ...f, data: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Turno *</label>
            <select value={novaEscala.turno} onChange={e => setNovaEscala(f => ({ ...f, turno: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="dia">Dia</option>
              <option value="noite">Noite</option>
              <option value="folga">Folga</option>
            </select>
          </div>
          <div className="flex justify-end">
            <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
              {criar.isPending ? 'Salvando...' : 'Salvar'}
            </button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
