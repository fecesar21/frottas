import { useEffect, useRef, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useAuth } from '../contexts/AuthContext'
import * as notificacoesApi from '../api/notificacoes'

import { tocarBeep, tocarChamada, vibrar } from '../utils/somAlerta'

const MAX_IDS_ANUNCIADOS = 200
const INTERVALO_CHAMADA_MS = 3_000

function exigeResposta(notificacao) {
  return !!notificacao.type?.match(/(NovaViagemDesignada|NovaSolicitacaoDisponivel)$/)
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
  const convitesExibidosRef = useRef(new Set())
  const [pendentes, setPendentes] = useState([])
  const habilitado = user?.perfil === 'admin' || user?.perfil === 'gestor' || (user?.perfil === 'operador' && !!user?.motorista_id)

  const { data } = useQuery({
    queryKey: ['notificacoes', 'nao-lidas'],
    queryFn: () => notificacoesApi.naoLidas().then(r => r.data),
    enabled: habilitado,
    refetchInterval: 20_000,
  })

  const total = data?.total ?? 0
  const notificacoes = data?.notificacoes ?? []

  useEffect(() => {
    // Convites ao motorista têm toque próprio (abaixo); o bipe é para os demais avisos.
    if (total > totalAnteriorRef.current && !notificacoes.some(exigeResposta)) {
      tocarBeep()
    }
    totalAnteriorRef.current = total
  }, [total]) // eslint-disable-line react-hooks/exhaustive-deps

  // Enquanto houver convite aberto sem resposta, toca e vibra em loop (estilo app de corrida).
  const temConviteAberto = pendentes.some(exigeResposta)
  useEffect(() => {
    if (!temConviteAberto) return
    tocarChamada()
    const id = setInterval(tocarChamada, INTERVALO_CHAMADA_MS)
    return () => {
      clearInterval(id)
      vibrar(0)
    }
  }, [temConviteAberto])

  useEffect(() => {
    if (!user?.id || notificacoes.length === 0) return

    if (!anunciadosRef.current) {
      anunciadosRef.current = lerAnunciados(user.id)
    }

    // Convites ao motorista só são lembrados durante a sessão: após recarregar a
    // página o pop-up volta enquanto o backend os listar (até aceitar ou recusar).
    const novas = notificacoes.filter(n => exigeResposta(n)
      ? !convitesExibidosRef.current.has(n.id)
      : !anunciadosRef.current.has(n.id))
    if (novas.length === 0) return

    novas.forEach(n => {
      if (exigeResposta(n)) {
        convitesExibidosRef.current.add(n.id)
      } else {
        anunciadosRef.current.add(n.id)
      }
    })
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
