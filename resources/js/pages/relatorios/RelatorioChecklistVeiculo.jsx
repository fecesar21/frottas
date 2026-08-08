import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { format } from 'date-fns'
import { AlertTriangle, Image as ImageIcon } from 'lucide-react'
import * as relatoriosApi from '../../api/relatorios'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import Badge from '../../components/ui/Badge'
import Modal from '../../components/ui/Modal'
import VeiculoSelect from '../../components/shared/VeiculoSelect'

const fmtDt = (s) => s ? format(new Date(s), 'dd/MM/yyyy') : '—'

export default function RelatorioChecklistVeiculo() {
  const [veiculoId, setVeiculoId] = useState('')
  const [data, setData] = useState('')
  const [status, setStatus] = useState('')
  const [detalheAberto, setDetalheAberto] = useState(null)

  const { data: rows, isLoading, refetch } = useQuery({
    queryKey: ['relatorio-checklist-veiculo', veiculoId, data, status],
    queryFn: () => relatoriosApi.checklistVeiculo({
      veiculo_id: veiculoId || undefined,
      data: data || undefined,
      status: status || undefined,
    }).then(r => r.data),
  })

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3 bg-white border border-gray-200 rounded-xl p-4">
        <div className="w-56">
          <VeiculoSelect value={veiculoId} onChange={setVeiculoId} />
        </div>
        <label className="text-sm text-gray-600">Data:</label>
        <input type="date" value={data} onChange={e => setData(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm" />
        <label className="text-sm text-gray-600">Status:</label>
        <select value={status} onChange={e => setStatus(e.target.value)} className="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
          <option value="">Todos</option>
          <option value="pendente">Pendente</option>
          <option value="enviado">Enviado</option>
        </select>
        <button onClick={() => refetch()} className="bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700">Filtrar</button>
      </div>

      {isLoading && <LoadingSpinner />}

      {rows && (
        <div className="bg-white rounded-xl border border-gray-200 overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase">
              <tr>
                {['Data', 'Motorista', 'Veículo', 'Status', 'Conforme', 'Não conforme', ''].map(h => (
                  <th key={h} className="px-4 py-3 text-left font-medium whitespace-nowrap">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {rows.map((r) => (
                <tr key={r.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{fmtDt(r.data_referencia)}</td>
                  <td className="px-4 py-3 text-gray-700 whitespace-nowrap">{r.motorista_nome ?? '—'}</td>
                  <td className="px-4 py-3 font-mono text-gray-800 whitespace-nowrap">{r.veiculo_placa ?? '—'}</td>
                  <td className="px-4 py-3"><Badge value={r.status} /></td>
                  <td className="px-4 py-3 text-green-700 font-medium">{r.itens_conforme}</td>
                  <td className="px-4 py-3 text-red-600 font-medium">{r.itens_nao_conforme}</td>
                  <td className="px-4 py-3">
                    {r.itens_nao_conforme > 0 && (
                      <button
                        onClick={() => setDetalheAberto(r)}
                        className="flex items-center gap-1 text-xs text-red-600 hover:text-red-800 border border-red-300 rounded px-2 py-1 hover:bg-red-50 transition-colors"
                      >
                        <AlertTriangle size={12} /> Ver não conformidades
                      </button>
                    )}
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr><td colSpan={7} className="px-4 py-8 text-center text-gray-400">Nenhum checklist encontrado</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}

      <Modal open={!!detalheAberto} onClose={() => setDetalheAberto(null)} title="Itens não conformes" size="lg">
        {detalheAberto && (
          <div className="space-y-4">
            <p className="text-sm text-gray-600">
              Veículo <strong>{detalheAberto.veiculo_placa}</strong> — Motorista <strong>{detalheAberto.motorista_nome}</strong> — {fmtDt(detalheAberto.data_referencia)}
            </p>
            <div className="space-y-3">
              {(detalheAberto.itens_nao_conforme_detalhe ?? []).map((item, i) => (
                <div key={i} className="border border-red-200 bg-red-50 rounded-lg p-3 space-y-2">
                  <p className="text-sm font-medium text-red-800">{item.label}</p>
                  {item.observacao && <p className="text-sm text-gray-700">{item.observacao}</p>}
                  {item.foto_path ? (
                    <a href={`/storage/${item.foto_path}`} target="_blank" rel="noreferrer" className="inline-block">
                      <img src={`/storage/${item.foto_path}`} alt={item.label} className="max-h-48 rounded-lg border border-gray-200" />
                    </a>
                  ) : (
                    <p className="flex items-center gap-1 text-xs text-gray-400"><ImageIcon size={12} /> Sem foto anexada</p>
                  )}
                </div>
              ))}
              {(detalheAberto.itens_nao_conforme_detalhe ?? []).length === 0 && (
                <p className="text-sm text-gray-400">Nenhum item não conforme.</p>
              )}
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
