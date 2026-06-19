import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import * as plantaoApi from '../../api/plantao'
import * as motoristasApi from '../../api/motoristas'
import VeiculoSelect from '../../components/shared/VeiculoSelect'
import Alert from '../../components/ui/Alert'

export default function PlantaoForm({ onSuccess }) {
  const [form, setForm] = useState({
    veiculo_id: '',
    motorista_saindo_id: '',
    motorista_entrando_id: '',
    turno_saindo: 'dia',
    turno_entrando: 'noite',
    km_momento: '',
    nivel_combustivel: '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const { data: motoristas } = useQuery({
    queryKey: ['motoristas', 'ativos'],
    queryFn: () => motoristasApi.listar({ status: 'ativo' }).then(r => r.data.data ?? r.data),
  })

  const criar = useMutation({
    mutationFn: (data) => plantaoApi.criar(data),
    onSuccess,
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao criar passagem')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    criar.mutate({ ...form, km_momento: Number(form.km_momento), nivel_combustivel: form.nivel_combustivel || null })
  }

  const sel = (key, label, options, req = false) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}{req ? ' *' : ''}</label>
      <select value={form[key]} onChange={e => setForm(f => ({ ...f, [key]: e.target.value }))} required={req}
        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
      </select>
      {fieldErrors[key] && <p className="text-red-500 text-xs mt-1">{fieldErrors[key][0]}</p>}
    </div>
  )

  const motoristaOptions = (motoristas ?? []).map(m => [m.id, m.nome])
  const turnoOptions = [['dia', 'Dia'], ['noite', 'Noite']]

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
        <VeiculoSelect value={form.veiculo_id} onChange={(v) => setForm(f => ({ ...f, veiculo_id: v }))} required />
      </div>

      <div className="grid grid-cols-2 gap-4">
        {sel('motorista_saindo_id', 'Motorista saindo', [['', 'Selecione'], ...motoristaOptions], true)}
        {sel('turno_saindo', 'Turno saindo', turnoOptions)}
        {sel('motorista_entrando_id', 'Motorista entrando', [['', 'Selecione'], ...motoristaOptions], true)}
        {sel('turno_entrando', 'Turno entrando', turnoOptions)}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">KM no momento *</label>
          <input type="number" min={0} required value={form.km_momento} onChange={e => setForm(f => ({ ...f, km_momento: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Nível combustível (%)</label>
          <input type="number" min={0} max={100} value={form.nivel_combustivel} onChange={e => setForm(f => ({ ...f, nivel_combustivel: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Criando...' : 'Criar passagem'}
        </button>
      </div>
    </form>
  )
}
