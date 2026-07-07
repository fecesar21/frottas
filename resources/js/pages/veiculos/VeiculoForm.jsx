import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X, Plus, Building2 } from 'lucide-react'
import * as veiculosApi from '../../api/veiculos'
import * as unidadesApi from '../../api/unidades'
import Alert from '../../components/ui/Alert'

const marcas = [
  'Chevrolet', 'Citroën', 'Fiat', 'Ford', 'Honda', 'Hyundai', 'Jeep',
  'Mercedes-Benz', 'Mitsubishi', 'Nissan', 'Peugeot', 'Renault',
  'Scania', 'Toyota', 'Volkswagen', 'Volvo',
]

const combustiveis = ['diesel_s10', 'diesel_s500', 'gasolina', 'gasolina_aditivada', 'etanol', 'gnv', 'flex']

export default function VeiculoForm({ veiculo, onSuccess }) {
  const qc = useQueryClient()
  const [form, setForm] = useState({
    placa: veiculo?.placa ?? '',
    modelo: veiculo?.modelo ?? '',
    marca: veiculo?.marca ?? '',
    ano: veiculo?.ano ?? new Date().getFullYear(),
    cor: veiculo?.cor ?? '',
    chassi: veiculo?.chassi ?? '',
    renavam: veiculo?.renavam ?? '',
    combustivel: veiculo?.combustivel ?? 'diesel_s10',
    capacidade_tanque: veiculo?.capacidade_tanque ?? '',
    km_atual: veiculo?.km_atual ?? 0,
    km_proxima_revisao: veiculo?.km_proxima_revisao ?? '',
    observacoes: veiculo?.observacoes ?? '',
  })
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})
  const [painelUnidades, setPainelUnidades] = useState(false)
  const [selecionados, setSelecionados] = useState([])
  const [erroUnidade, setErroUnidade] = useState('')

  const set = (k) => (e) => setForm(f => ({ ...f, [k]: e.target.value }))

  const setPlaca = (e) => {
    let v = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '')
    if (v.length > 3) v = v.slice(0, 3) + '-' + v.slice(3, 7)
    setForm(f => ({ ...f, placa: v }))
  }

  // Dados do veículo com unidades (apenas em modo edição)
  const { data: veiculoAtual, refetch: refetchUnidades } = useQuery({
    queryKey: ['veiculo', veiculo?.id, 'detalhes'],
    queryFn: () => veiculosApi.buscar(veiculo.id).then(r => r.data.data ?? r.data),
    enabled: !!veiculo?.id,
  })

  const { data: todasUnidades = [] } = useQuery({
    queryKey: ['unidades'],
    queryFn: () => unidadesApi.listar().then(r => r.data ?? []),
    enabled: !!veiculo?.id,
  })

  const unidadesVinculadas = veiculoAtual?.unidades ?? veiculo?.unidades ?? []
  const vinculadasIds = new Set(unidadesVinculadas.map(u => u.id))
  const disponiveis = todasUnidades.filter(u => !vinculadasIds.has(u.id))

  const vincular = useMutation({
    mutationFn: async () => {
      for (const unidadeId of selecionados) {
        await unidadesApi.vincularVeiculos(unidadeId, [veiculo.id])
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
    mutationFn: (unidadeId) => unidadesApi.desvincularVeiculo(unidadeId, veiculo.id),
    onSuccess: () => refetchUnidades(),
    onError: (e) => setErroUnidade(e.response?.data?.message ?? 'Erro ao desvincular'),
  })

  const toggleSelecionado = (id) =>
    setSelecionados(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id])

  const salvar = useMutation({
    mutationFn: (data) => veiculo ? veiculosApi.atualizar(veiculo.id, data) : veiculosApi.criar(data),
    onSuccess: () => { qc.invalidateQueries({ queryKey: ['veiculos'] }); onSuccess() },
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
    Object.keys(payload).forEach(k => { if (payload[k] === '') payload[k] = null })
    salvar.mutate(payload)
  }

  const field = (label, key, type = 'text', extra = {}) => (
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
      <input
        type={type}
        value={form[key] ?? ''}
        onChange={set(key)}
        className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
        {...extra}
      />
      {fieldErrors[key] && <p className="text-red-500 text-xs mt-1">{fieldErrors[key][0]}</p>}
    </div>
  )

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Placa *</label>
          <input
            type="text"
            value={form.placa}
            onChange={setPlaca}
            required
            maxLength={8}
            placeholder="ABC-1234"
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 uppercase"
          />
          {fieldErrors.placa && <p className="text-red-500 text-xs mt-1">{fieldErrors.placa[0]}</p>}
        </div>
        {field('Modelo *', 'modelo', 'text', { required: true })}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Marca</label>
          <select value={form.marca ?? ''} onChange={set('marca')}
            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Selecione...</option>
            {marcas.map(m => <option key={m} value={m}>{m}</option>)}
          </select>
          {fieldErrors.marca && <p className="text-red-500 text-xs mt-1">{fieldErrors.marca[0]}</p>}
        </div>
        {field('Ano *', 'ano', 'number', { required: true, min: 1980 })}
        {field('Cor', 'cor')}
        {field('Chassi', 'chassi', 'text', { maxLength: 17 })}
        {field('RENAVAM', 'renavam', 'text', { maxLength: 11 })}
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Combustível</label>
          <select value={form.combustivel} onChange={set('combustivel')} className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            {combustiveis.map(c => <option key={c} value={c}>{c.replace(/_/g, ' ')}</option>)}
          </select>
        </div>
        {field('Capacidade do tanque (L)', 'capacidade_tanque', 'number')}
        {field('KM atual', 'km_atual', 'number', { min: 0 })}
        {field('KM próxima revisão', 'km_proxima_revisao', 'number')}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
        <textarea value={form.observacoes ?? ''} onChange={set('observacoes')} rows={3}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
      </div>

      {/* Seção de Unidades — apenas em modo edição */}
      {veiculo ? (
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
              <p className="text-xs text-amber-600">Este veículo não está vinculado a nenhuma unidade</p>
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
