import 'leaflet/dist/leaflet.css'
import { useEffect } from 'react'
import { useQuery } from '@tanstack/react-query'
import { MapContainer, TileLayer, Polyline, CircleMarker, useMap } from 'react-leaflet'
import { format } from 'date-fns'
import { ptBR } from 'date-fns/locale'
import * as viagensApi from '../../api/viagens'
import Badge from '../../components/ui/Badge'
import LoadingSpinner from '../../components/ui/LoadingSpinner'

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

export default function ViagemDetalhe({ viagem }) {
  const { data: pontos, isLoading } = useQuery({
    queryKey: ['viagem-pontos', viagem.id],
    queryFn: () => viagensApi.buscarPontos(viagem.id).then(r => r.data),
    refetchInterval: viagem.status === 'em_andamento' ? 30_000 : false,
  })

  const lista = pontos ?? []
  const inicio = lista[0]
  const fim    = lista[lista.length - 1]
  const posicoes = lista.map(p => [p.latitude, p.longitude])

  return (
    <div className="space-y-4">
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
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Saída</p>
          <p className="text-gray-700">{fmtDt(viagem.saida_at)}</p>
        </div>
        <div>
          <p className="text-xs text-gray-400 mb-0.5">Chegada</p>
          <p className="text-gray-700">{fmtDt(viagem.chegada_at)}</p>
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
