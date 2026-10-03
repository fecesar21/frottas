import 'leaflet/dist/leaflet.css'
import { useEffect, useState } from 'react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { MapContainer, TileLayer, Polyline, CircleMarker, useMap } from 'react-leaflet'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import * as viagensApi from '../../api/viagens'
import * as solicitacoesApi from '../../api/solicitacoes'
import Badge from '../../components/ui/Badge'
import LoadingSpinner from '../../components/ui/LoadingSpinner'
import HistoricoCorrecoes from '../../components/shared/HistoricoCorrecoes'
import { useAuth } from '../../contexts/AuthContext'

const fmtDt  = (s) => s ? format(new Date(s), 'dd/MM/yyyy HH:mm', { locale: ptBR }) : '—'
const fmtKm  = (n) => n != null ? Number(n).toLocaleString('pt-BR') + ' km' : '—'

function AjustarMapa({ pontos }) {
  const map = useMap()
  useEffect(() => {
    if (pontos.length >= 2) {
      map.fitBounds(pontos.map(p => [p.latitude, p.longitude]), { padding: [40, 40] })
    } else if (pontos.length === 1) {
      map.setView([pontos[0].latitude, pontos[0].longitude], 15)
    }
  }, [pontos, map])
  return null
}

function CorrecaoKm({ viagem, onCorrigido }) {
  const qc = useQueryClient()
  const concluida = viagem.status === 'concluida'
  const [aberto, setAberto] = useState(false)
  const [kmSaida, setKmSaida] = useState(viagem.km_saida ?? '')
  const [kmChegada, setKmChegada] = useState(viagem.km_chegada ?? '')

  const salvar = useMutation({
    mutationFn: () => viagensApi.corrigir(viagem.id, {
      km_saida: Number(kmSaida),
      ...(concluida ? { km_chegada: Number(kmChegada) } : {}),
    }).then(r => r.data.data ?? r.data),
    onSuccess: (atualizada) => {
      qc.invalidateQueries({ queryKey: ['viagens'] })
      qc.invalidateQueries({ queryKey: ['auditoria-correcoes'] })
      onCorrigido(atualizada)
      setAberto(false)
    },
  })

  const erro = salvar.error?.response?.data
  const msgErro = erro?.errors ? Object.values(erro.errors).flat()[0] : (erro?.message ?? erro?.error)

  if (!aberto) {
    return (
      <div className="space-y-2">
      <button onClick={() => setAberto(true)} className="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded px-2 py-1 hover:bg-blue-50 transition-colors">
        Corrigir KM (admin)
      </button>
      <HistoricoCorrecoes entidade="viagem" entidadeId={viagem.id} />
      </div>
    )
  }

  return (
    <form onSubmit={(e) => { e.preventDefault(); salvar.mutate() }} className="border border-amber-200 bg-amber-50 rounded-lg p-3 space-y-2 text-sm">
      <p className="text-xs font-semibold text-amber-800">Correção de KM — somente administrador</p>
      <div className="grid grid-cols-2 gap-3">
        <label className="block">
          <span className="text-xs text-gray-500">KM de saída</span>
          <input type="number" min="0" required value={kmSaida} onChange={(e) => setKmSaida(e.target.value)}
            className="mt-1 w-full border border-gray-300 rounded-lg px-3 py-1.5" />
        </label>
        {concluida && (
          <label className="block">
            <span className="text-xs text-gray-500">KM de chegada</span>
            <input type="number" min="0" required value={kmChegada} onChange={(e) => setKmChegada(e.target.value)}
              className="mt-1 w-full border border-gray-300 rounded-lg px-3 py-1.5" />
          </label>
        )}
      </div>
      {msgErro && <p className="text-xs text-red-600">{msgErro}</p>}
      <div className="flex justify-end gap-2">
        <button type="button" onClick={() => setAberto(false)} className="px-3 py-1.5 text-xs text-gray-600 hover:text-gray-800">Cancelar</button>
        <button type="submit" disabled={salvar.isPending} className="px-3 py-1.5 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          {salvar.isPending ? 'Salvando…' : 'Salvar correção'}
        </button>
      </div>
    </form>
  )
}

