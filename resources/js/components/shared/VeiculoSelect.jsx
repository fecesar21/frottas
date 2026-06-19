import { useQuery } from '@tanstack/react-query'
import * as veiculosApi from '../../api/veiculos'

export default function VeiculoSelect({ value, onChange, name = 'veiculo_id', required = false, filterStatus }) {
  const { data } = useQuery({
    queryKey: ['veiculos', filterStatus],
    queryFn: () => veiculosApi.listar(filterStatus ? { status: filterStatus } : undefined).then(r => r.data.data ?? r.data),
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
      <option value="">Selecione um veículo</option>
      {(data ?? []).map((v) => (
        <option key={v.id} value={v.id}>
          {v.placa} — {v.modelo} ({v.status})
        </option>
      ))}
    </select>
  )
}
