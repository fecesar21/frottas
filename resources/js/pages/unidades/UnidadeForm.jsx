import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import * as unidadesApi from '../../api/unidades'
import Alert from '../../components/ui/Alert'

export default function UnidadeForm({ unidade, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: unidade?.nome ?? '',
    tipo: unidade?.tipo ?? 'filial',
    ativo: unidade?.ativo ?? true,
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const set = (k) => (e) =>
    setForm(f => ({ ...f, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))

  const salvar = useMutation({
    mutationFn: (data) =>
      unidade ? unidadesApi.atualizar(unidade.id, data) : unidadesApi.criar(data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['unidades'] })
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
          maxLength={120}
          placeholder="Ex: Filial São Paulo"
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        />
        {fieldErrors.nome && <p className="text-red-500 text-xs mt-1">{fieldErrors.nome[0]}</p>}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
        <select
          value={form.tipo}
          onChange={set('tipo')}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="matriz">Matriz</option>
          <option value="filial">Filial</option>
        </select>
      </div>

      {unidade && (
        <div className="flex items-center gap-2">
          <input
            type="checkbox"
            id="ativo"
            checked={form.ativo}
            onChange={set('ativo')}
            className="rounded"
          />
          <label htmlFor="ativo" className="text-sm text-gray-700">Unidade ativa</label>
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
