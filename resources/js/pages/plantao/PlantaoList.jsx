import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Eye, CheckSquare } from 'lucide-react'
import { format } from 'date-fns'
import * as plantaoApi from '../../api/plantao'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import PlantaoForm from './PlantaoForm'
import PlantaoChecklist from './PlantaoChecklist'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM/yyyy HH:mm') : '—'

export default function PlantaoList() {
  const qc = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)
  const [checklistTarget, setChecklistTarget] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['plantao'],
    queryFn: () => plantaoApi.listar().then(r => r.data.data ?? r.data),
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <div className="flex justify-end">
        <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
          <Plus size={16} /> Nova passagem
        </button>
      </div>

      {/* Cards — telas pequenas */}
      <div className="md:hidden space-y-3">
        {(data ?? []).map((p) => (
          <div key={p.id} className="bg-white border border-gray-200 rounded-xl p-4 text-sm space-y-2">
            <div className="flex items-center justify-between">
              <p className="font-mono font-semibold text-gray-800">{p.veiculo?.placa ?? '—'}</p>
              <Badge value={p.finalizado_at ? 'finalizado' : 'pendente'} />
            </div>
            <p className="text-gray-500 text-xs">{fmtDt(p.criado_em)}</p>
            <div className="grid grid-cols-2 gap-x-3 gap-y-1 text-gray-500 text-xs">
              <p>Saindo: <span className="text-gray-700">{p.motorista_saindo?.nome?.split(' ')[0] ?? '—'}</span></p>
              <p>Entrando: <span className="text-gray-700">{p.motorista_entrando?.nome?.split(' ')[0] ?? '—'}</span></p>
              <p>KM: <span className="text-gray-700">{Number(p.km_momento).toLocaleString('pt-BR')}</span></p>
              <p>Itens OK: <span className="text-green-700 font-medium">{p.itens_ok}</span></p>
              <p>Pendências: <span className="text-red-600 font-medium">{p.itens_pendencia}</span></p>
            </div>
            <button onClick={() => setChecklistTarget(p.id)} className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors w-fit">
              <CheckSquare size={12} /> Checklist
            </button>
          </div>
        ))}
        {(data ?? []).length === 0 && (
          <p className="text-center text-gray-400 py-8 text-sm">Nenhuma passagem registrada</p>
        )}
      </div>

      {/* Tabela — telas médias e maiores */}
      <div className="hidden md:block bg-white rounded-xl border border-gray-200 overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Data', 'Veículo', 'Saindo', 'Entrando', 'KM', 'Itens OK', 'Pendências', 'Status', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((p) => (
              <tr key={p.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(p.criado_em)}</td>
                <td className="px-4 py-3 font-mono font-semibold text-gray-800 whitespace-nowrap">{p.veiculo?.placa ?? '—'}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{p.motorista_saindo?.nome?.split(' ')[0] ?? '—'}</td>
                <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{p.motorista_entrando?.nome?.split(' ')[0] ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{Number(p.km_momento).toLocaleString('pt-BR')}</td>
                <td className="px-4 py-3 text-green-700 font-medium">{p.itens_ok}</td>
                <td className="px-4 py-3 text-red-600 font-medium">{p.itens_pendencia}</td>
                <td className="px-4 py-3">
                  <Badge value={p.finalizado_at ? 'finalizado' : 'pendente'} />
                </td>
                <td className="px-4 py-3">
                  <button onClick={() => setChecklistTarget(p.id)} className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors">
                    <CheckSquare size={12} /> Checklist
                  </button>
                </td>
              </tr>
            ))}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Nenhuma passagem registrada</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Nova passagem de plantão" size="lg">
        <PlantaoForm onSuccess={() => { setFormOpen(false); qc.invalidateQueries({ queryKey: ['plantao'] }) }} />
      </Modal>

      <Modal open={!!checklistTarget} onClose={() => setChecklistTarget(null)} title="Checklist de plantão" size="xl">
        {checklistTarget && <PlantaoChecklist plantaoId={checklistTarget} onClose={() => { setChecklistTarget(null); qc.invalidateQueries({ queryKey: ['plantao'] }) }} />}
      </Modal>
    </div>
  )
}
