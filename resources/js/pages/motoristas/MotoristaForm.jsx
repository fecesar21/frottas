import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X, Plus, Building2 } from 'lucide-react'
import * as motoristasApi from '../../api/motoristas'
import * as unidadesApi from '../../api/unidades'
import Alert from '../../components/ui/Alert'

function mascaraCpf(v) {
  const d = v.replace(/\D/g, '').slice(0, 11)
  if (d.length <= 3) return d
  if (d.length <= 6) return `${d.slice(0,3)}.${d.slice(3)}`
  if (d.length <= 9) return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6)}`
  return `${d.slice(0,3)}.${d.slice(3,6)}.${d.slice(6,9)}-${d.slice(9)}`
}

function mascaraTelefone(v) {
  const d = v.replace(/\D/g, '').slice(0, 11)
  if (d.length <= 2) return d
  if (d.length <= 7) return `(${d.slice(0,2)}) ${d.slice(2)}`
  return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`
}

export default function MotoristaForm({ motorista, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    nome: motorista?.nome ?? '',
    cpf: mascaraCpf(motorista?.cpf ?? ''),
    telefone: mascaraTelefone(motorista?.telefone ?? ''),
    email: motorista?.email ?? '',
    cnh_numero: motorista?.cnh_numero ?? '',
    cnh_categoria: motorista?.cnh_categoria ?? 'D',
    cnh_validade: motorista?.cnh_validade ?? '',
    turno_padrao: motorista?.turno_padrao ?? '',
    observacoes: motorista?.observacoes ?? '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [painelUnidades, setPainelUnidades] = useState(false)
  const [selecionados, setSelecionados] = useState([])
  const [erroUnidade, setErroUnidade] = useState('')

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))
  const setMask = (k, fn) => (e) => setForm(f => ({ ...f, [k]: fn(e.target.value) }))

  // Dados do motorista com unidades (apenas em modo edição)
  const { data: motoristaAtual, refetch: refetchUnidades } = useQuery({
    queryKey: ['motorista', motorista?.id, 'detalhes'],
    queryFn: () => motoristasApi.buscar(motorista.id).then(r => r.data.data ?? r.data),
    enabled: !!motorista?.id,
  })

  const { data: todasUnidades = [] } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data ?? []),
    enabled: !!motorista?.id,
  })

  const unidadesVinculadas = motoristaAtual?.unidades ?? motorista?.unidades ?? []
  const vinculadasIds = new Set(unidadesVinculadas.map(u => u.id))
  const disponiveis = todasUnidades.filter(u => !vinculadasIds.has(u.id))

  const vincular = useMutation({
    mutationFn: async () => {
      for (const unidadeId of selecionados) {
        await unidadesApi.vincularMotoristas(unidadeId, [motorista.id])
      }
    },
    onSuccess: () => {
      refetchUnidades()
      setSelecionados([])
      setPainelUnidades(false)
      setErroUnidade('')
    },
    onError: (e) => setErroUnidade(e.response?.data?.message ?? 'Erro ao vincular'),
  })

  const desvincular = useMutation({
    mutationFn: (unidadeId) => unidadesApi.desvincularMotorista(unidadeId, motorista.id),
    onSuccess: () => refetchUnidades(),
    onError: (e) => setErroUnidade(e.response?.data?.message ?? 'Erro ao desvincular'),
  })

  const toggleSelecionado = (id) =>
    setSelecionados(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  const salvar = useMutation({
    mutationFn: (data) => motorista ? motoristasApi.atualizar(motorista.id, data) : motoristasApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['motoristas'] }); onSuccess() },
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao salvar')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    const payload = { ...form }
    if (payload.cpf)      payload.cpf      = payload.cpf.replace(/\D/g, '')
    if (payload.telefone) payload.telefone = payload.telefone.replace(/\D/g, '')
    Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null })
    salvar.mutate(payload)
  }

  const fe = (key) => fieldErrors[key] ? <p className="text-red-500 text-xs mt-1">{fieldErrors[key][0]}</p> : null
  const inp = (key, type = 'text', extra = {}) => (
    <input type={type} value={form[key] ?? ''} onChange={set(key)}
      className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
      {...extra} />
  )

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="grid grid-cols-2 gap-4">
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
          {inp('nome', 'text', { required: true })} {fe('nome')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">CPF *</label>
          <input type="text" required value={form.cpf} onChange={setMask('cpf', mascaraCpf)}
            placeholder="000.000.000-00" inputMode="numeric"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('cpf')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
          <input type="text" value={form.telefone} onChange={setMask('telefone', mascaraTelefone)}
            placeholder="(00) 00000-0000" inputMode="numeric"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          {fe('telefone')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
          {inp('email', 'email')} {fe('email')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">CNH *</label>
          {inp('cnh_numero', 'text', { required: true })} {fe('cnh_numero')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Categoria *</label>
          <select value={form.cnh_categoria} onChange={set('cnh_categoria')} required
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            {['A', 'B', 'C', 'D', 'E', 'AB', 'AC', 'AD', 'AE'].map(c => <option key={c}>{c}</option>)}
          </select>
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Validade CNH *</label>
          {inp('cnh_validade', 'date', { required: true })} {fe('cnh_validade')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Turno padrão</label>
          <select value={form.turno_padrao} onChange={set('turno_padrao')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Não definido</option>
            <option value="dia">Dia</option>
            <option value="noite">Noite</option>
          </select>
        </div>
        <div className="col-span-2">
          <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
          <textarea value={form.observacoes ?? ''} onChange={set('observacoes')} rows={3}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
      </div>

      {/* Seção de Unidades — apenas em modo edição */}
      {motorista ? (
        <div className="border-t border-gray-100 pt-4">
          {erroUnidade && <Alert type="error" message={erroUnidade} />}
          <div className="flex items-center justify-between mb-2">
            <div className="flex items-center gap-1.5">
              <Building2 size={14} className="text-gray-400" />
              <span className="text-sm font-medium text-gray-700">Unidades vinculadas</span>
            </div>
            {!painelUnidades && disponiveis.length > 0 && (
              <button
                type="button"
                onClick={() => { setPainelUnidades(true); setSelecionados([]) }}
                className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700 font-medium"
              >
                <Plus size={12} /> Vincular
              </button>
            )}
          </div>

          <div className="flex flex-wrap gap-1.5 min-h-[28px]">
            {unidadesVinculadas.length === 0 && !painelUnidades && (
              <p className="text-xs text-amber-600">Este motorista não está vinculado a nenhuma unidade</p>
            )}
            {unidadesVinculadas.map(u => (
              <span
                key={u.id}
                className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium ${
                  u.tipo === 'matriz' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'
                }`}
              >
                {u.nome}
                <button
                  type="button"
                  onClick={() => desvincular.mutate(u.id)}
                  disabled={desvincular.isPending}
                  className="ml-0.5 hover:opacity-70"
                >
                  <X size={11} />
                </button>
              </span>
            ))}
          </div>

          {painelUnidades && (
            <div className="mt-3 border border-gray-200 rounded-lg overflow-hidden">
              <div className="max-h-40 overflow-y-auto divide-y divide-gray-100">
                {disponiveis.length === 0 ? (
                  <p className="px-3 py-2 text-xs text-gray-400">Nenhuma unidade disponível para vincular</p>
                ) : disponiveis.map(u => (
                  <label key={u.id} className="flex items-center gap-2.5 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={selecionados.includes(u.id)}
                      onChange={() => toggleSelecionado(u.id)}
                      className="rounded accent-blue-600"
                    />
                    <span className="text-sm text-gray-700">
                      {u.nome}
                      <span className={`ml-1.5 text-xs px-1.5 py-0.5 rounded-full ${
                        u.tipo === 'matriz' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600'
                      }`}>{u.tipo}</span>
                    </span>
                  </label>
                ))}
              </div>
              <div className="flex items-center justify-end gap-2 px-3 py-2 bg-gray-50 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => { setPainelUnidades(false); setSelecionados([]) }}
                  className="text-xs text-gray-500 hover:text-gray-700"
                >
                  Cancelar
                </button>
                <button
                  type="button"
                  onClick={() => vincular.mutate()}
                  disabled={selecionados.length === 0 || vincular.isPending}
                  className="flex items-center gap-1 bg-blue-600 text-white px-3 py-1.5 rounded-lg text-xs font-medium hover:bg-blue-700 disabled:opacity-60 transition-colors"
                >
                  {vincular.isPending ? 'Vinculando...' : `Vincular (${selecionados.length})`}
                </button>
              </div>
            </div>
          )}
        </div>
      ) : (
        <p className="text-xs text-gray-400 border-t border-gray-100 pt-4">
          Salve o cadastro primeiro para vincular a unidades.
        </p>
      )}

      <div className="flex justify-end gap-3 pt-2">
        <button type="submit" disabled={salvar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60 transition-colors">
          {salvar.isPending ? 'Salvando...' : 'Salvar'}
        </button>
      </div>
    </form>
  )
}
