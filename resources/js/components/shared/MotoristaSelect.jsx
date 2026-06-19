import { useQuery } from '@tanstack/react-query'
import * as motoristasApi from '../../api/motoristas'

export default function MotoristaSelect({ value, onChange, name = 'motorista_id', required = false }) {
  const { data } = useQuery({
    queryKey: ['motoristas', 'ativos'],
    queryFn: () => motoristasApi.listar({ status: 'ativo' }).then(r => r.data.data ?? r.data),
    staleTime: 30_000,
  })

  return (
    <select
      name={name}
      value={value ?? ''}
      onChange={(e) => onChange(e.target.value)}
      required={required}
      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
    >
      <option value="">Selecione um motorista</option>
      {(data ?? []).map((m) => (
        <option key={m.id} value={m.id}>
          {m.nome} — CNH {m.cnh_categoria}
        </option>
      ))}
    </select>
  )
}