export default function ViagemDetalhe({ viagem: viagemInicial }) {
  const { user, isAdmin } = useAuth()
  const [viagem, setViagem] = useState(viagemInicial)
  const ehMotorista = user?.perfil === 'operador' && !!user?.motorista_id && user.motorista_id === viagem.motorista_id

  const { data: pontos, isLoading } = useQuery({
    queryKey: ['viagem-pontos', viagem.id],
    queryFn: () => viagensApi.buscarPontos(viagem.id).then(r => r.data),
    refetchInterval: viagem.status === 'em_andamento' ? 30_000 : false,
  })

  // Backend já escopa por motorista_pendente_id do usuário autenticado — só
  // dispara essa query quando o próprio motorista da viagem está vendo a tela,
  // para não vazar a fila de outros motoristas para gestores/admins.
  const { data: fila } = useQuery({
    queryKey: ['solicitacoes', 'fila-motorista'],
    queryFn: () => solicitacoesApi.listar({ status: 'aguardando_finalizacao_trajeto' }).then(r => r.data.data ?? r.data),
    enabled: ehMotorista && viagem?.status === 'em_andamento',
    refetchInterval: 30_000,
  })

  const lista = pontos ?? []
  const inicio = lista[0]
  const fim    = lista[lista.length - 1]
  const posicoes = lista.map(p => [p.latitude, p.longitude])

  return (
    <div className="space-y-4">
      {(fila ?? []).length > 0 && (
        <div className="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-2">
          Próxima viagem aguardando ({fila.length} na fila)
        </div>
      )}

      {/* Resumo da viagem */}
      <div className="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Motorista</p>
          <p className="font-medium text-gray-800">{viagem.motorista?.nome ?? '—'}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Veículo</p>
          <p className="font-medium text-gray-800 font-mono">{viagem.veiculo?.placa ?? '—'}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Origem</p>
          <p className="text-gray-700">{viagem.origem}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Destino</p>
          <p className="text-gray-700">{viagem.destino}</p>
        </div>
        {viagem.colaboradores?.length > 0 && (
          <div className="col-span-full">
            <p className="text-xs text-gray-400 mb-0.5">Colaboradores transportados</p>
            <p className="text-gray-700">{viagem.colaboradores.map(c => c.nome).join(', ')}</p>
          </div>
        )}
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Saída</p>
          <p className="text-gray-700">{fmtDt(viagem.saida_at)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Chegada</p>
          <p className="text-gray-700">{fmtDt(viagem.chegada_at)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">KM saída / chegada</p>
          <p className="text-gray-700">{fmtKm(viagem.km_saida)} / {fmtKm(viagem.km_chegada)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">KM percorrido</p>
          <p className="text-gray-700">{fmtKm(viagem.km_percorrido)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Status</p>
          <Badge value={viagem.status} />
        </div>
      </div>

      {isAdmin && viagem.status !== 'cancelada' && (
        <CorrecaoKm viagem={viagem} onCorrigido={(v) => setViagem(prev => ({ ...prev, ...v }))} />
      )}

      {/* Mapa */}
      <div>
        <p className="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">
          Trajeto rastreado
          {viagem.status === 'em_andamento' && lista.length > 0 && (
            <span className="ml-2 inline-flex items-center gap-1 text-green-600 normal-case font-normal">
              <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse" />
              ao vivo
            </span>
          )}
        </p>

        {isLoading && <LoadingSpinner />}

        {!isLoading && lista.length === 0 && (
          <div className="flex items-center justify-center h-48 bg-gray-50 rounded-xl border border-dashed border-gray-200 text-sm text-gray-400">
            Sem pontos de rastreamento registrados
          </div>
        )}

        {!isLoading && lista.length > 0 && (
          <div className="rounded-xl overflow-hidden border border-gray-200" style={{ height: 380 }}>
            <MapContainer
              center={[inicio.latitude, inicio.longitude]}
              zoom={13}
              style={{ height: '100%', width: '100%' }}
              zoomControl
            >
              <TileLayer
                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              />
              <AjustarMapa pontos={lista} />
              {posicoes.length >= 2 && (
                <Polyline positions={posicoes} color="#3b82f6" weight={4} opacity={0.8} />
              )}
              {/* Ponto inicial — verde */}
              <CircleMarker
                center={[inicio.latitude, inicio.longitude]}
                radius={8}
                fillColor="#22c55e"
                fillOpacity={0.9}
                color="#fff"
                weight={2}
              />
              {/* Ponto final — vermelho (apenas se diferente do inicial) */}
              {lista.length > 1 && (
                <CircleMarker
                  center={[fim.latitude, fim.longitude]}
                  radius={8}
                  fillColor="#ef4444"
                  fillOpacity={0.9}
                  color="#fff"
                  weight={2}
                />
              )}
            </MapContainer>
          </div>
        )}
      </div>

      <p className="text-xs text-gray-400 text-right">{lista.length} ponto(s) registrado(s)</p>
    </div>
  )
}
