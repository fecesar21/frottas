import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import * as viagensApi from '../../api/viagens'
import { useAuth } from '../../contexts/AuthContext'
import MotoristaSelect from '../../components/shared/MotoristaSelect'
import VeiculoSelect from '../../components/shared/VeiculoSelect'
import Alert from '../../components/ui/Alert'

export default function ViagemForm({ onSuccess }) {
  const { user, isOperador, checkinAtivo } = useAuth()

  const [form, setForm] = useState({
    motorista_id: isOperador ? user.motorista_id : '',
    veiculo_id:   isOperador ? (checkinAtivo?.veiculo_id ?? '') : '',
    origem: '',
    destino: '',
    km_saida: '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const criar = useMutation({
    mutationFn: (data) => viagensApi.criar(data),
    onSuccess,
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao criar viagem')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    criar.mutate({ ...form, km_saida: Number(form.km_saida) })
  }

  const fe = (k) => fieldErrors[k] && <p className="text-red-500 text-xs mt-1">{fieldErrors[k][0]}</p>

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
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

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
        {isOperador ? (
          <div className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700">
            {checkinAtivo?.veiculo?.placa ?? checkinAtivo?.veiculo_id ?? '—'}
          </div>
        ) : (
          <VeiculoSelect value={form.veiculo_id} onChange={v => setForm(f => ({ ...f, veiculo_id: v }))} required filterStatus="em_uso" />
        )}
        {fe('veiculo_id')}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Origem *</label>
          <input type="text" required value={form.origem} onChange={e => setForm(f => ({ ...f, origem: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('origem')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Destino *</label>
          <input type="text" required value={form.destino} onChange={e => setForm(f => ({ ...f, destino: e.target.value }))}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('destino')}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">KM saída *</label>
        <input type="number" min={0} required value={form.km_saida} onChange={e => setForm(f => ({ ...f, km_saida: e.target.value }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('km_saida')}
      </div>

      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Criando...' : 'Iniciar viagem'}
        </button>
      </div>
    </form>
  )
}
