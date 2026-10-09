import { useEffect, useRef, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { format, startOfMonth } from 'date-fns'
import * as relatoriosApi from '../../api/relatorios'
import * as motoristasApi from '../../api/motoristas'
import * as motivosApi from '../../api/motivosViagem'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Badge from '../../components/ui/Badge'
import { downloadBlob } from '../../utils/downloadBlob'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM HH:mm') : '—'
const fmtKm = (n) => n != null ? Number(n).toLocaleString('pt-BR') : '—'
const fmtMotivo = (r) => r.motivo_nome ?? r.motivo_viagem ?? '—'

export default function RelatorioViagens() {
  const [de, setDe] = useState(format(startOfMonth(new Date()), 'yyyy-MM-dd'))
  const [ate, setAte] = useState(format(new Date(), 'yyyy-MM-dd'))
  const [motoristaIds, setMotoristaIds] = useState([])
  const [motivo, setMotivo] = useState('')
  const [exportando, setExportando] = useState(false)
  const filtros = { de, ate, motorista_ids: motoristaIds.length ? motoristaIds : undefined, motivo: motivo || undefined }

  const { data: motoristas } = useQuery({
    queryKey: ['motoristas', 'relatorio'],
    queryFn: () => motoristasApi.listar().then(r => r.data.data ?? r.data),
    staleTime: 60_000,
  })

  const { data: motivos } = useQuery({
    queryKey: ['motivos-viagem', 'relatorio'],
    queryFn: () => motivosApi.listar().then(r => r.data.data ?? r.data),
    staleTime: 60_000,
  })

  const { data, isLoading, refetch } = useQuery({
    queryKey: ['relatorio-viagens', de, ate, motoristaIds, motivo],
    queryFn: () => relatoriosApi.viagens(filtros).then(r => r.data),
    placeholderData: (anterior) => anterior,
  })

  const exportarPdf = async () => {
    setExportando(true)
    try {
      const { data: blob } = await relatoriosApi.viagensPdf(filtros)
      downloadBlob(blob, 'relatorio-viagens.pdf')
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
        <MotoristasMultiSelect motoristas={motoristas ?? []} value={motoristaIds} onChange={setMotoristaIds} />
        <select aria-label="Motivo" value={motivo} onChange={e => setMotivo(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
          <option value="">TODOS OS MOTIVOS</option>
          {(motivos ?? []).map(m => <option key={m.codigo} value={m.codigo}>{m.nome}</option>)}
        </select>
        <button onClick={() => refetch()} className="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">Filtrar</button>
        <button onClick={exportarPdf} disabled={exportando} className="ml-auto bg-gray-100 text-gray-700 px-4 py-1.5 rounded-lg text-sm hover:bg-gray-200 disabled:opacity-50">
          {exportando ? 'Exportando...' : 'Exportar PDF'}
        </button>
      </div>

      {isLoading && <LoadingSpinner />}

      {data && (
        <>
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
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

          <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                  {['Saída', 'Chegada', 'Placa', 'Motorista', 'Origem → Destino', 'Motivo', 'Nº Atendimento', 'Colaboradores', 'KM perc.', 'Duração', 'Status'].map(h => (
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
                    <td className="px-4 py-3 text-gray-600">{fmtMotivo(r)}</td>
                    <td className="px-4 py-3 text-gray-600">{r.numero_atendimento ?? '—'}</td>
                    <td className="px-4 py-3 text-gray-600 max-w-xs">{r.colaboradores ?? '—'}</td>
                    <td className="px-4 py-3">{fmtKm(r.km_percorrido)}</td>
                    <td className="px-4 py-3 text-gray-500">{r.duracao_min ? `${r.duracao_min} min` : '—'}</td>
                    <td className="px-4 py-3"><Badge value={r.status} /></td>
                  </tr>
                ))}
                {(data.rows ?? []).length === 0 && (
                  <tr><td colSpan={10} className="px-4 py-8 text-center text-gray-400">Sem dados no período</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  )
}

function MotoristasMultiSelect({ motoristas, value, onChange }) {
  const [aberto, setAberto] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    if (!aberto) return
    const fechar = (e) => { if (!ref.current?.contains(e.target)) setAberto(false) }
    document.addEventListener('mousedown', fechar)
    return () => document.removeEventListener('mousedown', fechar)
  }, [aberto])

  const alternar = (id) => onChange(value.includes(id) ? value.filter(v => v !== id) : [...value, id])
  const rotulo = value.length === 0
    ? 'TODOS OS MOTORISTAS'
    : value.length === 1
      ? motoristas.find(m => m.id === value[0])?.nome ?? '1 motorista'
      : `${value.length} motoristas selecionados`

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        aria-label="Motoristas"
        aria-expanded={aberto}
        onClick={() => setAberto(a => !a)}
        className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm bg-white min-w-56 text-left flex items-center justify-between gap-2"
      >
        <span className="truncate max-w-64">{rotulo}</span>
        <span className="text-gray-400 text-xs">▾</span>
      </button>
      {aberto && (
        <div className="absolute z-20 mt-1 w-72 max-h-72 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg py-1">
          <button
            type="button"
            onClick={() => onChange([])}
            disabled={value.length === 0}
            className="w-full text-left px-3 py-1.5 text-xs text-blue-600 hover:bg-gray-50 disabled:text-gray-300"
          >
            Limpar seleção (todos)
          </button>
          {motoristas.map(m => (
            <label key={m.id} className="flex items-center gap-2 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer">
              <input type="checkbox" checked={value.includes(m.id)} onChange={() => alternar(m.id)} />
              {m.nome}
            </label>
          ))}
        </div>
      )}
    </div>
  )
}
