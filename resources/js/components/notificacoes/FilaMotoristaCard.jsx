import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Clock3 } from 'lucide-react'
import * as solicitacoesApi from '../../api/solicitacoes'
import KmSaidaModal from './KmSaidaModal'

// Ponto de entrada persistente para a fila de viagens designadas que ainda
// aguardam o motorista informar o KM de saída (finding Important 4): caso o
// pop-up automático tenha sido perdido/dispensado, o motorista sempre pode
// voltar aqui e aceitar quando quiser.
export default function FilaMotoristaCard({ ehMotorista }) {
  const qc = useQueryClient()
  const [alvo, setAlvo] = useState(null)
  const [erro, setErro] = useState('')

  const { data } = useQuery({
    queryKey: ['solicitacoes', 'fila-motorista-lista'],
    queryFn: () => solicitacoesApi.listar({ status: 'aguardando_finalizacao_trajeto' }).then(r => r.data.data ?? r.data),
    enabled: ehMotorista,
    refetchInterval: 30_000,
  })

  const aceitarMutation = useMutation({
    mutationFn: (km) => solicitacoesApi.motoristaAceitar(alvo.id, km),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['viagens'] })
      qc.invalidateQueries({ queryKey: ['solicitacoes'] })
      setAlvo(null)
      setErro('')
    },
    onError: (e) => setErro(e.response?.data?.message ?? Object.values(e.response?.data?.errors ?? {}).flat()[0] ?? 'Erro ao iniciar viagem'),
  })

  const fila = data ?? []

  if (!ehMotorista || fila.length === 0) return null

  return (
    <div className="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm">
      <div className="flex items-center gap-2 text-amber-800 font-medium mb-2">
        <Clock3 size={16} />
        Viagens aguardando você informar o KM de saída ({fila.length})
      </div>
      <ul className="space-y-1.5">
        {fila.map((s) => (
          <li key={s.id} className="flex items-center justify-between bg-white rounded-lg px-3 py-2 border border-amber-100">
            <span className="text-gray-700">{s.origem ?? s.cidade ?? '—'} → <strong>{s.destino ?? s.hospital_destino ?? '—'}</strong></span>
            <button
              onClick={() => { setAlvo(s); setErro('') }}
              className="text-xs bg-amber-600 text-white px-3 py-1 rounded-lg hover:bg-amber-700"
            >
              Informar KM e aceitar
            </button>
          </li>
        ))}
      </ul>

      {alvo && (
        <KmSaidaModal
          loading={aceitarMutation.isPending}
          erro={erro}
          onCancelar={() => { setAlvo(null); setErro('') }}
          onConfirmar={(km) => aceitarMutation.mutate(km)}
        />
      )}
    </div>
  )
}
