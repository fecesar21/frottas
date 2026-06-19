import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, Trash2, AlertTriangle } from 'lucide-react'
import { format, parseISO } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import * as motoristasApi from '../../api/motoristas'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import MotoristaForm from './MotoristaForm'
import { useAuth } from '../../contexts/AuthContext'

export default function MotoristasList() {
  const { isGestor } = useAuth()
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState('ativo')
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)

  const { data, isLoading } = useQuery({
    queryKey: ['motoristas', statusFilter],
    queryFn: () => motoristasApi.listar({ status: statusFilter || undefined }).then(r => r.data.data ?? r.data),
  })

  const desativar = useMutation({
    mutationFn: (id) => motoristasApi.desativar(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['motoristas'] }),
  })

  const closeForm = () => { setFormOpen(false); setEditTarget(null) }

  const cnhStatus = (dias) => {
    if (dias < 0) return 'vencida'
    if (dias <= 30) return 'vencendo'
    return null
  }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          {[{ v: '', l: 'Todos' }, { v: 'ativo', l: 'Ativos' }, { v: 'inativo', l: 'Inativos' }].map(({ v, l }) => (
            <button key={v} onClick={() => setStatusFilter(v)}
              className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${statusFilter === v ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:border-blue-400'}`}>
              {l}
            </button>
          ))}
        </div>
        {isGestor && (
          <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
            <Plus size={16} /> Novo motorista
          </button>
        )}
      </div>

      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Nome', 'CPF', 'CNH', 'Cat.', 'Validade CNH', 'Turno', 'Status', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((m) => {
              const dias = m.dias_para_vencer_cnh
              const alerta = cnhStatus(dias)
              return (
                <tr key={m.id} className="hover:bg-gray-50 transition-colors">
                  <td className="px-4 py-3 font-medium text-gray-800">{m.nome}</td>
                  <td className="px-4 py-3 text-gray-500 font-mono">{m.cpf}</td>
                  <td className="px-4 py-3 text-gray-500 font-mono">{m.cnh_numero}</td>
                  <td className="px-4 py-3 text-gray-700 font-bold">{m.cnh_categoria}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1.5">
                      {m.cnh_validade}
                      {alerta && (
                        <span className="flex items-center gap-1">
                          <AlertTriangle size={13} className={alerta === 'vencida' ? 'text-red-500' : 'text-yellow-500'} />
                          <Badge value={alerta} />
                        </span>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3 text-gray-500 capitalize">{m.turno_padrao ?? '—'}</td>
                  <td className="px-4 py-3"><Badge value={m.status} /></td>
                  <td className="px-4 py-3">
                    {isGestor && (
                      <div className="flex items-center gap-2">
                        <button onClick={() => { setEditTarget(m); setFormOpen(true) }} className="text-gray-400 hover:text-blue-600 transition-colors"><Pencil size={15} /></button>
                        {m.status === 'ativo' && (
                          <button onClick={() => desativar.mutate(m.id)} className="text-gray-400 hover:text-red-600 transition-colors"><Trash2 size={15} /></button>
                        )}
                      </div>
                    )}
                  </td>
                </tr>
              )
            })}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Nenhum motorista encontrado</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={formOpen} onClose={closeForm} title={editTarget ? 'Editar motorista' : 'Novo motorista'} size="lg">
        <MotoristaForm motorista={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
