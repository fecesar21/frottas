import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import * as viagensApi from '../../api/viagens'
import * as localidadesApi from '../../api/localidades'
import { useAuth } from '../../contexts/AuthContext'
import MotoristaSelect from '../../components/shared/MotoristaSelect'
import VeiculoSelect from '../../components/shared/VeiculoSelect'
import VeiculoCheckinSelect from '../../components/shared/VeiculoCheckinSelect'
import ColaboradorSelect from '../../components/shared/ColaboradorSelect'
import Alert from '../../components/ui/Alert'
import { opcoesMotivo } from '../../utils/solicitacao'

const MOTIVOS = opcoesMotivo()

// Motivos exclusivos de cada tipo de veículo (mesma regra de Veiculo::ehAmbulancia()).
const SOMENTE_AMBULANCIA = ['transferencia_paciente', 'tfd']
const SOMENTE_ADMINISTRATIVO = ['buscar_medico', 'material_outro_hospital', 'transporte_colaborador', 'buscar_material_fornecedor', 'alimentacao']

const ehAmbulancia = (veiculo) =>
  (veiculo?.modelo ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toUpperCase().includes('AMBULANCIA')

// Sem veículo selecionado mostra todos os motivos.
export function motivosDoVeiculo(veiculo) {
  if (!veiculo?.modelo) return MOTIVOS
  const excluidos = ehAmbulancia(veiculo) ? SOMENTE_ADMINISTRATIVO : SOMENTE_AMBULANCIA
  return MOTIVOS.filter(m => !excluidos.includes(m.value))
}

export default function ViagemForm({ onSuccess }) {
  const { user, isOperador, checkinsAtivos } = useAuth()

  const [form, setForm] = useState({
    motorista_id: isOperador ? user.motorista_id : '',
    // Com 2 check-ins ativos o operador escolhe o veículo; com 1, ele é fixo.
    veiculo_id:   isOperador && checkinsAtivos.length === 1 ? checkinsAtivos[0].veiculo_id : '',
    origem: '',
    destino: '',
    motivo_viagem: '',
    numero_atendimento: '',
    km_saida: '',
  })
  const [colaboradores, setColaboradores] = useState([])
  const [veiculoGestao, setVeiculoGestao] = useState(null)
  const [error, setError] = useState('')
  const [fieldErrors, setFieldErrors] = useState({})

  const criar = useMutation({
    mutationFn: (data) => viagensApi.criar(data),
    onSuccess,
    onError: (e) => {
      if (e.response?.data?.errors) setFieldErrors(e.response.data.errors)
      else setError(e.response?.data?.message ?? 'Erro ao criar viagem')
    },
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    setError('')
    setFieldErrors({})
    if (isOperador && !form.veiculo_id) {
      setFieldErrors({ veiculo_id: ['Selecione o veículo da viagem.'] })
      return
    }
    if (form.numero_atendimento && (form.numero_atendimento.length < 6 || Number(form.numero_atendimento) < 100000)) {
      setFieldErrors({ numero_atendimento: ['O número do atendimento deve ter exatamente 6 dígitos e não pode ser 000000.'] })
      return
    }
    const transporteColaborador = form.motivo_viagem === 'transporte_colaborador'
    if (transporteColaborador && colaboradores.length === 0) {
      setFieldErrors({ colaborador_ids: ['Selecione ao menos um colaborador transportado.'] })
      return
    }
    criar.mutate({
      ...form,
      colaborador_ids: transporteColaborador ? colaboradores.map(c => c.id) : undefined,
      km_saida: Number(form.km_saida),
      numero_atendimento: form.numero_atendimento ? Number(form.numero_atendimento) : null,
    })
  }

  const veiculo = isOperador
    ? checkinsAtivos?.find(c => c.veiculo_id === form.veiculo_id)?.veiculo
    : veiculoGestao
  const motivos = motivosDoVeiculo(veiculo)
  if (form.motivo_viagem && !motivos.some(m => m.value === form.motivo_viagem)) {
    setForm(f => ({ ...f, motivo_viagem: '', numero_atendimento: '' }))
  }

  // Transferência de paciente: origem e destino vêm das Localidades cadastradas.
  const transferencia = form.motivo_viagem === 'transferencia_paciente'
  const { data: localidades = [] } = useQuery({
    queryKey: ['localidades'],
    queryFn: () => localidadesApi.listar().then(r => r.data),
    enabled: transferencia,
  })
  const nomesLocalidades = localidades.filter(l => l.ativo).map(l => l.nome.toUpperCase())

  const fe = (k) => fieldErrors[k] && <p className="text-red-500 text-xs mt-1">{fieldErrors[k][0]}</p>

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {error && <Alert type="error" message={error} />}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Motorista *</label>
        {isOperador ? (
          <div className="w-full border border-gray-200 bg-gray-50 rounded-lg px-3 py-2 text-sm text-gray-700">
            {user.nome}
          </div>
        ) : (
          <MotoristaSelect value={form.motorista_id} onChange={v => setForm(f => ({ ...f, motorista_id: v }))} required />
        )}
        {fe('motorista_id')}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Veículo *</label>
        {isOperador ? (
          <VeiculoCheckinSelect checkins={checkinsAtivos} value={form.veiculo_id} onChange={v => setForm(f => ({ ...f, veiculo_id: v }))} />
        ) : (
          <VeiculoSelect value={form.veiculo_id} onChange={v => setForm(f => ({ ...f, veiculo_id: v }))} required filterStatus="em_uso" onVeiculo={setVeiculoGestao} />
        )}
        {fe('veiculo_id')}
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Origem *</label>
          {transferencia ? (
            <select required value={form.origem} onChange={e => setForm(f => ({ ...f, origem: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione a localidade...</option>
              {nomesLocalidades.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          ) : (
            <input type="text" required value={form.origem} onChange={e => setForm(f => ({ ...f, origem: e.target.value.toUpperCase() }))}
              className="uppercase w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          )}
          {fe('origem')}
        </div>
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Destino *</label>
          {transferencia ? (
            <select required value={form.destino} onChange={e => setForm(f => ({ ...f, destino: e.target.value }))}
              className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Selecione a localidade...</option>
              {nomesLocalidades.map(n => <option key={n} value={n}>{n}</option>)}
            </select>
          ) : (
            <input type="text" required value={form.destino} onChange={e => setForm(f => ({ ...f, destino: e.target.value.toUpperCase() }))}
              className="uppercase w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          )}
          {fe('destino')}
        </div>
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">Motivo da Viagem *</label>
        <select required value={form.motivo_viagem}
          onChange={e => setForm(f => ({ ...f, motivo_viagem: e.target.value, numero_atendimento: e.target.value === 'transferencia_paciente' ? f.numero_atendimento : '',
            // Ao entrar/sair de transferência, origem/destino mudam de texto livre para lista.
            ...((e.target.value === 'transferencia_paciente') !== (f.motivo_viagem === 'transferencia_paciente') ? { origem: '', destino: '' } : {}) }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
          <option value="">Selecione...</option>
          {motivos.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
        </select>
        {fe('motivo_viagem')}
      </div>

      {form.motivo_viagem === 'transporte_colaborador' && (
        <div>
          <label className="block text-sm font-medium text-gray-700 mb-1">Colaboradores transportados *</label>
          <ColaboradorSelect value={colaboradores} onChange={setColaboradores} />
          {fe('colaborador_ids')}
          {Object.keys(fieldErrors).some(k => k.startsWith('colaborador_ids.')) &&
            <p className="text-red-500 text-xs mt-1">Um dos colaboradores selecionados está inválido ou inativo.</p>}
        </div>
      )}

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">
          Número do Atendimento {form.motivo_viagem === 'transferencia_paciente' && '*'}
        </label>
        <input type="text" inputMode="numeric" pattern="[0-9]{6}" minLength={6} maxLength={6}
          title="Exatamente 6 dígitos"
          required={form.motivo_viagem === 'transferencia_paciente'}
          value={form.numero_atendimento}
          onChange={e => {
            const v = e.target.value.replace(/\D/g, '').slice(0, 6)
            setForm(f => ({ ...f, numero_atendimento: v }))
          }}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('numero_atendimento')}
      </div>

      <div>
        <label className="block text-sm font-medium text-gray-700 mb-1">KM saída *</label>
        <input type="number" min={0} required value={form.km_saida} onChange={e => setForm(f => ({ ...f, km_saida: e.target.value }))}
          className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        {fe('km_saida')}
      </div>

      <div className="flex justify-end">
        <button type="submit" disabled={criar.isPending} className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-60">
          {criar.isPending ? 'Criando...' : 'Iniciar viagem'}
        </button>
      </div>
    </form>
  )
}
