import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../contexts/AuthContext'
import * as notificacoesApi from '../api/notificacoes'

const MAX_IDS_ANUNCIADOS = 200

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

function chaveAnunciados(userId) {
  return `notificacoes_popup_anunciados_${userId}`
}

function lerAnunciados(userId) {
  try {
    const bruto = window.localStorage.getItem(chaveAnunciados(userId))
    return new Set(bruto ? JSON.parse(bruto) : [])
  } catch {
    return new Set()
  }
}

function salvarAnunciados(userId, idsSet) {
  try {
    const ids = [...idsSet].slice(-MAX_IDS_ANUNCIADOS)
    window.localStorage.setItem(chaveAnunciados(userId), JSON.stringify(ids))
  } catch {
    // localStorage indisponível (ex: modo privado) — pop-up pode repetir entre sessões, aceitável.
  }
}

export function useNotificacoes() {
  const { user } = useAuth()
  const qc = useQueryClient()
  const totalAnteriorRef = useRef(0)
  const anunciadosRef = useRef(null)
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
    if (!user?.id || notificacoes.length === 0) return

    if (!anunciadosRef.current) {
      anunciadosRef.current = lerAnunciados(user.id)
    }

    const novas = notificacoes.filter(n => !anunciadosRef.current.has(n.id))
    if (novas.length === 0) return

    novas.forEach(n => anunciadosRef.current.add(n.id))
    salvarAnunciados(user.id, anunciadosRef.current)
    setPendentes(prev => [...prev, ...novas])
  }, [notificacoes, user?.id])

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
