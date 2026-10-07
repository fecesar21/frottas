import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import * as checkinsApi from '../../api/checkins'
import * as motoristasApi from '../../api/motoristas'
import { useAuth } from '../../contexts/AuthContext'
import MotoristaSelect from '../../components/shared/MotoristaSelect'
import VeiculoSelect from '../../components/shared/VeiculoSelect'
import Alert from '../../components/ui/Alert'

export default function CheckinForm({ onSuccess }) {
  const qc = useQueryClient()
  const { user, isOperador, setCheckinAtivo } = useAuth()

  const [form, setForm] = useState({
    motorista_id: isOperador ? user.motorista_id : '',
    veiculo_id: '',
    turno: 'dia',
    km_saida: '',
    nivel_combustivel_saida: '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  // Turno fixo pelo cadastro do motorista (trocar de turno exigiria refazer o checklist)
  const { data: motorista } = useQuery({
    queryKey: ['motoristas', form.motorista_id],
    queryFn: () => motoristasApi.buscar(form.motorista_id).then(r => r.data.data ?? r.data),
    enabled: !!form.motorista_id,
  })
  const turnoPadrao = form.motorista_id ? motorista?.turno_padrao : null

  useEffect(() => {
    if (turnoPadrao) setForm(f => ({ ...f, turno: turnoPadrao }))
  }, [turnoPadrao])

  const criar = useMutation({
    mutationFn: (data) => checkinsApi.criar(data),
    onSuccess: (res) => {
      const checkin = res.data?.data ?? res.data
      if (isOperador && checkin) setCheckinAtivo(checkin)
      qc.invalidateQueries({ queryKey: ['checkins'] })
      qc.invalidateQueries({ queryKey: ['veiculos'] })
      onSuccess()
    },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao registrar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    criar.mutate({ ...form, km_saida: Number(form.km_saida), nivel_combustivel_saida: form.nivel_combustivel_saida || null })
  }

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
          <MotoristaSelect value={form.motorista_id} onChange={(v) => setForm(f => ({ ...f, motorista_id: v }))} required />
        )}
        {fieldErrors.motorista_id && <p className="text-red-500 text-xs mt-1">{fieldErrors.motorista_id[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
        <VeiculoSelect value={form.veiculo_id} onChange={(v) => setForm(f => ({ ...f, veiculo_id: v }))} required filterStatus="disponivel" />
        {fieldErrors.veiculo_id && <p className="text-red-500 text-xs mt-1">{fieldErrors.veiculo_id[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Turno *</label>
        <select value={form.turno} onChange={e => setForm(f => ({ ...f, turno: e.target.value }))}
          disabled={!!turnoPadrao}
          className="w-full disabled:bg-gray-50 disabled:text-gray-700 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="dia">Dia</option>
          <option value="noite">Noite</option>
        </select>
        {turnoPadrao && <p className="text-gray-500 text-xs mt-1">Definido pelo cadastro do motorista.</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">KM saída *</label>
        <input type="number" min={0} required value={form.km_saida} onChange={e => setForm(f => ({ ...f, km_saida: e.target.value }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fieldErrors.km_saida && <p className="text-red-500 text-xs mt-1">{fieldErrors.km_saida[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Nível combustível saída (%)</label>
        <input type="number" min={0} max={100} value={form.nivel_combustivel_saida} onChange={e => setForm(f => ({ ...f, nivel_combustivel_saida: e.target.value }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Registrando...' : 'Registrar check-in'}
        </button>
      </div>
    </form>
  )
}
