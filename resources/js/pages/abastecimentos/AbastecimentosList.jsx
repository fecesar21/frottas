import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { format } from 'date-fns'
import * as abastecimentosApi from '../../api/abastecimentos'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import AbastecimentoForm from './AbastecimentoForm'
import { useAuth } from '../../contexts/AuthContext'

const fmtBrl = (n) => Number(n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDt = (s) => s ? format(new Date(s), 'dd/MM/yyyy HH:mm') : '—'

export default function AbastecimentosList() {
  const { isGestor } = useAuth()
  const qc = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)

  const { data: lista, isLoading } = useQuery({
    queryKey: ['abastecimentos'],
    queryFn: () => abastecimentosApi.listar().then(r => r.data.data ?? r.data),
  })

  const { data: resumo } = useQuery({
    queryKey: ['abastecimentos', 'resumo'],
    queryFn: () => abastecimentosApi.resumo().then(r => r.data.data ?? r.data),
  })

  const excluir = useMutation({
    mutationFn: (id) => abastecimentosApi.excluir(id),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['abastecimentos'] }) },
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-5">
      <div className="flex justify-end">
        <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
          <Plus size={16} /> Novo abastecimento
        </button>
      </div>

      {(resumo ?? []).length > 0 && (
        <div>
          <h3 className="text-sm font-semibold text-gray-700 mb-2">Resumo por veículo</h3>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            {(resumo ?? []).slice(0, 8).map((r) => (
              <div key={r.veiculo_id} className="bg-white border border-gray-200 rounded-xl p-4 text-sm">
                <p className="font-mono font-bold text-gray-800">{r.placa}</p>
                <p className="text-gray-500 text-xs">{r.modelo}</p>
                <p className="mt-2 font-semibold text-blue-700">{fmtBrl(r.total_valor)}</p>
                <p className="text-gray-400 text-xs">{Number(r.total_litros).toFixed(1)} L</p>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Data', 'Veículo', 'Motorista', 'Posto', 'Combustível', 'Litros', 'R$/L', 'Total', 'KM', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(lista ?? []).map((a) => (
              <tr key={a.id} className="hover:bg-gray-50 transition-colors">
                <td className="px-4 py-3 text-gray-500">{fmtDt(a.abastecido_at)}</td>
                <td className="px-4 py-3 font-mono font-semibold text-gray-800">{a.veiculo?.placa ?? '—'}</td>
                <td className="px-4 py-3 text-gray-600">{a.motorista?.nome?.split(' ')[0] ?? '—'}</td>
                <td className="px-4 py-3 text-gray-500">{a.posto ?? '—'}</td>
                <td className="px-4 py-3 text-gray-500 capitalize">{a.combustivel?.replace(/_/g, ' ')}</td>
                <td className="px-4 py-3 text-gray-700">{Number(a.litros).toFixed(2)} L</td>
                <td className="px-4 py-3 text-gray-700">{fmtBrl(a.valor_litro)}</td>
                <td className="px-4 py-3 font-semibold text-gray-800">{fmtBrl(a.valor_total)}</td>
                <td className="px-4 py-3 text-gray-500">{Number(a.km_momento).toLocaleString('pt-BR')}</td>
                <td className="px-4 py-3">
                  {isGestor && (
                    <button onClick={() => excluir.mutate(a.id)} className="text-gray-400 hover:text-red-600 transition-colors"><Trash2 size={14} /></button>
                  )}
                </td>
              </tr>
            ))}
            {(lista ?? []).length === 0 && (
              <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Nenhum abastecimento registrado</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={formOpen} onClose={() => setFormOpen(false)} title="Novo abastecimento" size="lg">
        <AbastecimentoForm onSuccess={() => { setFormOpen(false); qc.invalidateQueries({ queryKey: ['abastecimentos'] }) }} />
      </Modal>
    </div>
  )
}
