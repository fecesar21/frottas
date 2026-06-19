import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as motoristasApi from '../../api/motoristas'
import Alert from '../../components/ui/Alert'

export default function MotoristaForm({ motorista, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: motorista?.nome ?? '',
    cpf: motorista?.cpf ?? '',
    telefone: motorista?.telefone ?? '',
    email: motorista?.email ?? '',
    cnh_numero: motorista?.cnh_numero ?? '',
    cnh_categoria: motorista?.cnh_categoria ?? 'D',
    cnh_validade: motorista?.cnh_validade ?? '',
    turno_padrao: motorista?.turno_padrao ?? '',
    observacoes: motorista?.observacoes ?? '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) => motorista ? motoristasApi.atualizar(motorista.id, data) : motoristasApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['motoristas'] }); onSuccess() },
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
    Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null })
    salvar.mutate(payload)
  }

  const fe = (key) => fieldErrors[key] ? <p className="text-red-500 text-xs mt-1">{fieldErrors[key][0]}</p> : null
  const inp = (key, type = 'text', extra = {}) => (
    <input type={type} value={form[key] ?? ''} onChange={set(key)}
      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      {...extra} />
  )

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="grid grid-cols-2 gap-4">
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
          {inp('nome', 'text', { required: true })} {fe('nome')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
          {inp('cpf', 'text', { required: true, placeholder: '000.000.000-00' })} {fe('cpf')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
          {inp('telefone')} {fe('telefone')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
          {inp('email', 'email')} {fe('email')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">CNH *</label>
          {inp('cnh_numero', 'text', { required: true })} {fe('cnh_numero')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Categoria *</label>
          <select value={form.cnh_categoria} onChange={set('cnh_categoria')} required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            {['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'].map(c => <option key={c}>{c}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Validade CNH *</label>
          {inp('cnh_validade', 'date', { required: true })} {fe('cnh_validade')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Turno padrão</label>
          <select value={form.turno_padrao} onChange={set('turno_padrao')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Não definido</option>
            <option value="dia">Dia</option>
            <option value="noite">Noite</option>
          </select>
        </div>
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
          <textarea value={form.observacoes ?? ''} onChange={set('observacoes')} rows={3}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div className="flex justify-end gap-3 pt-2">
        <button type="submit" disabled={salvar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors">
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
