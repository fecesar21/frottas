import { useEffect, useState } from 'react'
import { Navigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Maximize, LogOut, Sun, Moon } from 'lucide-react'
import { useAuth } from '../../contexts/AuthContext'
import * as relatoriosApi from '../../api/relatorios'
import { entrarModoKiosk, sairModoKiosk, corDoTempo, formatarMinutos } from './kiosk'
import './painel.css'

const MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho',
  'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro']

const STATUS = {
  neutro: () => 'Sem Viagens Concluídas No Período',
  vermelho: (acordo) => `Acima Do Acordo De ${acordo} Minutos`,
  laranja: (acordo) => `Próximo Do Limite De ${acordo} Minutos`,
  verde: (acordo) => `Dentro Do Acordo De ${acordo} Minutos`,
}

const ETAPAS = [
  { chave: 'autorizacao_solicitacao', nome: 'Autorização Até Solicitação De Transporte' },
  { chave: 'solicitacao_inicio', nome: 'Solicitação Até Início Da Viagem' },
  { chave: 'inicio_fim', nome: 'Início Até Fim Da Viagem' },
]

const CHAVE_TEMA = 'hd_painel_tema'

function temaInicial() {
  try {
    const salvo = localStorage.getItem(CHAVE_TEMA)
    if (salvo === 'claro' || salvo === 'escuro') return salvo
  } catch { /* armazenamento indisponível */ }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'escuro' : 'claro'
}

// Separa número e unidade para que a unidade fique menor ao lado do número.
function Tempo({ minutos }) {
  const texto = formatarMinutos(minutos)
  const m = texto.match(/^(\d+)\s?(min)$/)
  return m ? <>{m[1]}<small>{m[2]}</small></> : texto
}

function LinhaDoTempo({ medias, acordo }) {
  const valores = ETAPAS.map(e => medias[e.chave] ?? 0)
  const soma = valores.reduce((a, b) => a + b, 0)
  const escala = Math.max(acordo * 1.25, soma)

  return (
    <div className="painel-linha">
      <div className="painel-barra" role="img"
        aria-label={`Etapas somam ${Math.round(soma)} minutos; acordo de ${acordo} minutos`}>
        {valores.map((v, i) => (
          <div key={ETAPAS[i].chave} className="painel-seg" style={{ flexGrow: v / escala, flexBasis: 0 }} />
        ))}
        <div className="painel-resto" style={{ flexGrow: Math.max(escala - soma, 0) / escala, flexBasis: 0 }} />
        <div className="painel-marco" style={{ left: `${(acordo / escala) * 100}%` }}>
          <span>Acordo {acordo} Min</span>
        </div>
      </div>
      <div className="painel-etapas">
        {ETAPAS.map(e => (
          <div key={e.chave} className="painel-etapa">
            <p className="painel-etapa-nome">{e.nome}</p>
            <p className="painel-etapa-valor"><Tempo minutos={medias[e.chave]} /></p>
          </div>
        ))}
      </div>
    </div>
  )
}

export default function PainelGerencial() {
  const { user, logout } = useAuth()
  const hoje = new Date()
  const [mes, setMes] = useState(hoje.getMonth() + 1)
  const [ano, setAno] = useState(hoje.getFullYear())
  const [tema, setTema] = useState(temaInicial)
  const [telaCheia, setTelaCheia] = useState(!!document.fullscreenElement)

  useEffect(() => {
    const onChange = () => setTelaCheia(!!document.fullscreenElement)
    document.addEventListener('fullscreenchange', onChange)
    return () => document.removeEventListener('fullscreenchange', onChange)
  }, [])

  const { data, isLoading, dataUpdatedAt } = useQuery({
    queryKey: ['dashboard-gerencial', mes, ano],
    queryFn: () => relatoriosApi.dashboardGerencialTransferencias({ mes, ano }).then(r => r.data),
    refetchInterval: 60_000,
    enabled: !!user,
  })

  if (!user) return <Navigate to="/login" replace />
  if (!['dashboard', 'admin', 'gestor'].includes(user.perfil)) return <Navigate to="/" replace />

  const acordo = data?.acordo_minutos ?? 30
  const medias = data?.medias_minutos ?? {}
  const cor = corDoTempo(medias.autorizacao_fim, acordo)
  const anos = Array.from({ length: 5 }, (_, i) => hoje.getFullYear() - i)

  const alternarTema = () => {
    const novo = tema === 'escuro' ? 'claro' : 'escuro'
    setTema(novo)
    try { localStorage.setItem(CHAVE_TEMA, novo) } catch { /* ignora */ }
  }

  const sair = async () => {
    sairModoKiosk()
    await logout()
  }

  return (
    <div className="painel" data-theme={tema}>
      <header className="painel-topo">
        <div>
          <h1 className="painel-titulo">Transferência De Pacientes</h1>
          <p className="painel-sub">Acordo De {acordo} Minutos Entre A Autorização Da Referência E A Chegada Ao Destino</p>
        </div>
        <div className="painel-controles">
          <select aria-label="Mês" className="painel-select" value={mes} onChange={e => setMes(Number(e.target.value))}>
            {MESES.map((nome, i) => <option key={nome} value={i + 1}>{nome}</option>)}
          </select>
          <select aria-label="Ano" className="painel-select" value={ano} onChange={e => setAno(Number(e.target.value))}>
            {anos.map(a => <option key={a} value={a}>{a}</option>)}
          </select>
          <button className="painel-botao" onClick={alternarTema}
            title={tema === 'escuro' ? 'Usar Modo Claro' : 'Usar Modo Escuro'}
            aria-label={tema === 'escuro' ? 'Usar Modo Claro' : 'Usar Modo Escuro'}>
            {tema === 'escuro' ? <Sun size={20} /> : <Moon size={20} />}
          </button>
          {!telaCheia && (
            <button className="painel-botao" onClick={entrarModoKiosk} title="Tela Cheia" aria-label="Tela Cheia">
              <Maximize size={20} />
            </button>
          )}
          <button className="painel-botao" onClick={sair} title="Sair" aria-label="Sair">
            <LogOut size={20} />
          </button>
        </div>
      </header>

      {isLoading ? (
        <p className="painel-rotulo">Carregando Indicadores…</p>
      ) : (
        <main className="painel-corpo">
          <section className="painel-destaque" data-cor={cor}>
            <p className="painel-rotulo">Tempo Médio Da Autorização Até O Fim Da Viagem</p>
            <p className="painel-numero"><Tempo minutos={medias.autorizacao_fim} /></p>
            <p className="painel-status">{STATUS[cor](acordo)}</p>
          </section>

          <section className="painel-total">
            <p className="painel-rotulo">Transferências No Mês</p>
            <p className="painel-numero">{data?.total_transferencias ?? 0}</p>
            <p className="painel-total-periodo">{MESES[mes - 1]} De {ano}</p>
          </section>

          <LinhaDoTempo medias={medias} acordo={acordo} />
        </main>
      )}

      <footer className="painel-rodape">
        Atualizado Automaticamente A Cada Minuto
        {dataUpdatedAt ? `, Última Atualização Às ${new Date(dataUpdatedAt).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` : ''}
      </footer>
    </div>
  )
}
