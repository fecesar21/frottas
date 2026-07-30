import { useEffect, useRef, useState, useCallback } from 'react'
import { registrarPonto } from '../api/viagens'
import { enfileirarPonto, listarPendentes, removerPonto } from '../lib/pontosQueue'

const DISTANCIA_MINIMA_M = 25
const ACCURACY_MAXIMA_M = 60

function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000
  const toRad = x => (x * Math.PI) / 180
  const dLat = toRad(lat2 - lat1)
  const dLon = toRad(lon2 - lon1)
  const a =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon / 2) ** 2
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))
}

async function registrarBackgroundSync() {
  try {
    const reg = await navigator.serviceWorker?.ready
    await reg?.sync?.register('sync-viagem-pontos')
  } catch {
    // Background Sync não suportado (ex. Safari/iOS) — a fila será
    // esvaziada mesmo assim quando o app estiver em foreground novamente.
  }
}

export function useRastreamento(viagemId) {
  const ultimaPosRef = useRef(null)
  const [erro, setErro] = useState(null)
  const [falhasEnvio, setFalhasEnvio] = useState(0)
  const [pendentes, setPendentes] = useState(0)
  const viagemIdRef = useRef(viagemId)
  viagemIdRef.current = viagemId

  const atualizarPendentes = useCallback(() => {
    listarPendentes().then(items => setPendentes(items.length)).catch(() => {})
  }, [])

  const esvaziarFila = useCallback(async () => {
    let itens
    try {
      itens = await listarPendentes()
    } catch {
      return
    }

    for (const item of itens) {
      if (item.viagemId !== viagemIdRef.current) continue
      try {
        await registrarPonto(item.viagemId, item.ponto)
        await removerPonto(item.localId)
        setFalhasEnvio(0)
      } catch (e) {
        // Mantém na fila; para de tentar os próximos nesta rodada se a viagem
        // não estiver mais em_andamento (422) — evita gastar tentativas à toa.
        if (e.response?.status === 422) break
      }
    }
    atualizarPendentes()
  }, [atualizarPendentes])

  useEffect(() => {
    setErro(null)
    setFalhasEnvio(0)

    if (!viagemId) {
      setPendentes(0)
      return
    }

    if (!navigator.geolocation) {
      setErro('Geolocalização não suportada neste navegador/contexto (verifique se o acesso é via HTTPS).')
      return
    }

    let watchId = null
    let wakeLock = null

    navigator.wakeLock?.request('screen').then(wl => { wakeLock = wl }).catch(() => {})

    esvaziarFila()
    const intervaloRetry = setInterval(esvaziarFila, 15000)

    const onOnline = () => esvaziarFila()
    window.addEventListener('online', onOnline)

    const onSwMessage = (event) => {
      if (event.data === 'FLUSH_PONTOS_PENDENTES') esvaziarFila()
    }
    navigator.serviceWorker?.addEventListener('message', onSwMessage)

    watchId = navigator.geolocation.watchPosition(
      ({ coords }) => {
        setErro(null)
        const { latitude, longitude, accuracy } = coords

        if (accuracy != null && accuracy > ACCURACY_MAXIMA_M) {
          return
        }

        const ultima = ultimaPosRef.current
        if (ultima && haversine(ultima.latitude, ultima.longitude, latitude, longitude) < DISTANCIA_MINIMA_M) {
          return
        }

        ultimaPosRef.current = { latitude, longitude }

        const ponto = {
          latitude,
          longitude,
          accuracy,
          capturado_at: new Date().toISOString(),
        }

        registrarPonto(viagemId, ponto)
          .then(() => setFalhasEnvio(0))
          .catch(async (e) => {
            setFalhasEnvio(n => n + 1)
            setErro(e.response?.data?.message ?? 'Falha ao sincronizar ponto de GPS com o servidor. Ponto salvo para reenvio.')
            console.error('Falha ao registrar ponto de GPS', e)
            try {
              await enfileirarPonto(viagemId, ponto)
              atualizarPendentes()
              registrarBackgroundSync()
            } catch (queueErr) {
              console.error('Falha ao salvar ponto na fila local', queueErr)
            }
          })
      },
      (geoErr) => {
        const mensagens = {
          1: 'Permissão de localização negada pelo motorista.',
          2: 'Localização indisponível no momento.',
          3: 'Tempo esgotado ao obter a localização.',
        }
        setErro(mensagens[geoErr.code] ?? 'Erro ao obter localização.')
        console.error('Erro de geolocalização', geoErr)
      },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    )

    return () => {
      if (watchId !== null) navigator.geolocation.clearWatch(watchId)
      wakeLock?.release().catch(() => {})
      ultimaPosRef.current = null
      clearInterval(intervaloRetry)
      window.removeEventListener('online', onOnline)
      navigator.serviceWorker?.removeEventListener('message', onSwMessage)
    }
  }, [viagemId, esvaziarFila, atualizarPendentes])

  return { erro, falhasEnvio, pendentes }
}
