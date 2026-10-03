import { useQuery } from '@tanstack/react-query'
import { format } from 'date-fns'
import * as auditoriaApi from '../../api/auditoria'

const ROTULOS = {
  km_saida: 'KM saída',
  km_chegada: 'KM chegada',
  litros: 'Litros',
  valor_litro: 'R$/L',
  km_momento: 'KM',
}

const fmtValor = (v) => (v == null ? '—' : Number.isFinite(Number(v)) ? Number(v).toLocaleString('pt-BR') : String(v))

export default function HistoricoCorrecoes({ entidade, entidadeId }) {
  const { data: registros } = useQuery({
    queryKey: ['auditoria-correcoes', entidade, entidadeId],
    queryFn: () => auditoriaApi.listarCorrecoes({ entidade, entidade_id: entidadeId }).then(r => r.data.data),
  })

  if (!registros?.length) return null

  return (
    <div className="text-xs">
      <p className="font-semibold text-gray-500 mb-1">Histórico de correções</p>
      <ul className="space-y-1">
        {registros.map((r) => (
          <li key={r.id} className="text-gray-600">
            <span className="text-gray-400">{format(new Date(r.created_at), 'dd/MM/yyyy HH:mm')}</span>
            {' · '}{r.usuario?.nome ?? 'usuário removido'}
            {' · '}
            {r.acao === 'exclusao'
              ? 'excluiu o registro'
              : Object.keys(r.depois ?? {}).map((campo) => (
                  `${ROTULOS[campo] ?? campo}: ${fmtValor(r.antes?.[campo])} → ${fmtValor(r.depois[campo])}`
                )).join('; ')}
          </li>
        ))}
      </ul>
    </div>
  )
}
