import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, MapPin } from 'lucide-react'
import * as localidadesApi from '../../api/localidades'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import LocalidadeForm from './LocalidadeForm'

export default function LocalidadesList() {
  const qc = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['localidades'],
    queryFn: () => localidadesApi.listar().then(r => r.data),
  })

  const desativar = useMutation({
    mutationFn: (id) => localidadesApi.desativar(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['localidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao desativar'),
  })

  const reativar = useMutation({
    mutationFn: (id) => localidadesApi.atualizar(id, { ativo: true }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['localidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao reativar'),
  })

  const openEdit = (l) => {
    setEditTarget(l)
    setFormOpen(true)
  }

  const closeForm = () => {
    setFormOpen(false)
    setEditTarget(null)
  }

  if (isLoading) return <LoadingSpinner />

  const lista = data ?? []

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex justify-end">
        <button
          onClick={() => setFormOpen(true)}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors"
        >
          <Plus size={16} /> Nova localidade
        </button>
      </div>

      {lista.length === 0 && (
        <div className="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center text-gray-400">
          <MapPin size={36} className="mx-auto mb-3 opacity-30" />
          <p>Nenhuma localidade cadastrada</p>
        </div>
      )}

      {lista.length > 0 && (
        <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
          {lista.map(l => (
            <div
              key={l.id}
              className={`flex items-center justify-between px-5 py-4 ${!l.ativo ? 'opacity-60' : ''}`}
            >
              <div className="flex items-center gap-3">
                <div className="w-9 h-9 rounded-lg flex items-center justify-center bg-blue-100">
                  <MapPin size={18} className="text-blue-600" />
                </div>
                <div>
                  <p className="text-sm font-medium text-gray-800">{l.nome}</p>
                  <p className="text-xs text-gray-500">{l.endereco || 'Sem endereço cadastrado'}</p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                  l.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                }`}>
                  {l.ativo ? 'Ativa' : 'Inativa'}
                </span>
                <button
                  onClick={() => openEdit(l)}
                  title="Editar"
                  className="text-gray-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition-colors"
                >
                  <Pencil size={15} />
                </button>
                <button
                  onClick={() => l.ativo ? desativar.mutate(l.id) : reativar.mutate(l.id)}
                  disabled={desativar.isPending || reativar.isPending}
                  title={l.ativo ? 'Inativar' : 'Reativar'}
                  className={`text-xs px-2.5 py-1 rounded-lg font-medium transition-colors ${
                    l.ativo ? 'text-red-500 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'
                  }`}
                >
                  {l.ativo ? 'Inativar' : 'Reativar'}
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      <Modal
        open={formOpen}
        onClose={closeForm}
        title={editTarget ? 'Editar localidade' : 'Nova localidade'}
      >
        <LocalidadeForm localidade={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
