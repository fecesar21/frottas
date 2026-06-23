import { useState, useMemo } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as usuariosApi from '../../api/usuarios'
import * as motoristasApi from '../../api/motoristas'
import Alert from '../../components/ui/Alert'

export default function UsuarioForm({ usuario, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: usuario?.nome ?? '',
    cpf: usuario?.cpf ?? '',
    email: usuario?.email ?? '',
    senha: '',
    perfil: usuario?.perfil ?? 'operador',
    ativo: usuario?.ativo ?? true,
    motorista_id: usuario?.motorista_id ?? '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const handleCpfChange = (e) => {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11)
    if (v.length > 9)      v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4')
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3')
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2')
    setForm(f => ({ ...f, cpf: v }))
  }

  const { data: disponiveis = [] } = useQuery({
    queryKey: ['motoristas', 'disponiveis'],
    queryFn: () => motoristasApi.listarDisponiveis().then(r => r.data.data ?? r.data),
    enabled: form.perfil === 'operador',
  })

  // Quando editando um operador já vinculado, busca o motorista atual (não aparece em disponiveis)
  const { data: motoristaAtual } = useQuery({
    queryKey: ['motoristas', usuario?.motorista_id],
    queryFn: () => motoristasApi.buscar(usuario.motorista_id).then(r => r.data.data ?? r.data),
    enabled: form.perfil === 'operador' && !!usuario?.motorista_id,
  })

  const opcoesMotorista = useMemo(() => {
    const lista = [...disponiveis]
    if (motoristaAtual && !lista.find(m => m.id === motoristaAtual.id)) {
      lista.unshift(motoristaAtual)
    }
    return lista
  }, [disponiveis, motoristaAtual])

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
    if (payload.perfil !== 'operador') delete payload.motorista_id
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
        <label className="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
        <input type="text" value={form.cpf} onChange={handleCpfChange}
          placeholder="000.000.000-00" required={!usuario} maxLength={14}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('cpf')}
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

      {form.perfil === 'operador' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Motorista vinculado *</label>
          <select
            value={form.motorista_id}
            onChange={set('motorista_id')}
            required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="">Selecione um motorista...</option>
            {opcoesMotorista.map(m => (
              <option key={m.id} value={m.id}>
                {m.nome} — {m.cpf}
              </option>
            ))}
          </select>
          {opcoesMotorista.length === 0 && (
            <p className="text-amber-600 text-xs mt-1">Nenhum motorista disponível para vincular.</p>
          )}
          {fe('motorista_id')}
        </div>
      )}

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
