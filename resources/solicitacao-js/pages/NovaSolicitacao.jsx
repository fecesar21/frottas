import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import * as solicitacoesApi from '../api/solicitacoes'
import * as pontosViagemApi from '../api/pontosViagem'
import Layout from '../components/Layout'
import PainelFrota from '../components/PainelFrota'
import * as motivosApi from '../api/motivosViagem'
import * as unidadesApi from '../api/unidades'
import { useAuth } from '../contexts/AuthContext'

const INITIAL = {
  motivo: '',
  origem_tipo: '',
  origem_id: '',
  destino_tipo: '',
  destino_id: '',
  numero_atendimento: '',
  autorizacao_referencia_em: '',
  cidade: '',
  hospital_destino: '',
  fornecedor_nome: '',
  observacoes: '',
}

// Motivos cuja origem é sempre a unidade do solicitante (espelha MotivoViagem::origemNaUnidadeDoSolicitante).
const normalizar = (nome) => (nome ?? '').normalize('NFD').replace(/[0300-036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim()

const origemNaUnidade = (motivo) => !!motivo && (
  motivo.codigo === 'transferencia_paciente' || motivo.codigo.startsWith('exames') || normalizar(motivo.nome).startsWith('exames')
)

// Destinos que a Transferência de Paciente não pode ter, por unidade de origem (espelha MotivoViagem::DESTINOS_BLOQUEADOS_TRANSFERENCIA).
const DESTINOS_BLOQUEADOS_TRANSFERENCIA = {
  upa: ['imed', 'itpac', 'ame', 'pai'],
  pai: ['imed', 'itpac', 'upa', 'ame'],
}

// Únicos destinos permitidos em "Exames - Levar Paciente", por unidade de origem (espelha MotivoViagem::DESTINOS_PERMITIDOS_EXAMES).
const DESTINOS_PERMITIDOS_EXAMES = {
  upa: ['hma', 'hmdo', 'imed', 'itpac'],
  pai: ['hma', 'hmdo', 'imed', 'itpac'],
}

const ehExames = (motivo) => !!motivo && (motivo.codigo.startsWith('exames') || normalizar(motivo.nome).startsWith('exames'))

// Renal Center e Instituto de Doenças Renais Tocantins: nunca origem, e destino só em Exames (espelha MotivoViagem::somenteDestino).
const somenteDestino = (nome) => /\brena(l|is)\b/.test(normalizar(nome))

// Espelha MotivoViagem::destinoBloqueado.
const destinoBloqueado = (motivo, unidadeNome, destinoNome) => {
  if (!motivo) return false
  const palavras = normalizar(unidadeNome).split(/[^a-z0-9]+/)
  const destino = normalizar(destinoNome)
  if (somenteDestino(destinoNome)) return !ehExames(motivo)
  if (motivo.codigo === 'transferencia_paciente') {
    return Object.entries(DESTINOS_BLOQUEADOS_TRANSFERENCIA)
      .some(([origem, bloqueados]) => palavras.includes(origem) && bloqueados.includes(destino))
  }
  if (ehExames(motivo)) {
    return Object.entries(DESTINOS_PERMITIDOS_EXAMES)
      .some(([origem, permitidos]) => palavras.includes(origem) && !permitidos.includes(destino))
  }
  return false
}

// "Agora" no formato do input datetime-local (horário local), usado como limite máximo.
const agoraLocal = () => {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().slice(0, 16)
}

export default function NovaSolicitacao() {
  const navigate = useNavigate()
  const { user } = useAuth()
  const [unidade, setUnidade] = useState(null)
  const [form, setForm] = useState(INITIAL)
  const [pontos, setPontos] = useState([])
  const [motivos, setMotivos] = useState([])
  // 'carregando' | 'ok' | 'erro'
  const [estadoMotivos, setEstadoMotivos] = useState('carregando')
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [sucesso, setSucesso] = useState(false)

  useEffect(() => {
    pontosViagemApi.listar().then(({ data }) => setPontos(data.data ?? data)).catch(() => {})
  }, [])

  useEffect(() => {
    if (!user?.unidade_id) return
    unidadesApi.listar()
      .then(({ data }) => setUnidade((data.data ?? data).find(u => u.id === user.unidade_id) ?? null))
      .catch(() => {})
  }, [user?.unidade_id])

  // Motivos cadastrados e liberados para solicitação (este app não usa React Query).
  useEffect(() => {
    motivosApi.listar({ solicitacao: 1 })
      .then(({ data }) => {
        setMotivos(data.data ?? data)
        setEstadoMotivos('ok')
      })
      .catch(() => setEstadoMotivos('erro'))
  }, [])

  const setField = (field, value) => setForm(f => ({ ...f, [field]: value }))

  const setPonto = (prefixo, pontoId) => {
    const ponto = pontos.find(p => p.id === pontoId)
    setForm(f => ({ ...f, [`${prefixo}_id`]: pontoId, [`${prefixo}_tipo`]: ponto?.tipo ?? '' }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setErrors({})
    setSucesso(false)
    setLoading(true)
    try {
      await solicitacoesApi.criar(form)
      setSucesso(true)
      setForm(INITIAL)
      window.scrollTo({ top: 0, behavior: 'smooth' })
    } catch (err) {
      setErrors(err.response?.data?.errors ?? {})
    } finally {
      setLoading(false)
    }
  }

  const fe = (field) => errors[field] && (
    <p className="text-xs text-red-600 mt-1">{errors[field][0]}</p>
  )

  const motivoSelecionado = motivos.find(m => m.codigo === form.motivo)
  const exigeOrigemDestino = origemNaUnidade(motivoSelecionado)
  const origemFixa = !!user?.unidade_id && exigeOrigemDestino
  const precisaOrigemDestino = exigeOrigemDestino || ['transferencia_paciente', 'transporte_colaborador'].includes(form.motivo)
  // Com origem fixa, o destino não pode ser o próprio local do solicitante.
  const pontosDestino = origemFixa && unidade
    ? pontos.filter(p => normalizar(p.nome) !== normalizar(unidade.nome)
      && !destinoBloqueado(motivoSelecionado, unidade.nome, p.nome))
    : pontos.filter(p => !destinoBloqueado(motivoSelecionado, '', p.nome))
  const pontosOrigem = pontos.filter(p => !somenteDestino(p.nome))
  const precisaAtendimento = form.motivo === 'transferencia_paciente'
  const precisaCidade = form.motivo === 'buscar_medico'
  const precisaHospital = form.motivo === 'material_outro_hospital'
  const precisaFornecedor = form.motivo === 'buscar_material_fornecedor'

  return (
    <Layout lateral={<PainelFrota />}>
      <h1 className="text-xl font-bold text-gray-900 mb-1">Solicitação de Transporte</h1>
      <p className="text-sm text-gray-500 mb-6">Preencha os dados abaixo para solicitar um transporte.</p>

      {sucesso && (
        <div className="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4">
          Solicitação enviada com sucesso! Acompanhe em "Minhas Solicitações".
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-xs border border-gray-100 p-6 space-y-5">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Motivo da Viagem *</label>
          <div className="space-y-2">
            {estadoMotivos === 'carregando' && (
              <p className="text-sm text-gray-500">Carregando motivos...</p>
            )}
            {estadoMotivos === 'erro' && (
              <p className="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
                Não foi possível carregar os motivos de viagem. Recarregue a página ou tente novamente em instantes.
              </p>
            )}
            {estadoMotivos === 'ok' && motivos.length === 0 && (
              <p className="text-sm text-gray-500">Nenhum motivo disponível para solicitação no momento. Procure a gestão da frota.</p>
            )}
            {motivos.map(m => (
              <label key={m.codigo} className="flex items-center gap-2.5 border border-gray-200 rounded-xl px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                <input
                  type="radio"
                  name="motivo"
                  value={m.codigo}
                  checked={form.motivo === m.codigo}
                  onChange={(e) => setField('motivo', e.target.value)}
                  className="accent-brand-500"
                />
                <span className="text-sm text-gray-800">{m.nome}</span>
              </label>
            ))}
          </div>
          {fe('motivo')}
        </div>

        {precisaOrigemDestino && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Origem *</label>
              {origemFixa ? (
                <input
                  readOnly
                  value={unidade?.nome ?? 'Sua unidade'}
                  className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700"
                />
              ) : (
              <select
                required
                value={form.origem_id}
                onChange={(e) => setPonto('origem', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {pontosOrigem.map(p => <option key={`${p.tipo}-${p.id}`} value={p.id}>{p.nome}</option>)}
              </select>
              )}
              {fe('origem_id')}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Destino *</label>
              <select
                required
                value={form.destino_id}
                onChange={(e) => setPonto('destino', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {pontosDestino.map(p => <option key={`${p.tipo}-${p.id}`} value={p.id}>{p.nome}</option>)}
              </select>
              {fe('destino_id')}
            </div>
          </div>
        )}

        {precisaAtendimento && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Número do Atendimento *</label>
            <input
              type="number"
              required
              min={0}
              max={999999}
              value={form.numero_atendimento}
              onChange={(e) => setField('numero_atendimento', e.target.value.slice(0, 6))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
            />
            {fe('numero_atendimento')}
          </div>
        )}

        {precisaAtendimento && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Data e Hora da Autorização da Referência *</label>
            <input
              type="datetime-local"
              required
              max={agoraLocal()}
              value={form.autorizacao_referencia_em}
              onChange={(e) => setField('autorizacao_referencia_em', e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
            />
            {fe('autorizacao_referencia_em')}
          </div>
        )}

        {precisaCidade && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Cidade *</label>
            <input
              type="text"
              required
              value={form.cidade}
              onChange={(e) => setField('cidade', e.target.value.toUpperCase())}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
            />
            {fe('cidade')}
          </div>
        )}

        {precisaHospital && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Para qual hospital? *</label>
            <input
              type="text"
              required
              value={form.hospital_destino}
              onChange={(e) => setField('hospital_destino', e.target.value.toUpperCase())}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
            />
            {fe('hospital_destino')}
          </div>
        )}

        {precisaFornecedor && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Nome do Fornecedor *</label>
            <input
              type="text"
              required
              value={form.fornecedor_nome}
              onChange={(e) => setField('fornecedor_nome', e.target.value.toUpperCase())}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
            />
            {fe('fornecedor_nome')}
          </div>
        )}

        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
          <textarea
            rows={3}
            value={form.observacoes}
            onChange={(e) => setField('observacoes', e.target.value)}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-hidden focus:ring-2 focus:ring-brand-400"
          />
        </div>

        <button
          type="submit"
          disabled={!form.motivo || loading}
          className="w-full bg-gradient-to-r from-brand-500 to-brand-700 hover:brightness-110 disabled:opacity-60 text-white font-semibold py-3 rounded-xl transition-all duration-150 text-sm"
        >
          {loading ? 'Enviando...' : 'Enviar'}
        </button>
      </form>
    </Layout>
  )
}
