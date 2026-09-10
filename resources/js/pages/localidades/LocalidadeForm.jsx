import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as localidadesApi from '../../api/localidades'
import Alert from '../../components/ui/Alert'

export default function LocalidadeForm({ localidade, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: localidade?.nome ?? '',
    endereco: localidade?.endereco ?? '',
    latitude: localidade?.latitude ?? '',
    longitude: localidade?.longitude ?? '',
    telefone: localidade?.telefone ?? '',
    email: localidade?.email ?? '',
    ativo: localidade?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) =>
      localidade ? localidadesApi.atualizar(localidade.id, data) : localidadesApi.criar(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['localidades'] })
      onSuccess()
    },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao salvar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    salvar.mutate(form)
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
        <input
          type="text"
          required
          value={form.nome}
          onChange={set('nome')}
          maxLength={150}
          placeholder="Ex: Clínica Parceira ABC"
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {fieldErrors.nome && <p className="text-red-500 text-xs mt-1">{fieldErrors.nome[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Endereço</label>
        <input
          type="text"
          value={form.endereco}
          onChange={set('endereco')}
          maxLength={255}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {fieldErrors.endereco && <p className="text-red-500 text-xs mt-1">{fieldErrors.endereco[0]}</p>}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
          <input
            type="number"
            step="any"
            value={form.latitude}
            onChange={set('latitude')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.latitude && <p className="text-red-500 text-xs mt-1">{fieldErrors.latitude[0]}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
          <input
            type="number"
            step="any"
            value={form.longitude}
            onChange={set('longitude')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.longitude && <p className="text-red-500 text-xs mt-1">{fieldErrors.longitude[0]}</p>}
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
          <input
            type="text"
            value={form.telefone}
            onChange={set('telefone')}
            maxLength={20}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.telefone && <p className="text-red-500 text-xs mt-1">{fieldErrors.telefone[0]}</p>}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
          <input
            type="email"
            value={form.email}
            onChange={set('email')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          {fieldErrors.email && <p className="text-red-500 text-xs mt-1">{fieldErrors.email[0]}</p>}
        </div>
      </div>

      {localidade && (
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="ativo"
            checked={form.ativo}
            onChange={set('ativo')}
            className="rounded"
          />
          <label htmlFor="ativo" className="text-sm text-gray-700">Localidade ativa</label>
        </div>
      )}

      <div className="flex justify-end pt-2">
        <button
          type="submit"
          disabled={salvar.isPending}
          className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors"
        >
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
