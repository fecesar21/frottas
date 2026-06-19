import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as usuariosApi from '../../api/usuarios'
import Alert from '../../components/ui/Alert'

export default function UsuarioForm({ usuario, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: usuario?.nome ?? '',
    email: usuario?.email ?? '',
    senha: '',
    perfil: usuario?.perfil ?? 'operador',
    ativo: usuario?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) => usuario ? usuariosApi.atualizar(usuario.id, data) : usuariosApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['usuarios'] }); onSuccess() },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao salvar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    const payload = { ...form }
    if (!payload.senha) delete payload.senha
    if (!payload.email) payload.email = null
    salvar.mutate(payload)
  }

  const fe = (k) => fieldErrors[k] && <p className="text-red-500 text-xs mt-1">{fieldErrors[k][0]}</p>

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
        <input type="text" required value={form.nome} onChange={set('nome')}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('nome')}
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
        <input type="email" value={form.email} onChange={set('email')}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('email')}
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Senha {usuario ? '(deixe em branco para manter)' : '*'}
        </label>
        <input type="password" value={form.senha} onChange={set('senha')} required={!usuario} minLength={6}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('senha')}
      </div>
      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Perfil *</label>
        <select value={form.perfil} onChange={set('perfil')} required
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="operador">Operador</option>
          <option value="gestor">Gestor</option>
          <option value="admin">Admin</option>
        </select>
      </div>
      {usuario && (
        <div className="flex items-center gap-2">
          <input type="checkbox" id="ativo" checked={form.ativo} onChange={set('ativo')} className="rounded" />
          <label htmlFor="ativo" className="text-sm text-gray-700">Usuário ativo</label>
        </div>
      )}

      <div className="flex justify-end pt-2">
        <button type="submit" disabled={salvar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
