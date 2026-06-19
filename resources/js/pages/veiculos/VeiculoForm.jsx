import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as veiculosApi from '../../api/veiculos'
import Alert from '../../components/ui/Alert'

const combustiveis = ['diesel_s10', 'diesel_s500', 'gasolina', 'gasolina_aditivada', 'etanol', 'gnv', 'flex']

export default function VeiculoForm({ veiculo, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    placa: veiculo?.placa ?? '',
    modelo: veiculo?.modelo ?? '',
    marca: veiculo?.marca ?? '',
    ano: veiculo?.ano ?? new Date().getFullYear(),
    cor: veiculo?.cor ?? '',
    chassi: veiculo?.chassi ?? '',
    renavam: veiculo?.renavam ?? '',
    combustivel: veiculo?.combustivel ?? 'diesel_s10',
    capacidade_tanque: veiculo?.capacidade_tanque ?? '',
    km_atual: veiculo?.km_atual ?? 0,
    km_proxima_revisao: veiculo?.km_proxima_revisao ?? '',
    observacoes: veiculo?.observacoes ?? '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) => veiculo ? veiculosApi.atualizar(veiculo.id, data) : veiculosApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['veiculos'] }); onSuccess() },
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

  const field = (label, key, type = 'text', extra = {}) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input
        type={type}
        value={form[key] ?? ''}
        onChange={set(key)}
        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        {...extra}
      />
      {fieldErrors[key] && <p className="text-red-500 text-xs mt-1">{fieldErrors[key][0]}</p>}
    </div>
  )

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="grid grid-cols-2 gap-4">
        {field('Placa *', 'placa', 'text', { required: true, maxLength: 10, style: { textTransform: 'uppercase' } })}
        {field('Modelo *', 'modelo', 'text', { required: true })}
        {field('Marca', 'marca')}
        {field('Ano *', 'ano', 'number', { required: true, min: 1980 })}
        {field('Cor', 'cor')}
        {field('Chassi', 'chassi', 'text', { maxLength: 17 })}
        {field('RENAVAM', 'renavam', 'text', { maxLength: 11 })}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Combustível</label>
          <select value={form.combustivel} onChange={set('combustivel')} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            {combustiveis.map(c => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
        {field('Capacidade do tanque (L)', 'capacidade_tanque', 'number')}
        {field('KM atual', 'km_atual', 'number', { min: 0 })}
        {field('KM próxima revisão', 'km_proxima_revisao', 'number')}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
        <textarea value={form.observacoes ?? ''} onChange={set('observacoes')} rows={3}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div className="flex justify-end gap-3 pt-2">
        <button type="submit" disabled={salvar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors">
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
