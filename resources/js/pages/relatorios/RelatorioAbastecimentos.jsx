import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { format, startOfMonth } from 'date-fns'
import * as relatoriosApi from '../../api/relatorios'
import LoadingSpinner from '../../components/ui/LoadingSpinner'

const fmtBrl = (n) => Number(n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const fmtDt = (s) => s ? format(new Date(s), 'dd/MM/yyyy HH:mm') : '—'

export default function RelatorioAbastecimentos() {
  const [de, setDe] = useState(format(startOfMonth(new Date()), 'yyyy-MM-dd'))
  const [ate, setAte] = useState(format(new Date(), 'yyyy-MM-dd'))

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['relatorio-abastecimentos', de, ate],
    queryFn: () => relatoriosApi.abastecimentos({ de, ate }).then(r => r.data),
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
              { l: 'Total litros', v: `${Number(data.totais?.total_litros ?? 0).toFixed(1)} L` },
              { l: 'Total gasto', v: fmtBrl(data.totais?.total_valor) },
              { l: 'Preço médio/L', v: fmtBrl(data.totais?.preco_medio) },
              { l: 'Registros', v: data.totais?.total_registros ?? 0 },
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
                  {['Data', 'Placa', 'Motorista', 'Posto', 'Combustível', 'Litros', 'R$/L', 'Total', 'KM'].map(h => (
                    <th key={h} className="px-4 py-3 text-left font-medium">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {(data.rows ?? []).map((r) => (
                  <tr key={r.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 text-gray-500">{fmtDt(r.data)}</td>
                    <td className="px-4 py-3 font-mono font-semibold text-gray-800">{r.placa}</td>
                    <td className="px-4 py-3 text-gray-600">{r.motorista_nome}</td>
                    <td className="px-4 py-3 text-gray-500">{r.posto ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-500 capitalize">{r.combustivel?.replace(/_/g, ' ')}</td>
                    <td className="px-4 py-3">{Number(r.litros).toFixed(2)}</td>
                    <td className="px-4 py-3">{fmtBrl(r.valor_litro)}</td>
                    <td className="px-4 py-3 font-semibold">{fmtBrl(r.valor_total)}</td>
                    <td className="px-4 py-3">{Number(r.km_momento).toLocaleString('pt-BR')}</td>
                  </tr>
                ))}
                {(data.rows ?? []).length === 0 && (
                  <tr><td colSpan={9} className="px-4 py-8 text-center text-gray-400">Sem dados no período</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
