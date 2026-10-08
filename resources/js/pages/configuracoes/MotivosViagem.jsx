import { useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { Plus, Pencil, ListChecks } from 'lucide-react'
import * as motivosApi from '../../api/motivosViagem'
import Modal from '../../components/ui/Modal'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Alert from '../../components/ui/Alert'

const TIPOS = { administrativo: 'Administrativo', ambulancia: 'Ambulância', ambos: 'Ambos' }

function MotivoForm({ motivo, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: motivo?.nome ?? '',
    tipo_veiculo: motivo?.tipo_veiculo ?? 'ambos',
    disponivel_solicitacao: motivo?.disponivel_solicitacao ?? false,
    ativo: motivo?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) => (motivo ? motivosApi.atualizar(motivo.id, data) : motivosApi.criar(data)),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['motivos-viagem'] })
      onSuccess()
    },
    onError: (e) => {
      if (e.response?.data?.errors) {
        setFieldErrors(e.response.data.errors)
        setError(e.response.data.message ?? '')
      } else {
        setError(e.response?.data?.message ?? 'Erro ao salvar')
      }
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    salvar.mutate(form)
  }

  const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100'

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label htmlFor="mv-nome" className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
        <input id="mv-nome" type="text" required maxLength={100} value={form.nome}
          onChange={set('nome')} className={inputCls} />
        {fieldErrors.nome && <p className="text-red-500 text-xs mt-1">{fieldErrors.nome[0]}</p>}
      </div>

      <div>
        <label htmlFor="mv-tipo" className="block text-sm font-medium text-gray-700 mb-1">Tipo de veículo</label>
        <select id="mv-tipo" value={form.tipo_veiculo} onChange={set('tipo_veiculo')}
          disabled={!!motivo?.sistema} className={inputCls}>
          {Object.entries(TIPOS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
        </select>
        {motivo?.sistema && (
          <p className="text-xs text-gray-500 mt-1">O tipo de veículo de um motivo do sistema não pode ser alterado.</p>
        )}
        {fieldErrors.tipo_veiculo && <p className="text-red-500 text-xs mt-1">{fieldErrors.tipo_veiculo[0]}</p>}
      </div>

      <div className="flex items-center gap-2">
        <input id="mv-disp" type="checkbox" className="rounded"
          checked={form.disponivel_solicitacao} onChange={set('disponivel_solicitacao')} />
        <label htmlFor="mv-disp" className="text-sm text-gray-700">Disponível em solicitações</label>
      </div>

      <div className="flex items-center gap-2">
        <input id="mv-ativo" type="checkbox" className="rounded"
          checked={form.ativo} onChange={set('ativo')} />
        <label htmlFor="mv-ativo" className="text-sm text-gray-700">Ativo</label>
      </div>

      <div className="flex justify-end pt-2">
        <button type="submit" disabled={salvar.isPending}
          className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors">
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}

export default function MotivosViagem() {
  const qc = useQueryClient()
  const [formOpen, setFormOpen] = useState(false)
  const [editTarget, setEditTarget] = useState(null)
  const [mostrarInativos, setMostrarInativos] = useState(false)
  const [confirmarId, setConfirmarId] = useState(null)
  const [error, setError] = useState('')

  const { data, isLoading } = useQuery({
    queryKey: ['motivos-viagem', { todos: 1 }],
    queryFn: () => motivosApi.listar({ todos: 1 }).then(r => r.data.data),
  })

  const invalidar = () => qc.invalidateQueries({ queryKey: ['motivos-viagem'] })
  const msg = (e, padrao) => e.response?.data?.message ?? padrao

  const alternar = useMutation({
    mutationFn: ({ id, ativo }) => motivosApi.atualizar(id, { ativo }),
    onSuccess: () => { setError(''); invalidar() },
    onError: (e) => setError(msg(e, 'Erro ao atualizar')),
  })

  const excluir = useMutation({
    mutationFn: (id) => motivosApi.excluir(id),
    onSuccess: () => { setError(''); setConfirmarId(null); invalidar() },
    onError: (e) => { setConfirmarId(null); setError(msg(e, 'Erro ao excluir')) },
  })

  const openEdit = (m) => { setEditTarget(m); setFormOpen(true) }
  const closeForm = () => { setFormOpen(false); setEditTarget(null) }

  if (isLoading) return <LoadingSpinner />

  const lista = (data ?? []).filter(m => mostrarInativos || m.ativo)

  return (
    <div className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="flex items-center justify-between">
        <label className="flex items-center gap-2 text-sm text-gray-600">
          <input type="checkbox" className="rounded" checked={mostrarInativos}
            onChange={(e) => setMostrarInativos(e.target.checked)} />
          Mostrar inativos
        </label>
        <button onClick={() => setFormOpen(true)}
          className="flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 transition-colors">
          <Plus size={16} /> Novo motivo
        </button>
      </div>

      {lista.length === 0 ? (
        <div className="bg-white rounded-xl border border-gray-200 px-6 py-12 text-center text-gray-400">
          <ListChecks size={36} className="mx-auto mb-3 opacity-30" />
          <p>Nenhum motivo cadastrado</p>
        </div>
      ) : (
        <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-left text-xs text-gray-500 uppercase">
              <tr>
                <th className="px-5 py-3">Nome</th>
                <th className="px-5 py-3">Tipo de veículo</th>
                <th className="px-5 py-3">Solicitações</th>
                <th className="px-5 py-3">Status</th>
                <th className="px-5 py-3 text-right">Ações</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {lista.map(m => (
                <tr key={m.id} className={!m.ativo ? 'opacity-60' : ''}>
                  <td className="px-5 py-3 font-medium text-gray-800">
                    {m.nome}
                    {m.sistema && (
                      <span className="ml-2 text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 font-medium">Sistema</span>
                    )}
                  </td>
                  <td className="px-5 py-3 text-gray-600">{TIPOS[m.tipo_veiculo] ?? m.tipo_veiculo}</td>
                  <td className="px-5 py-3 text-gray-600">{m.disponivel_solicitacao ? 'Sim' : 'Não'}</td>
                  <td className="px-5 py-3">
                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${
                      m.ativo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'
                    }`}>{m.ativo ? 'Ativo' : 'Inativo'}</span>
                  </td>
                  <td className="px-5 py-3">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => openEdit(m)} title="Editar" aria-label={`Editar ${m.nome}`}
                        className="text-gray-400 hover:text-blue-600 p-1.5 rounded-lg hover:bg-blue-50 transition-colors">
                        <Pencil size={15} />
                      </button>
                      <button
                        onClick={() => alternar.mutate({ id: m.id, ativo: !m.ativo })}
                        disabled={alternar.isPending}
                        className={`text-xs px-2.5 py-1 rounded-lg font-medium transition-colors ${
                          m.ativo ? 'text-red-500 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'
                        }`}>
                        {m.ativo ? 'Inativar' : 'Reativar'}
                      </button>
                      {!m.sistema && !m.em_uso && (
                        confirmarId === m.id ? (
                          <>
                            <button onClick={() => excluir.mutate(m.id)} disabled={excluir.isPending}
                              className="text-xs px-2.5 py-1 rounded-lg font-medium bg-red-600 text-white hover:bg-red-700">
                              Confirmar exclusão
                            </button>
                            <button onClick={() => setConfirmarId(null)}
                              className="text-xs px-2.5 py-1 rounded-lg text-gray-500 hover:bg-gray-100">
                              Cancelar
                            </button>
                          </>
                        ) : (
                          <button onClick={() => setConfirmarId(m.id)}
                            className="text-xs px-2.5 py-1 rounded-lg font-medium text-red-500 hover:bg-red-50">
                            Excluir
                          </button>
                        )
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={formOpen} onClose={closeForm} title={editTarget ? 'Editar motivo' : 'Novo motivo'}>
        <MotivoForm key={editTarget?.id ?? 'novo'} motivo={editTarget} onSuccess={closeForm} />
      </Modal>
    </div>
  )
}
