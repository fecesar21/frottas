import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { format, startOfMonth } from 'date-fns'
import * as relatoriosApi from '../../api/relatorios'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Badge from '../../components/ui/Badge'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'
const fmtKm = (n) => n != null ? Number(n).toLocaleString('pt-BR') : '—'

export default function RelatorioViagens() {
  const [de, setDe] = useState(format(startOfMonth(new Date()), 'yyyy-MM-dd'))
  const [ate, setAte] = useState(format(new Date(), 'yyyy-MM-dd'))

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['relatorio-viagens', de, ate],
    queryFn: () => relatoriosApi.viagens({ de, ate }).then(r => r.data),
  })

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-3 bg-white border border-gray-200 rounded-xl p-4">
        <label className="text-sm text-gray-600">De:</label>
        <input type="date" value={de} onChange={e => setDe(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
        <label className="text-sm text-gray-600">Até:</label>
        <input type="date" value={ate} onChange={e => setAte(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
        <button onClick={() => refetch()} className="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">Filtrar</button>
      </div>

      {isLoading && <LoadingSpinner />}

      {data && (
        <>
          <div className="grid grid-cols-4 gap-4">
            {[
              { l: 'Total viagens', v: data.totais?.total_viagens ?? 0 },
              { l: 'Concluídas', v: data.totais?.viagens_concluidas ?? 0 },
              { l: 'KM total', v: `${fmtKm(data.totais?.km_total)} km` },
              { l: 'Duração média', v: `${Math.round(data.totais?.duracao_media_min ?? 0)} min` },
            ].map(({ l, v }) => (
              <div key={l} className="bg-white border border-gray-200 rounded-xl p-4">
                <p className="text-xs text-gray-500">{l}</p>
                <p className="text-xl font-bold text-gray-800 mt-1">{v}</p>
              </div>
            ))}
          </div>

          <div className="bg-white rounded-xl border border-gray-200 overflow-hidden">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                  {['Saída', 'Chegada', 'Placa', 'Motorista', 'Origem → Destino', 'KM perc.', 'Duração', 'Status'].map(h => (
                    <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {(data.rows ?? []).map((r) => (
                  <tr key={r.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-gray-500">{fmtDt(r.saida_at)}</td>
                    <td className="px-4 py-3 text-gray-500">{fmtDt(r.chegada_at)}</td>
                    <td className="px-4 py-3 font-mono font-semibold text-gray-800">{r.placa}</td>
                    <td className="px-4 py-3 text-gray-600">{r.motorista_nome}</td>
                    <td className="px-4 py-3 text-gray-600">
                      <span className="text-gray-400">{r.origem}</span> → {r.destino}
                    </td>
                    <td className="px-4 py-3">{fmtKm(r.km_percorrido)}</td>
                    <td className="px-4 py-3 text-gray-500">{r.duracao_min ? `${r.duracao_min} min` : '—'}</td>
                    <td className="px-4 py-3"><Badge value={r.status} /></td>
                  </tr>
                ))}
                {(data.rows ?? []).length === 0 && (
                  <tr><td colSpan={8} className="px-4 py-8 text-center text-gray-400">Sem dados no período</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
