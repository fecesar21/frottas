import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil } from 'lucide-react'
import * as veiculosApi from '../../api/veiculos'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import VeiculoForm from './VeiculoForm'
import { useAuth } from '../../contexts/AuthContext'

const fmt = (n) => Number(n ?? 0).toLocaleString('pt-BR')

function tempoDecorrido(isoDate) {
  if (!isoDate) return ''
  const diff = Math.floor((Date.now() - new Date(isoDate).getTime()) / 1000)
  if (diff < 3600) return `${Math.floor(diff / 60)}min`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h`
  return `${Math.floor(diff / 86400)}d`
}

export default function VeiculosList() {
  const { isGestor } = useAuth()
  const qc = useQueryClient()
  const [statusFilter, setStatusFilter] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)
  const [statusError, setStatusError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['veiculos', statusFilter],
    queryFn: () => veiculosApi.listar(statusFilter ? { status: statusFilter } : undefined).then(r => r.data.data ?? r.data),
  })

  const mudarStatus = useMutation({
    mutationFn: ({ id, status }) => veiculosApi.atualizarStatus(id, status),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['veiculos'] }),
    onError: (e) => setStatusError(e.response?.data?.message ?? 'Erro ao atualizar status'),
  })

  const openEdit = (v) => { setEditTarget(v); setFormOpen(true) }
  const closeForm = () => { setFormOpen(false); setEditTarget(null) }

  const toggleAtivo = (v) => {
    const novoStatus = v.status === 'inativo' ? 'disponivel' : 'inativo'
    mudarStatus.mutate({ id: v.id, status: novoStatus })
  }

  const toggleManutencao = (v) => {
    const novoStatus = v.status === 'manutencao' ? 'disponivel' : 'manutencao'
    mudarStatus.mutate({ id: v.id, status: novoStatus })
  }

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex gap-2">
          {['', 'disponivel', 'em_uso', 'manutencao', 'inativo'].map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={`px-3 py-1.5 text-sm rounded-lg border transition-colors ${statusFilter === s ? 'bg-blue-600 text-white border-blue-600' : 'border-gray-300 text-gray-600 hover:border-blue-400'}`}
            >
              {s === '' ? 'Todos' : <Badge value={s} />}
            </button>
          ))}
        </div>
        {isGestor && (
          <button onClick={() => setFormOpen(true)} className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
            <Plus size={16} /> Novo veículo
          </button>
        )}
      </div>

      {statusError && <Alert type="error" message={statusError} />}

      <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <table className="w-full text-sm">
          <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              {['Placa', 'Modelo', 'Marca', 'Ano', 'Combustível', 'KM Atual', 'Status', 'Ativo', 'Manutenção', ''].map(h => (
                <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {(data ?? []).map((v) => (
              <tr key={v.id} className={`hover:bg-gray-50 transition-colors ${v.status === 'inativo' ? 'opacity-60' : ''}`}>
                <td className="px-4 py-3 font-mono font-semibold text-gray-800">{v.placa}</td>
                <td className="px-4 py-3 text-gray-700">{v.modelo}</td>
                <td className="px-4 py-3 text-gray-500">{v.marca ?? '—'}</td>
                <td className="px-4 py-3 text-gray-500">{v.ano}</td>
                <td className="px-4 py-3 text-gray-500 capitalize">{v.combustivel?.replace(/_/g, ' ')}</td>
                <td className="px-4 py-3 text-gray-700">{fmt(v.km_atual)} km</td>
                <td className="px-4 py-3"><Badge value={v.status} /></td>

                <td className="px-4 py-3">
                  {isGestor ? (
                    <input
                      type="checkbox"
                      checked={v.status !== 'inativo'}
                      disabled={v.status === 'em_uso' || mudarStatus.isPending}
                      onChange={() => toggleAtivo(v)}
                      title={v.status === 'em_uso' ? 'Veículo em uso' : v.status === 'inativo' ? 'Reativar' : 'Inativar'}
                      className="w-4 h-4 accent-blue-600 cursor-pointer disabled:cursor-not-allowed"
                    />
                  ) : (
                    <span className={`inline-block w-2 h-2 rounded-full ${v.status !== 'inativo' ? 'bg-green-500' : 'bg-gray-400'}`} />
                  )}
                </td>

                <td className="px-4 py-3">
                  <div className="flex items-center gap-1.5">
                    {isGestor ? (
                      <input
                        type="checkbox"
                        checked={v.status === 'manutencao'}
                        disabled={v.status === 'inativo' || v.status === 'em_uso' || mudarStatus.isPending}
                        onChange={() => toggleManutencao(v)}
                        title={v.status === 'inativo' ? 'Veículo inativo' : v.status === 'em_uso' ? 'Veículo em uso' : v.status === 'manutencao' ? 'Tirar da manutenção' : 'Colocar em manutenção'}
                        className="w-4 h-4 accent-yellow-500 cursor-pointer disabled:cursor-not-allowed"
                      />
                    ) : (
                      <span className={`inline-block w-2 h-2 rounded-full ${v.status === 'manutencao' ? 'bg-yellow-500' : 'bg-gray-200'}`} />
                    )}
                    {v.status === 'manutencao' && v.manutencao_inicio && (
                      <span className="text-xs text-yellow-600 font-medium">{tempoDecorrido(v.manutencao_inicio)}</span>
                    )}
                  </div>
                </td>

                <td className="px-4 py-3">
                  {isGestor && (
                    <button onClick={() => openEdit(v)} className="text-gray-400 hover:text-blue-600 transition-colors">
                      <Pencil size={15} />
                    </button>
                  )}
                </td>
              </tr>
            ))}
            {(data ?? []).length === 0 && (
              <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Nenhum veículo encontrado</td></tr>
            )}
          </tbody>
        </table>
      </div>

      <Modal open={formOpen} onClose={closeForm} title={editTarget ? 'Editar veículo' : 'Novo veículo'} size="lg">
        <VeiculoForm veiculo={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
