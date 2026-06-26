import { useEffect, useRef } from 'react'
import { registrarPonto } from '../api/viagens'

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

  useEffect(() => {
    if (!viagemId || !navigator.geolocation) return

    let watchId = null
    let wakeLock = null

    navigator.wakeLock?.request('screen').then(wl => { wakeLock = wl }).catch(() => {})

    watchId = navigator.geolocation.watchPosition(
      ({ coords }) => {
        const { latitude, longitude, accuracy } = coords
        const ultima = ultimaPosRef.current

        if (ultima && haversine(ultima.latitude, ultima.longitude, latitude, longitude) < 100) {
          return
        }

        ultimaPosRef.current = { latitude, longitude }

        registrarPonto(viagemId, {
          latitude,
          longitude,
          accuracy,
          capturado_at: new Date().toISOString(),
        }).catch(() => {})
      },
      () => {},
      { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    )

    return () => {
      if (watchId !== null) navigator.geolocation.clearWatch(watchId)
      wakeLock?.release().catch(() => {})
      ultimaPosRef.current = null
    }
  }, [viagemId])
}
