import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import * as relatoriosApi from '../../api/relatorios'
import { downloadBlob } from '../../utils/downloadBlob'
import ManutencaoResumo from '../../components/manutencoes/ManutencaoResumo'
import { fmtDuracao } from '../../components/charts/chartTheme'

const hoje = new Date()
const iso = (d) => d.toISOString().slice(0, 10)
const fmtDt = (v) => v ? new Date(v).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—'

export default function RelatorioManutencoes() {
  const [de, setDe] = useState(iso(new Date(hoje.getFullYear(), hoje.getMonth(), 1)))
  const [ate, setAte] = useState(iso(hoje))
  const [exportando, setExportando] = useState(false)

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['manutencoes', de, ate],
    queryFn: () => relatoriosApi.manutencoes({ de, ate }).then(r => r.data),
  })

  const exportarPdf = async () => {
    setExportando(true)
    try {
      const { data: blob } = await relatoriosApi.manutencoesPdf({ de, ate })
      downloadBlob(blob, 'relatorio-manutencoes.pdf')
    } finally {
      setExportando(false)
    }
  }

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3 bg-white border border-gray-200 rounded-xl p-4">
        <label className="flex items-center gap-2 text-sm text-gray-600">
          De:
          <input type="date" value={de} onChange={e => setDe(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
        </label>
        <label className="flex items-center gap-2 text-sm text-gray-600">
          Até:
          <input type="date" value={ate} onChange={e => setAte(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
        </label>
        <button onClick={() => refetch()} className="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">Filtrar</button>
        <button onClick={exportarPdf} disabled={exportando} className="ml-auto bg-gray-100 text-gray-700 px-4 py-1.5 rounded-lg text-sm hover:bg-gray-200 disabled:opacity-50">
          {exportando ? 'Exportando...' : 'Exportar PDF'}
        </button>
      </div>

      {isError ? (
        <div className="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-4">Não foi possível carregar o relatório de manutenções.</div>
      ) : (
        <>
          <ManutencaoResumo data={data} loading={isLoading} />

          {(data?.por_tipo ?? []).length > 0 && (
            <div className="bg-white rounded-xl border border-gray-200 p-4">
              <h3 className="text-sm font-semibold text-gray-700 mb-3">Por tipo de manutenção</h3>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                {data.por_tipo.map(t => (
                  <div key={t.tipo ?? 'nao_informado'} className="border border-gray-100 rounded-lg p-3">
                    <p className="text-xs text-gray-500">{t.tipo_label}</p>
                    <p className="text-lg font-semibold text-gray-800">{t.manutencoes}</p>
                    <p className="text-xs text-gray-500">{fmtDuracao(t.tempo_total_min)} parado</p>
                  </div>
                ))}
              </div>
            </div>
          )}

          <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                  {['Placa', 'Modelo', 'Tipo', 'Motivo', 'Início', 'Fim', 'Aberta por', 'Fechada por', 'Duração total', 'No período'].map(h => (
                    <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {(data?.rows ?? []).map(m => (
                  <tr key={m.id} className="hover:bg-gray-50">
                    <td className="px-4 py-3 font-mono font-semibold text-gray-800">{m.placa}</td>
                    <td className="px-4 py-3 text-gray-600">{m.modelo}</td>
                    <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{m.tipo_label}</td>
                    <td className="px-4 py-3 text-gray-600">{m.motivo || '—'}{m.local ? <span className="block text-xs text-gray-400">{m.local}</span> : null}</td>
                    <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(m.inicio)}</td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      {m.em_andamento
                        ? <span className="text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-full px-2 py-0.5">Em andamento</span>
                        : <span className="text-gray-500">{fmtDt(m.fim)}</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">
                      {m.aberta_por || '—'}
                      {m.origem === 'motorista' && <span className="ml-1 text-xs text-blue-600">(motorista)</span>}
                    </td>
                    <td className="px-4 py-3 text-gray-600 whitespace-nowrap">{m.fechada_por || '—'}</td>
                    <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtDuracao(m.duracao_min)}</td>
                    <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{fmtDuracao(m.duracao_periodo_min)}</td>
                  </tr>
                ))}
                {!isLoading && (data?.rows ?? []).length === 0 && (
                  <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Sem manutenções no período</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}
