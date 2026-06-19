import { useQuery } from '@tanstack/react-query'
import * as relatoriosApi from '../../api/relatorios'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Badge from '../../components/ui/Badge'

const fmtKm = (n) => Number(n ?? 0).toLocaleString('pt-BR')

export default function RelatorioMotoristas() {
  const { data, isLoading } = useQuery({
    queryKey: ['relatorio-motoristas'],
    queryFn: () => relatoriosApi.motoristas().then(r => r.data),
  })

  if (isLoading) return <LoadingSpinner />

  return (
    <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <table className="w-full text-sm">
        <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
          <tr>
            {['Nome', 'CNH', 'Cat.', 'Validade CNH', 'Turno', 'Viagens', 'KM total', 'Abastec.', 'Plantões', 'Status', 'CNH'].map(h => (
              <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {(data ?? []).map((m) => (
            <tr key={m.id} className="hover:bg-gray-50">
              <td className="px-4 py-3 font-medium text-gray-800">{m.nome}</td>
              <td className="px-4 py-3 font-mono text-gray-500">{m.cnh_numero}</td>
              <td className="px-4 py-3 font-bold text-gray-700">{m.cnh_categoria}</td>
              <td className="px-4 py-3 text-gray-500">{m.cnh_validade}</td>
              <td className="px-4 py-3 text-gray-500 capitalize">{m.turno_padrao ?? '—'}</td>
              <td className="px-4 py-3 text-center">{m.total_viagens}</td>
              <td className="px-4 py-3">{fmtKm(m.km_total)} km</td>
              <td className="px-4 py-3 text-center">{m.total_abastecimentos}</td>
              <td className="px-4 py-3 text-center">{m.total_plantoes}</td>
              <td className="px-4 py-3"><Badge value={m.status} /></td>
              <td className="px-4 py-3"><Badge value={m.cnh_status} /></td>
            </tr>
          ))}
          {(data ?? []).length === 0 && (
            <tr><td colSpan={11} className="px-4 py-8 text-center text-gray-400">Sem dados</td></tr>
          )}
        </tbody>
      </table>
    </div>
  )
}
