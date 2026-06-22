import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import * as abastecimentosApi from '../../api/abastecimentos'
import { useAuth } from '../../contexts/AuthContext'
import MotoristaSelect from '../../components/shared/MotoristaSelect'
import VeiculoSelect from '../../components/shared/VeiculoSelect'
import Alert from '../../components/ui/Alert'

const combustiveis = ['diesel_s10', 'diesel_s500', 'gasolina', 'gasolina_aditivada', 'etanol', 'gnv', 'flex']

export default function AbastecimentoForm({ onSuccess }) {
  const { user, isOperador, checkinAtivo } = useAuth()

  const [form, setForm] = useState({
    motorista_id: isOperador ? user.motorista_id : '',
    veiculo_id:   isOperador ? (checkinAtivo?.veiculo_id ?? '') : '',
    posto: '',
    combustivel: 'diesel_s10',
    litros: '',
    valor_litro: '',
    km_momento: '',
    nota_fiscal: '',
    observacoes: '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const criar = useMutation({
    mutationFn: (data) => abastecimentosApi.criar(data),
    onSuccess,
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao registrar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    criar.mutate({
      ...form,
      litros: Number(form.litros),
      valor_litro: Number(form.valor_litro),
      km_momento: Number(form.km_momento),
    })
  }

  const valorTotal = form.litros && form.valor_litro
    ? (Number(form.litros) * Number(form.valor_litro)).toFixed(2)
    : null

  const fe = (k) => fieldErrors[k] && <p className="text-red-500 text-xs mt-1">{fieldErrors[k][0]}</p>

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="grid grid-cols-2 gap-4">
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Motorista *</label>
          {isOperador ? (
            <div className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700">
              {user.nome}
            </div>
          ) : (
            <MotoristaSelect value={form.motorista_id} onChange={v => setForm(f => ({ ...f, motorista_id: v }))} required />
          )}
          {fe('motorista_id')}
        </div>

        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
          {isOperador ? (
            <div className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700">
              {checkinAtivo?.veiculo?.placa ?? checkinAtivo?.veiculo_id ?? '—'}
            </div>
          ) : (
            <VeiculoSelect value={form.veiculo_id} onChange={v => setForm(f => ({ ...f, veiculo_id: v }))} required />
          )}
          {fe('veiculo_id')}
        </div>

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Posto</label>
          <input type="text" value={form.posto} onChange={e => setForm(f => ({ ...f, posto: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Combustível *</label>
          <select value={form.combustivel} onChange={e => setForm(f => ({ ...f, combustivel: e.target.value }))} required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            {combustiveis.map(c => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Litros *</label>
          <input type="number" step="0.01" min="0" required value={form.litros} onChange={e => setForm(f => ({ ...f, litros: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('litros')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Preço por litro *</label>
          <input type="number" step="0.001" min="0" required value={form.valor_litro} onChange={e => setForm(f => ({ ...f, valor_litro: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        {valorTotal && (
          <div className="col-span-2 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 text-sm text-blue-800">
            Total estimado: <strong>R$ {Number(valorTotal).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</strong>
          </div>
        )}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">KM no momento *</label>
          <input type="number" min={0} required value={form.km_momento} onChange={e => setForm(f => ({ ...f, km_momento: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('km_momento')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Nota fiscal</label>
          <input type="text" value={form.nota_fiscal} onChange={e => setForm(f => ({ ...f, nota_fiscal: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar abastecimento'}
        </button>
      </div>
    </form>
  )
}
