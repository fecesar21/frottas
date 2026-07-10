import { useEffect, useRef, useState } from 'react'
import { registrarPonto } from '../api/viagens'

const DISTANCIA_MINIMA_M = 40

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

export function useRastreamento(viagemId) {
  const ultimaPosRef = useRef(null)
  const [erro, setErro] = useState(null)
  const [falhasEnvio, setFalhasEnvio] = useState(0)

  useEffect(() => {
    setErro(null)
    setFalhasEnvio(0)

    if (!viagemId) return

    if (!navigator.geolocation) {
      setErro('Geolocalização não suportada neste navegador/contexto (verifique se o acesso é via HTTPS).')
      return
    }

    let watchId = null
    let wakeLock = null

    navigator.wakeLock?.request('screen').then(wl => { wakeLock = wl }).catch(() => {})

    watchId = navigator.geolocation.watchPosition(
      ({ coords }) => {
        setErro(null)
        const { latitude, longitude, accuracy } = coords
        const ultima = ultimaPosRef.current

        if (ultima && haversine(ultima.latitude, ultima.longitude, latitude, longitude) < DISTANCIA_MINIMA_M) {
          return
        }

        ultimaPosRef.current = { latitude, longitude }

        registrarPonto(viagemId, {
          latitude,
          longitude,
          accuracy,
          capturado_at: new Date().toISOString(),
        })
          .then(() => setFalhasEnvio(0))
          .catch((e) => {
            setFalhasEnvio(n => n + 1)
            setErro(e.response?.data?.message ?? 'Falha ao sincronizar ponto de GPS com o servidor.')
            console.error('Falha ao registrar ponto de GPS', e)
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
    }
  }, [viagemId])

  return { erro, falhasEnvio }
}
