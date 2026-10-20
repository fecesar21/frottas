import { useEffect, useState } from 'react'
import * as solicitacoesApi from '../api/solicitacoes'
import Layout from '../components/Layout'
import { rotuloMotivo } from '../../js/utils/solicitacao'


const STATUS = {
  pendente_motorista: { label: 'Aguardando motorista', className: 'bg-amber-50 text-amber-700 border-amber-200' },
  recusada: { label: 'Em análise pelo gestor', className: 'bg-amber-50 text-amber-700 border-amber-200' },
  aberto: { label: 'Em aberto', className: 'bg-amber-50 text-amber-700 border-amber-200' },
  em_trajeto: { label: 'Motorista em Trajeto', className: 'bg-blue-50 text-blue-700 border-blue-200' },
  aguardando_finalizacao_trajeto: { label: 'Aguardando Finalização do Trajeto Anterior', className: 'bg-orange-50 text-orange-700 border-orange-200' },
  finalizado: { label: 'Finalizado', className: 'bg-green-50 text-green-700 border-green-200' },
  recusada_gestao: { label: 'Recusada', className: 'bg-red-50 text-red-700 border-red-200' },
  cancelado: { label: 'Cancelado', className: 'bg-gray-100 text-gray-600 border-gray-200' },
}

// Espelha SolicitacaoService::STATUS_CANCELAVEIS: viagem iniciada ou realizada não cancela.
const CANCELAVEIS = ['aberto', 'recusada', 'pendente_motorista', 'aguardando_finalizacao_trajeto']

export default function MinhasSolicitacoes() {
  const [solicitacoes, setSolicitacoes] = useState([])
  const [loading, setLoading] = useState(true)
  const [confirmarId, setConfirmarId] = useState(null)
  const [cancelando, setCancelando] = useState(false)
  const [erro, setErro] = useState('')

  const carregar = () => solicitacoesApi.listar().then(({ data }) => setSolicitacoes(data.data ?? data))

  useEffect(() => {
    carregar().finally(() => setLoading(false))
    // Atualiza periodicamente para o solicitante acompanhar despacho/recusa.
    const timer = setInterval(() => { carregar().catch(() => {}) }, 30_000)
    return () => clearInterval(timer)
  }, [])

  const cancelar = async (id) => {
    setCancelando(true)
    setErro('')
    try {
      await solicitacoesApi.cancelar(id)
      setConfirmarId(null)
      await carregar()
    } catch (e) {
      setErro(e.response?.data?.message ?? 'Não foi possível cancelar a solicitação.')
    } finally {
      setCancelando(false)
    }
  }

  return (
    <Layout>
      <h1 className="text-xl font-bold text-gray-900 mb-1">Minhas Solicitações</h1>
      <p className="text-sm text-gray-500 mb-6">Acompanhe o status das suas solicitações de transporte.</p>

      {erro && <p className="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2">{erro}</p>}

      <div className="bg-white rounded-2xl shadow-xs border border-gray-100 overflow-hidden">
        {loading ? (
          <p className="p-6 text-sm text-gray-500">Carregando...</p>
        ) : solicitacoes.length === 0 ? (
          <p className="p-6 text-sm text-gray-500">Nenhuma solicitação encontrada.</p>
        ) : (
          <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
              <tr>
                <th className="text-left px-4 py-3">Data</th>
                <th className="text-left px-4 py-3">Motivo</th>
                <th className="text-left px-4 py-3">Saída</th>
                <th className="text-left px-4 py-3">Chegada</th>
                <th className="text-left px-4 py-3">Status</th>
                <th className="text-left px-4 py-3">Motorista</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {solicitacoes.map(s => (
                <tr key={s.id}>
                  <td className="px-4 py-3 text-gray-700">{new Date(s.criado_em).toLocaleDateString('pt-BR')}</td>
                  <td className="px-4 py-3 text-gray-700">{rotuloMotivo(s)}</td>
                  <td className="px-4 py-3 text-gray-700">{s.saida_at ? new Date(s.saida_at).toLocaleString('pt-BR') : '—'}</td>
                  <td className="px-4 py-3 text-gray-700">{s.chegada_at ? new Date(s.chegada_at).toLocaleString('pt-BR') : '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium border ${STATUS[s.status]?.className ?? ''}`}>
                      {STATUS[s.status]?.label ?? s.status}
                    </span>
                    {s.status === 'recusada_gestao' && s.motivo_recusa && (
                      <p className="mt-1 text-xs text-red-700 max-w-xs break-words">Motivo: {s.motivo_recusa}</p>
                    )}
                  </td>
                  <td className="px-4 py-3 text-gray-700">{s.motorista_nome ?? '—'}</td>
                  <td className="px-4 py-3 text-right whitespace-nowrap">
                    {CANCELAVEIS.includes(s.status) && !s.viagem_id && (confirmarId === s.id ? (
                      <span className="inline-flex gap-2">
                        <button onClick={() => cancelar(s.id)} disabled={cancelando}
                          className="text-xs text-white bg-red-600 hover:bg-red-700 rounded-lg px-3 py-1.5 disabled:opacity-60">
                          {cancelando ? 'Cancelando...' : 'Confirmar'}
                        </button>
                        <button onClick={() => setConfirmarId(null)} className="text-xs text-gray-600 hover:bg-gray-100 rounded-lg px-3 py-1.5">
                          Voltar
                        </button>
                      </span>
                    ) : (
                      <button onClick={() => { setConfirmarId(s.id); setErro('') }}
                        className="text-xs text-red-600 border border-red-200 hover:bg-red-50 rounded-lg px-3 py-1.5">
                        Cancelar
                      </button>
                    ))}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          </div>
        )}
      </div>
    </Layout>
  )
}
