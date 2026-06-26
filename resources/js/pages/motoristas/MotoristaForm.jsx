import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as motoristasApi from '../../api/motoristas'
import Alert from '../../components/ui/Alert'

function mascaraCpf(v) {
  const d = v.replace(/\D/g, '').slice(0, 11)
  if (d.length <= 3) return d
  if (d.length <= 6) return `${d.slice(0,3)}.${d.slice(3)}`
  if (d.length <= 9) return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6)}`
  return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`
}

function mascaraTelefone(v) {
  const d = v.replace(/\D/g, '').slice(0, 11)
  if (d.length <= 2) return d
  if (d.length <= 7) return `(${d.slice(0,2)}) ${d.slice(2)}`
  return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`
}

export default function MotoristaForm({ motorista, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: motorista?.nome ?? '',
    cpf: mascaraCpf(motorista?.cpf ?? ''),
    telefone: mascaraTelefone(motorista?.telefone ?? ''),
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
  const setMask = (k, fn) => (e) => setForm(f => ({ ...f, [k]: fn(e.target.value) }))

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
    if (payload.cpf)      payload.cpf      = payload.cpf.replace(/\D/g, '')
    if (payload.telefone) payload.telefone = payload.telefone.replace(/\D/g, '')
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
          <input type="text" required value={form.cpf} onChange={setMask('cpf', mascaraCpf)}
            placeholder="000.000.000-00" inputMode="numeric"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('cpf')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
          <input type="text" value={form.telefone} onChange={setMask('telefone', mascaraTelefone)}
            placeholder="(00) 00000-0000" inputMode="numeric"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('telefone')}
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
