import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import * as solicitacoesApi from '../api/solicitacoes'
import * as unidadesApi from '../api/unidades'
import Layout from '../components/Layout'

const MOTIVOS = [
  { value: 'transferencia_paciente', label: 'Transferência de Paciente' },
  { value: 'buscar_medico', label: 'Buscar Médicos em outra cidade' },
  { value: 'material_outro_hospital', label: 'Levar Material em outro Hospital' },
  { value: 'transporte_colaborador', label: 'Transporte de Colaborador(es)' },
  { value: 'buscar_material_fornecedor', label: 'Buscar materiais em fornecedor' },
  { value: 'tfd', label: 'TFD' },
]

const INITIAL = {
  motivo: '',
  origem_unidade_id: '',
  destino_unidade_id: '',
  numero_atendimento: '',
  cidade: '',
  hospital_destino: '',
  fornecedor_nome: '',
  observacoes: '',
}

export default function NovaSolicitacao() {
  const navigate = useNavigate()
  const [form, setForm] = useState(INITIAL)
  const [unidades, setUnidades] = useState([])
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(false)
  const [sucesso, setSucesso] = useState(false)

  useEffect(() => {
    unidadesApi.listar().then(({ data }) => setUnidades(data.data ?? data)).catch(() => {})
  }, [])

  const setField = (field, value) => setForm(f => ({ ...f, [field]: value }))

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

  const precisaOrigemDestino = ['transferencia_paciente', 'transporte_colaborador'].includes(form.motivo)
  const precisaAtendimento = form.motivo === 'transferencia_paciente'
  const precisaCidade = form.motivo === 'buscar_medico'
  const precisaHospital = form.motivo === 'material_outro_hospital'
  const precisaFornecedor = form.motivo === 'buscar_material_fornecedor'

  return (
    <Layout>
      <h1 className="text-xl font-bold text-gray-900 mb-1">Solicitação de Transporte</h1>
      <p className="text-sm text-gray-500 mb-6">Preencha os dados abaixo para solicitar um transporte.</p>

      {sucesso && (
        <div className="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4">
          Solicitação enviada com sucesso! Acompanhe em "Minhas Solicitações".
        </div>
      )}

      <form onSubmit={handleSubmit} className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-2">Motivo da Viagem *</label>
          <div className="space-y-2">
            {MOTIVOS.map(m => (
              <label key={m.value} className="flex items-center gap-2.5 border border-gray-200 rounded-xl px-3 py-2.5 cursor-pointer hover:bg-gray-50 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                <input
                  type="radio"
                  name="motivo"
                  value={m.value}
                  checked={form.motivo === m.value}
                  onChange={(e) => setField('motivo', e.target.value)}
                  className="accent-brand-500"
                />
                <span className="text-sm text-gray-800">{m.label}</span>
              </label>
            ))}
          </div>
          {fe('motivo')}
        </div>

        {precisaOrigemDestino && (
          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Origem *</label>
              <select
                required
                value={form.origem_unidade_id}
                onChange={(e) => setField('origem_unidade_id', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {unidades.map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}
              </select>
              {fe('origem_unidade_id')}
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Destino *</label>
              <select
                required
                value={form.destino_unidade_id}
                onChange={(e) => setField('destino_unidade_id', e.target.value)}
                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
              >
                <option value="">Selecione...</option>
                {unidades.map(u => <option key={u.id} value={u.id}>{u.nome}</option>)}
              </select>
              {fe('destino_unidade_id')}
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
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
            />
            {fe('numero_atendimento')}
          </div>
        )}

        {precisaCidade && (
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Cidade *</label>
            <input
              type="text"
              required
              value={form.cidade}
              onChange={(e) => setField('cidade', e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
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
              onChange={(e) => setField('hospital_destino', e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
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
              onChange={(e) => setField('fornecedor_nome', e.target.value)}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
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
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
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
