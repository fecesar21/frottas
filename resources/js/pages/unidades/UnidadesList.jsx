import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { Plus, Pencil, ChevronRight, Building2 } from 'lucide-react'
import * as unidadesApi from '../../api/unidades'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'
import UnidadeForm from './UnidadeForm'

export default function UnidadesList() {
  const qc = useQueryClient()
  const navigate = useNavigate()
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data),
  })

  const desativar = useMutation({
    mutationFn: (id) => unidadesApi.desativar(id),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['unidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao desativar'),
  })

  const reativar = useMutation({
    mutationFn: (id) => unidadesApi.atualizar(id, { ativo: true }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['unidades'] }),
    onError: (e) => setError(e.response?.data?.message ?? 'Erro ao reativar'),
  })

  const openEdit = (u, e) => {
    e.stopPropagation()
    setEditTarget(u)
    setFormOpen(true)
  }

  const closeForm = () => {
    setFormOpen(false)
    setEditTarget(null)
  }

  if (isLoading) return <LoadingSpinner />

  const lista = data ?? []
  const matrizes = lista.filter(u => u.tipo === 'matriz')
  const filiais = lista.filter(u => u.tipo === 'filial')

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex justify-end">
        <button
          onClick={() => setFormOpen(true)}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors"
        >
          <Plus size={16} /> Nova unidade
        </button>
      </div>

      {lista.length === 0 && (
        <div className="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center text-gray-400">
          <Building2 size={36} className="mx-auto mb-3 opacity-30" />
          <p>Nenhuma unidade cadastrada</p>
        </div>
      )}

      {[{ label: 'Matriz', items: matrizes }, { label: 'Filiais', items: filiais }]
        .filter(g => g.items.length > 0)
        .map(grupo => (
          <div key={grupo.label}>
            <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 px-1">
              {grupo.label}
            </p>
            <div className="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
              {grupo.items.map(u => (
                <div
                  key={u.id}
                  onClick={() => navigate(`/unidades/${u.id}`)}
                  className={`flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors ${!u.ativo ? 'opacity-60' : ''}`}
                >
                  <div className="flex items-center gap-3">
                    <div className={`w-9 h-9 rounded-lg flex items-center justify-center ${
                      u.tipo === 'matriz' ? 'bg-purple-100' : 'bg-blue-100'
                    }`}>
                      <Building2 size={18} className={u.tipo === 'matriz' ? 'text-purple-600' : 'text-blue-600'} />
                    </div>
                    <div>
                      <p className="text-sm font-medium text-gray-800">{u.nome}</p>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                          u.tipo === 'matriz' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
                        }`}>
                          {u.tipo === 'matriz' ? 'Matriz' : 'Filial'}
                        </span>
                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                          u.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                        }`}>
                          {u.ativo ? 'Ativa' : 'Inativa'}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                    <button
                      onClick={(e) => openEdit(u, e)}
                      title="Editar"
                      className="text-gray-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition-colors"
                    >
                      <Pencil size={15} />
                    </button>
                    <button
                      onClick={() => u.ativo ? desativar.mutate(u.id) : reativar.mutate(u.id)}
                      disabled={desativar.isPending || reativar.isPending}
                      title={u.ativo ? 'Inativar' : 'Reativar'}
                      className={`text-xs px-2.5 py-1 rounded-lg font-medium transition-colors ${
                        u.ativo
                          ? 'text-red-500 hover:bg-red-50'
                          : 'text-green-600 hover:bg-green-50'
                      }`}
                    >
                      {u.ativo ? 'Inativar' : 'Reativar'}
                    </button>
                    <ChevronRight size={16} className="text-gray-300" />
                  </div>
                </div>
              ))}
            </div>
          </div>
        ))}

      <Modal
        open={formOpen}
        onClose={closeForm}
        title={editTarget ? 'Editar unidade' : 'Nova unidade'}
      >
        <UnidadeForm unidade={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
