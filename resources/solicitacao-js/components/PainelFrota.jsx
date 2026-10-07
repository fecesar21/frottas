import { useEffect, useState } from 'react'
import { Truck, User, RefreshCw } from 'lucide-react'
import * as frotaApi from '../api/frota'

const INTERVALO_MS = 15_000

const STATUS = {
  disponivel: { label: 'Disponível', classe: 'bg-green-50 text-green-700 border-green-200', ponto: 'bg-green-500' },
  em_viagem: { label: 'Em Viagem', classe: 'bg-blue-50 text-blue-700 border-blue-200', ponto: 'bg-blue-500' },
  manutencao: { label: 'Em Manutenção', classe: 'bg-amber-50 text-amber-700 border-amber-200', ponto: 'bg-amber-500' },
}

function Badge({ status }) {
  const s = STATUS[status] ?? STATUS.disponivel
  return (
    <span className={`shrink-0 inline-flex items-center gap-1.5 text-[11px] font-medium px-2 py-0.5 rounded-full border ${s.classe}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${s.ponto}`} />
      {s.label}
    </span>
  )
}

const hora = (iso) => (iso ? new Date(iso).toLocaleTimeString('pt-BR') : '')

export default function PainelFrota() {
  const [dados, setDados] = useState(null)
  const [erro, setErro] = useState(false)
  const [aba, setAba] = useState('veiculos')

  useEffect(() => {
    let ativo = true
    const carregar = () =>
      frotaApi.status()
        .then(({ data }) => { if (ativo) { setDados(data); setErro(false) } })
        .catch(() => { if (ativo) setErro(true) })

    carregar()
    const timer = setInterval(() => { if (!document.hidden) carregar() }, INTERVALO_MS)
    const aoVoltar = () => { if (!document.hidden) carregar() }
    document.addEventListener('visibilitychange', aoVoltar)
    return () => {
      ativo = false
      clearInterval(timer)
      document.removeEventListener('visibilitychange', aoVoltar)
    }
  }, [])

  const veiculos = dados?.veiculos ?? []
  const motoristas = dados?.motoristas ?? []
  const contar = (s) => veiculos.filter((v) => v.status === s).length
  const lista = aba === 'veiculos' ? veiculos : motoristas

  return (
    <section className="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
      <header className="px-4 py-3 border-b border-gray-100">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-semibold text-gray-900">Frota agora</h2>
          <span className="flex items-center gap-1 text-[11px] text-gray-400">
            <RefreshCw size={11} />
            {dados ? `Atualizado às ${hora(dados.atualizado_em)}` : 'Carregando...'}
          </span>
        </div>
        <p className="mt-1 text-xs text-gray-500">
          {contar('disponivel')} disponíveis · {contar('em_viagem')} em viagem · {contar('manutencao')} em manutenção
        </p>
      </header>

      <div className="flex text-xs font-medium border-b border-gray-100">
        {[['veiculos', 'Veículos', veiculos.length], ['motoristas', 'Motoristas', motoristas.length]].map(([id, label, n]) => (
          <button
            key={id}
            type="button"
            onClick={() => setAba(id)}
            className={`flex-1 py-2 ${aba === id ? 'text-brand-700 border-b-2 border-brand-500' : 'text-gray-500 hover:text-gray-700'}`}
          >
            {label} ({n})
          </button>
        ))}
      </div>

      {erro && <p className="px-4 py-2 text-xs text-red-600 bg-red-50">Não foi possível atualizar o painel.</p>}

      <ul className="divide-y divide-gray-100 max-h-[60vh] overflow-y-auto">
        {dados && lista.length === 0 && (
          <li className="px-4 py-6 text-center text-xs text-gray-400">Nenhum {aba === 'veiculos' ? 'veículo' : 'motorista'} em plantão.</li>
        )}
        {aba === 'veiculos'
          ? veiculos.map((v) => (
            <li key={v.id} className="px-4 py-2.5 flex items-center gap-3">
              <Truck size={15} className="text-gray-400 shrink-0" />
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-800">{v.placa}</p>
                <p className="text-[11px] text-gray-500 truncate">{[v.modelo, v.motorista_nome].filter(Boolean).join(' · ')}</p>
              </div>
              <Badge status={v.status} />
            </li>
          ))
          : motoristas.map((m) => (
            <li key={m.id} className="px-4 py-2.5 flex items-center gap-3">
              <User size={15} className="text-gray-400 shrink-0" />
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-gray-800 truncate">{m.nome}</p>
                {m.placa && <p className="text-[11px] text-gray-500">{m.placa}</p>}
              </div>
              <Badge status={m.status} />
            </li>
          ))}
      </ul>
    </section>
  )
}
