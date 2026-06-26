import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, AlertTriangle } from 'lucide-react'
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
  const [statusError, setStatusError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['motoristas', statusFilter],
    queryFn: () => motoristasApi.listar({ status: statusFilter || undefined }).then(r => r.data.data ?? r.data),
  })

  const mudarStatus = useMutation({
    mutationFn: ({ id, status }) => motoristasApi.atualizarStatus(id, status),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['motoristas'] }),
    onError: (e) => setStatusError(e.response?.data?.message ?? 'Erro ao atualizar status'),
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

      {statusError && <Alert type="error" message={statusError} />}

      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Nome', 'CPF', 'CNH', 'Cat.', 'Validade CNH', 'Turno', 'Status', 'Ativo', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((m) => {
              const dias = m.dias_para_vencer_cnh
              const alerta = cnhStatus(dias)
              return (
                <tr key={m.id} className={`hover:bg-gray-50 transition-colors ${m.status === 'inativo' ? 'opacity-60' : ''}`}>
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
                    {isGestor ? (
                      <input
                        type="checkbox"
                        checked={m.status !== 'inativo'}
                        disabled={mudarStatus.isPending}
                        onChange={() => mudarStatus.mutate({ id: m.id, status: m.status === 'inativo' ? 'ativo' : 'inativo' })}
                        title={m.status === 'inativo' ? 'Reativar motorista' : 'Inativar motorista'}
                        className="w-4 h-4 accent-blue-600 cursor-pointer disabled:cursor-not-allowed"
                      />
                    ) : (
                      <span className={`inline-block w-2 h-2 rounded-full ${m.status !== 'inativo' ? 'bg-green-500' : 'bg-gray-400'}`} />
                    )}
                  </td>
                  <td className="px-4 py-3">
                    {isGestor && (
                      <button onClick={() => { setEditTarget(m); setFormOpen(true) }} className="text-gray-400 hover:text-blue-600 transition-colors">
                        <Pencil size={15} />
                      </button>
                    )}
                  </td>
                </tr>
              )
            })}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Nenhum motorista encontrado</td></tr>
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
