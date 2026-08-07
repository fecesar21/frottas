import { useEffect, useState } from 'react'
import * as solicitacoesApi from '../api/solicitacoes'
import Layout from '../components/Layout'

const MOTIVOS = {
  transferencia_paciente: 'Transferência de Paciente',
  buscar_medico: 'Buscar Médicos em outra cidade',
  material_outro_hospital: 'Levar Material em outro Hospital',
  transporte_colaborador: 'Transporte de Colaborador(es)',
  buscar_material_fornecedor: 'Buscar materiais em fornecedor',
  tfd: 'TFD',
}

const STATUS = {
  aberto: { label: 'Em aberto', className: 'bg-amber-50 text-amber-700 border-amber-200' },
  em_trajeto: { label: 'Motorista em Trajeto', className: 'bg-blue-50 text-blue-700 border-blue-200' },
  aguardando_finalizacao_trajeto: { label: 'Aguardando Finalização do Trajeto Anterior', className: 'bg-orange-50 text-orange-700 border-orange-200' },
  finalizado: { label: 'Finalizado', className: 'bg-green-50 text-green-700 border-green-200' },
  cancelado: { label: 'Cancelado', className: 'bg-gray-100 text-gray-600 border-gray-200' },
}

export default function MinhasSolicitacoes() {
  const [solicitacoes, setSolicitacoes] = useState([])
  const [loading, setLoading] = useState(true)

  const carregar = () => solicitacoesApi.listar().then(({ data }) => setSolicitacoes(data.data ?? data))

  useEffect(() => {
    carregar().finally(() => setLoading(false))
  }, [])

  return (
    <Layout>
      <h1 className="text-xl font-bold text-gray-900 mb-1">Minhas Solicitações</h1>
      <p className="text-sm text-gray-500 mb-6">Acompanhe o status das suas solicitações de transporte.</p>

      <div className="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
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
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {solicitacoes.map(s => (
                <tr key={s.id}>
                  <td className="px-4 py-3 text-gray-700">{new Date(s.criado_em).toLocaleDateString('pt-BR')}</td>
                  <td className="px-4 py-3 text-gray-700">{MOTIVOS[s.motivo] ?? s.motivo}</td>
                  <td className="px-4 py-3 text-gray-700">{s.saida_at ? new Date(s.saida_at).toLocaleString('pt-BR') : '—'}</td>
                  <td className="px-4 py-3 text-gray-700">{s.chegada_at ? new Date(s.chegada_at).toLocaleString('pt-BR') : '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-block px-2.5 py-1 rounded-full text-xs font-medium border ${STATUS[s.status]?.className ?? ''}`}>
                      {STATUS[s.status]?.label ?? s.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-700">{s.motorista_nome ?? '—'}</td>
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
