import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import * as colaboradoresApi from '../../api/colaboradores'
import * as unidadesApi from '../../api/unidades'

/**
 * Seleção múltipla de colaboradores (cópia local do AD) com busca por nome
 * e filtro por unidade. `value` é a lista de colaboradores selecionados
 * ({ id, nome, unidade }); `onChange` recebe a nova lista.
 */
export default function ColaboradorSelect({ value, onChange }) {
  const [busca, setBusca] = useState('')
  const [buscaDebounced, setBuscaDebounced] = useState('')
  const [unidadeId, setUnidadeId] = useState('')

  useEffect(() => {
    const t = setTimeout(() => setBuscaDebounced(busca.trim()), 300)
    return () => clearTimeout(t)
  }, [busca])

  const { data: unidades } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data.data ?? r.data),
    staleTime: 300_000,
  })

  const { data: resultados, isFetching } = useQuery({
    queryKey: ['colaboradores', buscaDebounced, unidadeId],
    queryFn: () => colaboradoresApi.listar({ busca: buscaDebounced || undefined, unidade_id: unidadeId || undefined })
      .then(r => r.data.data),
    enabled: buscaDebounced.length >= 2 || !!unidadeId,
    staleTime: 60_000,
  })

  const selecionadosIds = new Set(value.map(c => c.id))
  const adicionar = (c) => { if (!selecionadosIds.has(c.id)) onChange([...value, c]) }
  const remover = (id) => onChange(value.filter(c => c.id !== id))

  const inputCls = 'w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500'

  return (
    <div className="space-y-2">
      {value.length > 0 && (
        <ul className="flex flex-wrap gap-2" aria-label="Colaboradores selecionados">
          {value.map(c => (
            <li key={c.id} className="flex items-center gap-1 bg-blue-50 text-blue-800 border border-blue-200 rounded-full pl-3 pr-1 py-1 text-xs">
              {c.nome}
              <button type="button" onClick={() => remover(c.id)} aria-label={`Remover ${c.nome}`}
                className="w-6 h-6 rounded-full hover:bg-blue-100">×</button>
            </li>
          ))}
        </ul>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
        <input type="search" value={busca} onChange={e => setBusca(e.target.value)}
          placeholder="Buscar colaborador pelo nome..." className={`${inputCls} sm:col-span-2`} />
        <select value={unidadeId} onChange={e => setUnidadeId(e.target.value)} className={inputCls} aria-label="Filtrar por unidade">
          <option value="">Todas as unidades</option>
          {(unidades ?? []).map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}
        </select>
      </div>

      {(buscaDebounced.length >= 2 || unidadeId) && (
        <ul className="border border-gray-200 rounded-lg max-h-56 overflow-y-auto divide-y divide-gray-100">
          {isFetching && !resultados && <li className="px-3 py-2 text-sm text-gray-500">Buscando...</li>}
          {resultados?.length === 0 && <li className="px-3 py-2 text-sm text-gray-500">Nenhum colaborador encontrado.</li>}
          {resultados?.map(c => (
            <li key={c.id}>
              <button type="button" onClick={() => adicionar(c)} disabled={selecionadosIds.has(c.id)}
                className="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-50">
                <span className="font-medium text-gray-800">{c.nome}</span>
                <span className="block text-xs text-gray-500">
                  {[c.unidade, c.departamento, c.cargo].filter(Boolean).join(' · ')}
                </span>
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
