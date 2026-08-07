import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../contexts/AuthContext'
import * as notificacoesApi from '../api/notificacoes'

function tocarBeep() {
  try {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext
    const ctx = new AudioContextClass()
    const oscillator = ctx.createOscillator()
    const gain = ctx.createGain()
    oscillator.type = 'sine'
    oscillator.frequency.value = 880
    gain.gain.setValueAtTime(0.15, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.3)
    oscillator.connect(gain)
    gain.connect(ctx.destination)
    oscillator.start()
    oscillator.stop(ctx.currentTime + 0.3)
    oscillator.onended = () => ctx.close()
  } catch {
    // Ambiente sem suporte a Web Audio API (ex: alguns navegadores headless) — falha silenciosamente.
  }
}

export function useNotificacoes() {
  const { user } = useAuth()
  const qc = useQueryClient()
  const totalAnteriorRef = useRef(0)
  const idsVistosRef = useRef(new Set())
  const primeiraCargaRef = useRef(true)
  const [pendentes, setPendentes] = useState([])
  const habilitado = user?.perfil === 'admin' || user?.perfil === 'gestor'

  const { data } = useQuery({
    queryKey: ['notificacoes', 'nao-lidas'],
    queryFn: () => notificacoesApi.naoLidas().then(r => r.data),
    enabled: habilitado,
    refetchInterval: 20_000,
  })

  const total = data?.total ?? 0
  const notificacoes = data?.notificacoes ?? []

  useEffect(() => {
    if (total > totalAnteriorRef.current) {
      tocarBeep()
    }
    totalAnteriorRef.current = total
  }, [total])

  useEffect(() => {
    if (notificacoes.length === 0) return

    if (primeiraCargaRef.current) {
      primeiraCargaRef.current = false
      notificacoes.forEach(n => idsVistosRef.current.add(n.id))
      return
    }

    const novas = notificacoes.filter(n => !idsVistosRef.current.has(n.id))
    if (novas.length === 0) return

    novas.forEach(n => idsVistosRef.current.add(n.id))
    setPendentes(prev => [...prev, ...novas])
  }, [notificacoes])

  const removerPendente = (id) => {
    setPendentes(prev => prev.filter(n => n.id !== id))
  }

  const marcarTodasLidas = async () => {
    await notificacoesApi.marcarTodasLidas()
    qc.setQueryData(['notificacoes', 'nao-lidas'], { total: 0, notificacoes: [] })
    totalAnteriorRef.current = 0
  }

  return { total, notificacoes, marcarTodasLidas, pendentes, removerPendente }
}
